<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\Upload;
use App\Services\Integrations;
use App\Support\ApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Adapter Postiz Public API (repository-github/14-postiz, AGPL → dipakai sebagai LAYANAN terpisah lewat API, PRD 53.6).
 * Header `Authorization: <api key>`; endpoint /public/v1/*:
 *  - POST /integrations/connect {integration, redirectUrl} → {url}: halaman Postiz tempat pengguna login ke platform
 *  - GET  /integrations → kanal yang terhubung di organisasi Postiz
 *  - POST /upload (multipart) → {id, path};  POST /posts → buat/jadwalkan posting
 * Satu organisasi Postiz dipakai bersama; kepemilikan kanal per pengguna Krafio dicatat di social_accounts (provider=postiz).
 */
class Postiz
{
    public static function config(): ?array
    {
        $c = Integrations::active('postiz');

        return $c && ! empty($c['base_url']) && ! empty($c['api_key']) ? $c : null;
    }

    public static function enabled(): bool
    {
        return (bool) self::config();
    }

    private static function client(): PendingRequest
    {
        $c = self::config() ?? throw new ApiException('postiz_not_configured', 'Postiz belum dikonfigurasi Admin.', 422);

        return Http::baseUrl(rtrim((string) $c['base_url'], '/').'/public/v1')->timeout(30)->acceptJson()->withHeaders(['Authorization' => (string) $c['api_key']]);
    }

    /** Link connect Postiz untuk satu platform; pengguna login sendiri di halaman Postiz/platform. */
    public static function connectLink(string $platform, string $returnUrl): string
    {
        $id = Platforms::DEFS[$platform]['postiz'] ?? $platform;
        $res = self::client()->post('/integrations/connect', ['integration' => $id, 'redirectUrl' => $returnUrl]);
        if (! $res->successful() || ! $res->json('url')) {
            throw new ApiException('postiz_error', 'Postiz menolak membuat link koneksi: '.Str::limit((string) ($res->json('msg') ?? $res->json('message') ?? $res->status()), 140), 502);
        }

        return (string) $res->json('url');
    }

    /** @return array<int, array{id:string, name:string, identifier:string, picture:?string, disabled:bool, profile:?string}> */
    public static function integrations(): array
    {
        $res = self::client()->get('/integrations');
        if (! $res->successful()) {
            throw new ApiException('postiz_error', 'Gagal membaca kanal Postiz (HTTP '.$res->status().').', 502);
        }

        return array_map(fn ($i) => [
            'id' => (string) ($i['id'] ?? ''), 'name' => (string) ($i['name'] ?? ''), 'identifier' => (string) ($i['identifier'] ?? ''),
            'picture' => $i['picture'] ?? null, 'disabled' => (bool) ($i['disabled'] ?? false), 'profile' => $i['profile'] ?? null,
        ], (array) $res->json());
    }

    /**
     * Setelah kembali dari halaman connect: klaim kanal Postiz baru untuk platform ini yang belum dimiliki pengguna lain.
     */
    public static function claim(int $userId, string $platform): ?SocialAccount
    {
        $identifier = Platforms::DEFS[$platform]['postiz'] ?? $platform;
        $taken = SocialAccount::where('provider', 'postiz')->where('user_id', '!=', $userId)->pluck('external_id')->all();
        $mine = SocialAccount::where('provider', 'postiz')->where('user_id', $userId)->where('platform', $platform)->value('external_id');
        $cands = array_values(array_filter(self::integrations(), fn ($i) => $i['identifier'] === $identifier && ! $i['disabled'] && ! in_array($i['id'], $taken, true)));
        if (! $cands) {
            return null;
        }
        // Kanal terbaru (terakhir dalam daftar) yang belum dimiliki pengguna lain; utamakan kanal milik sendiri bila tersambung ulang
        $pick = collect($cands)->firstWhere('id', $mine) ?? end($cands);

        return SocialAccount::updateOrCreate(['user_id' => $userId, 'platform' => $platform], [
            'provider' => 'postiz', 'external_id' => $pick['id'], 'account_name' => $pick['name'], 'username' => $pick['profile'], 'avatar_url' => $pick['picture'],
            'access_token' => null, 'refresh_token' => null, 'status' => 'connected', 'expires_at' => null, 'synced_at' => now(), 'last_error' => null, 'profile' => ['postiz' => ['identifier' => $pick['identifier']]],
        ]);
    }

    /** Kirim posting ke Postiz (sekarang / jadwal / draf) untuk akun-akun Postiz milik pengguna. */
    public static function publish(SocialPost $post): void
    {
        $accounts = SocialAccount::where('user_id', $post->user_id)->where('provider', 'postiz')->whereIn('platform', $post->platforms)->where('status', 'connected')->get();
        if ($accounts->isEmpty()) {
            throw new ApiException('no_postiz_accounts', 'Belum ada akun yang terhubung lewat Postiz untuk platform yang dipilih.', 422);
        }
        $images = [];
        foreach ((array) $post->media as $m) {
            $up = Upload::where('id', $m['upload_id'] ?? 0)->where('user_id', $post->user_id)->first();
            if (! $up) {
                continue;
            }
            $res = self::client()->attach('file', Storage::disk($up->disk ?: 'local')->get($up->path), $up->original_name)->post('/upload');
            if (! $res->successful()) {
                throw new ApiException('postiz_error', 'Unggah media ke Postiz gagal (HTTP '.$res->status().').', 502);
            }
            $images[] = ['id' => (string) $res->json('id'), 'path' => (string) $res->json('path')];
        }
        $body = [
            'type' => $post->type, 'shortLink' => false, 'tags' => [],
            'date' => ($post->scheduled_at ?? now()->addMinute())->utc()->toIso8601String(),
            'posts' => $accounts->map(fn ($a) => [
                'integration' => ['id' => $a->external_id],
                'value' => [['content' => $post->content, 'image' => $images]],
                'settings' => ['__type' => $a->profile['postiz']['identifier'] ?? (Platforms::DEFS[$a->platform]['postiz'] ?? $a->platform)],
            ])->values()->all(),
        ];
        $res = self::client()->post('/posts', $body);
        if (! $res->successful()) {
            throw new ApiException('postiz_error', 'Postiz menolak posting: '.Str::limit((string) ($res->json('message') ?? $res->json('msg') ?? $res->body()), 160), 502);
        }
        $post->update(['status' => $post->type === 'draft' ? 'draft' : ($post->type === 'now' ? 'sent' : 'scheduled'), 'external' => (array) $res->json(), 'error' => null]);
    }

    /** Uji koneksi untuk Admin. */
    public static function test(): array
    {
        try {
            $n = count(self::integrations());

            return [true, "Terhubung ke Postiz. {$n} kanal tersedia."];
        } catch (\Throwable $e) {
            return [false, $e->getMessage()];
        }
    }
}
