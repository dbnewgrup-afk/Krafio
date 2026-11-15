<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\Upload;
use App\Services\Audit;
use App\Services\Entitlements;
use App\Services\Social\Platforms;
use App\Services\Social\Postiz;
use App\Services\Social\Publisher;
use App\Support\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Integrasi media sosial (PRD 6.11, 17.6, 30).
 * Alur: panel user membuka POPUP ke halaman login resmi platform (atau halaman connect Postiz) → pengguna login sendiri →
 * platform memanggil callback REST Krafio dengan `code` + `state` (sekali pakai, terikat ke pengguna) → token disimpan
 * terenkripsi → data profil diambil dari API → popup memberi tahu panel (postMessage) lalu tertutup.
 */
class SocialController extends Controller
{
    private const STATE_TTL = 900;

    public function index(Request $r)
    {
        $u = $r->user();
        $acc = SocialAccount::where('user_id', $u->id)->get()->keyBy('platform');
        $postiz = Postiz::enabled();
        $ent = Entitlements::forUser($u)['social.integrations'] ?? null;

        return ['data' => [
            'postiz_enabled' => $postiz,
            'entitled' => (bool) ($ent['enabled'] ?? false),
            'limit' => Entitlements::limit($u, 'social_accounts'),
            'platforms' => array_map(function ($p) use ($acc, $postiz) {
                $a = $acc[$p] ?? null;
                $direct = (bool) Platforms::credentials($p);
                $status = ! $a ? ($direct || $postiz ? 'disconnected' : 'not_available') : ($a->expires_at && $a->expires_at->isPast() ? 'expired' : $a->status);

                return [
                    'platform' => $p, 'label' => Platforms::DEFS[$p]['label'], 'available' => $direct || $postiz, 'via' => ['direct' => $direct, 'postiz' => $postiz],
                    'status' => $status, 'provider' => $a?->provider, 'account_name' => $a?->account_name, 'username' => $a?->username, 'avatar_url' => $a?->avatar_url,
                    'stats' => $a?->profile['stats'] ?? [], 'synced_at' => $a?->synced_at?->toIso8601String(), 'expires_at' => $a?->expires_at?->toIso8601String(),
                    'last_error' => $a?->last_error, 'scopes' => Platforms::DEFS[$p]['scopes'], 'can_publish' => $a && $status === 'connected' && ($a->provider === 'postiz' || in_array($p, Publisher::SUPPORTED, true)),
                ];
            }, Platforms::LIST),
        ]];
    }

    /** Mulai koneksi: kembalikan URL untuk dibuka di popup. */
    public function connect(Request $r, string $platform)
    {
        abort_unless(in_array($platform, Platforms::LIST, true), 404);
        $u = $r->user();
        $via = $r->validate(['via' => 'nullable|in:direct,postiz'])['via'] ?? null;
        Entitlements::ensure($u, 'social.integrations', 0);
        $mine = SocialAccount::where('user_id', $u->id);
        if (! (clone $mine)->where('platform', $platform)->exists()) { // menyambung ulang platform yang sama tidak menambah jumlah
            Entitlements::ensureCount($u, 'social_accounts', (clone $mine)->count(), 1, 'social.integrations', 'akun sosial');
        }
        $via ??= Platforms::credentials($platform) ? 'direct' : (Postiz::enabled() ? 'postiz' : null);
        if (! $via || ($via === 'direct' && ! Platforms::credentials($platform)) || ($via === 'postiz' && ! Postiz::enabled())) {
            throw new ApiException('integration_not_available', 'Integrasi ini belum tersedia. Admin perlu mengonfigurasi aplikasi resmi platform atau Postiz.', 422);
        }
        $state = Str::random(48);
        if ($via === 'postiz') {
            $url = Postiz::connectLink($platform, rtrim((string) config('krafio.frontend_url'), '/').'/api/social/postiz-return?state='.$state);
            Cache::put("social:oauth:{$state}", ['user_id' => $u->id, 'platform' => $platform, 'via' => 'postiz', 'verifier' => null], self::STATE_TTL);
        } else {
            $auth = Platforms::authorizeUrl($platform, $state);
            $url = $auth['url'];
            Cache::put("social:oauth:{$state}", ['user_id' => $u->id, 'platform' => $platform, 'via' => 'direct', 'verifier' => $auth['verifier']], self::STATE_TTL);
        }
        Audit::log('social.connect_started', $u, ['platform' => $platform, 'via' => $via], $u->id);

        return ['data' => ['authorize_url' => $url, 'via' => $via, 'redirect_uri' => $via === 'direct' ? Platforms::redirectUri($platform) : null]];
    }

    /** Callback OAuth resmi (publik; identitas pengguna dari state sekali pakai, bukan sesi). */
    public function callback(Request $r, string $platform)
    {
        abort_unless(in_array($platform, Platforms::LIST, true), 404);
        $st = Cache::pull('social:oauth:'.(string) $r->query('state'));
        if (! $st || $st['platform'] !== $platform || $st['via'] !== 'direct') {
            return $this->popup(false, $platform, 'Sesi koneksi tidak valid atau kedaluwarsa. Ulangi dari Krafio.');
        }
        if ($err = $r->query('error') ?? $r->query('error_code')) {
            return $this->popup(false, $platform, $err === 'access_denied' ? 'Izin ditolak di halaman platform.' : 'Platform mengembalikan galat: '.Str::limit((string) ($r->query('error_description') ?? $err), 120));
        }
        $code = (string) $r->query('code');
        if ($code === '') {
            return $this->popup(false, $platform, 'Kode otorisasi tidak diterima.');
        }
        try {
            $tok = Platforms::exchange($platform, $code, $st['verifier']);
            $prof = Platforms::profile($platform, $tok['access_token'], $tok['user_id']);
            $a = SocialAccount::updateOrCreate(['user_id' => $st['user_id'], 'platform' => $platform], [
                'provider' => 'direct', 'external_id' => $prof['external_id'], 'account_name' => $prof['name'], 'username' => $prof['username'], 'avatar_url' => $prof['avatar'],
                'access_token' => $tok['access_token'], 'refresh_token' => $tok['refresh_token'], 'scopes' => $tok['scope'] ? preg_split('/[\s,]+/', $tok['scope']) : Platforms::DEFS[$platform]['scopes'],
                'expires_at' => $tok['expires_in'] ? now()->addSeconds($tok['expires_in']) : null, 'profile' => ['stats' => $prof['stats']], 'status' => 'connected', 'synced_at' => now(), 'last_error' => null,
            ]);
            Audit::log('social.connected', $a, ['platform' => $platform, 'via' => 'direct'], $st['user_id']);
        } catch (\Throwable $e) {
            report($e);

            return $this->popup(false, $platform, 'Koneksi gagal: '.Str::limit($e->getMessage(), 160));
        }

        return $this->popup(true, $platform, 'Akun '.(! empty($prof['username']) ? '@'.ltrim($prof['username'], '@') : ($prof['name'] ?? '')).' terhubung.');
    }

    /** Kembali dari halaman connect Postiz → klaim kanal baru untuk pengguna ini. */
    public function postizReturn(Request $r)
    {
        $st = Cache::pull('social:oauth:'.(string) $r->query('state'));
        if (! $st || $st['via'] !== 'postiz') {
            return $this->popup(false, null, 'Sesi koneksi tidak valid atau kedaluwarsa. Ulangi dari Krafio.');
        }
        try {
            $a = Postiz::claim($st['user_id'], $st['platform']);
        } catch (\Throwable $e) {
            report($e);

            return $this->popup(false, $st['platform'], 'Koneksi Postiz gagal: '.Str::limit($e->getMessage(), 160));
        }
        if (! $a) {
            return $this->popup(false, $st['platform'], 'Akun belum terhubung di Postiz. Selesaikan login di halaman platform lalu coba lagi.');
        }
        Audit::log('social.connected', $a, ['platform' => $st['platform'], 'via' => 'postiz'], $st['user_id']);

        return $this->popup(true, $st['platform'], 'Akun '.$a->account_name.' terhubung lewat Postiz.');
    }

    public function sync(Request $r, string $platform)
    {
        $a = SocialAccount::where('user_id', $r->user()->id)->where('platform', $platform)->firstOrFail();
        if ($a->provider === 'postiz') {
            $a = Postiz::claim($r->user()->id, $platform) ?? $a;
        } else {
            $a = Platforms::sync($a);
        }

        return $this->index($r);
    }

    public function disconnect(Request $r, string $platform)
    {
        $a = SocialAccount::where('user_id', $r->user()->id)->where('platform', $platform)->first();
        if ($a) {
            $a->delete();
            Audit::log('social.disconnected', null, ['platform' => $platform], $r->user()->id);
        }

        return response()->noContent();
    }

    /* ---------------- Posting & jadwal (Postiz) ---------------- */

    public function posts(Request $r)
    {
        return ['data' => SocialPost::where('user_id', $r->user()->id)->latest('id')->limit(50)->get()->map(fn ($p) => [
            'id' => $p->id, 'content' => $p->content, 'platforms' => $p->platforms, 'type' => $p->type, 'status' => $p->status, 'error' => $p->error,
            'scheduled_at' => $p->scheduled_at?->toIso8601String(), 'created_at' => $p->created_at->toIso8601String(), 'media' => count((array) $p->media), 'provider' => $p->provider,
            'results' => collect($p->external['results'] ?? [])->map(fn ($x) => ['ok' => (bool) $x['ok'], 'url' => $x['url'] ?? null, 'error' => $x['error'] ?? null])->all(),
        ])];
    }

    public function createPost(Request $r)
    {
        $u = $r->user();
        Entitlements::ensure($u, 'social.integrations', 0);
        $d = $r->validate([
            'content' => 'required|string|min:1|max:5000',
            'platforms' => 'required|array|min:1', 'platforms.*' => 'in:'.implode(',', Platforms::LIST),
            'type' => 'required|in:now,schedule,draft',
            'scheduled_at' => 'required_if:type,schedule|nullable|date|after:now',
            'upload_ids' => 'nullable|array|max:4', 'upload_ids.*' => 'integer',
        ]);
        $platforms = array_values(array_unique($d['platforms']));
        $uploads = Upload::whereIn('id', $d['upload_ids'] ?? [])->where('user_id', $u->id)->get();
        if ($uploads->count() !== count(array_unique($d['upload_ids'] ?? []))) {
            throw new ApiException('validation_failed', 'Media tidak ditemukan.', 422, [], ['upload_ids' => ['Media tidak ditemukan.']]);
        }
        $media = $uploads->map(fn ($m) => ['upload_id' => $m->id])->values()->all();
        // Jalur: langsung ke API platform bila SEMUA platform terpilih terhubung langsung; selain itu Postiz.
        $accounts = SocialAccount::where('user_id', $u->id)->whereIn('platform', $platforms)->where('status', 'connected')->get()->keyBy('platform');
        $direct = collect($platforms)->every(fn ($p) => ($accounts[$p]->provider ?? null) === 'direct');
        if ($direct) {
            Publisher::validate($platforms, $uploads->all(), $d['content']);
            $post = SocialPost::create(['user_id' => $u->id, 'content' => $d['content'], 'platforms' => $platforms, 'type' => $d['type'],
                'scheduled_at' => $d['type'] === 'schedule' ? $d['scheduled_at'] : null, 'media' => $media, 'provider' => 'direct',
                'status' => ['now' => 'queued', 'schedule' => 'scheduled', 'draft' => 'draft'][$d['type']]]);
            if ($d['type'] === 'now') {
                $id = $post->id;
                dispatch(fn () => Publisher::run($id))->onQueue('default');
            }
        } else {
            if (! Postiz::enabled()) {
                $missing = collect($platforms)->reject(fn ($p) => ($accounts[$p]->provider ?? null) === 'direct')->map(fn ($p) => Platforms::DEFS[$p]['label'])->implode(', ');
                throw new ApiException('account_not_connected', 'Hubungkan dulu akun berikut: '.$missing.'.', 422);
            }
            $post = SocialPost::create(['user_id' => $u->id, 'content' => $d['content'], 'platforms' => $platforms, 'type' => $d['type'],
                'scheduled_at' => $d['type'] === 'schedule' ? $d['scheduled_at'] : null, 'media' => $media, 'status' => 'queued', 'provider' => 'postiz']);
            try {
                Postiz::publish($post);
            } catch (ApiException $e) {
                $post->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 250)]);

                throw $e;
            }
        }
        Audit::log('social.post_'.$post->type, $post, ['platforms' => $post->platforms], $u->id);

        return response()->json(['data' => ['id' => $post->id, 'status' => $post->fresh()->status]], 201);
    }

    /** Kirim ulang posting gagal/sebagian, terbitkan draf, atau batalkan jadwal (posting langsung). */
    public function postAction(Request $r, SocialPost $post, string $action)
    {
        abort_unless($post->user_id === $r->user()->id && $post->provider === 'direct', 404);
        Entitlements::ensure($r->user(), 'social.integrations', 0);
        if ($action === 'cancel') {
            abort_unless(in_array($post->status, ['scheduled', 'draft'], true), 422, 'Posting ini tidak dapat dibatalkan.');
            $post->delete();

            return response()->noContent();
        }
        abort_unless($action === 'publish' && in_array($post->status, ['failed', 'partial', 'draft', 'scheduled'], true), 422, 'Posting ini tidak dapat dikirim ulang.');
        $post->update(['status' => 'queued', 'scheduled_at' => null, 'type' => 'now', 'error' => null]);
        $id = $post->id;
        dispatch(fn () => Publisher::run($id))->onQueue('default');

        return ['data' => ['id' => $post->id, 'status' => $post->fresh()->status]];
    }

    /** File media untuk platform yang menarik dari URL (Instagram/Threads/TikTok) — hanya lewat URL bertanda tangan & berjangka. */
    public function media(Upload $upload)
    {
        abort_unless(Storage::disk($upload->disk ?: 'local')->exists($upload->path), 404);

        return Storage::disk($upload->disk ?: 'local')->response($upload->path, $upload->original_name, ['Content-Type' => $upload->mime, 'Cache-Control' => 'private, max-age=3600']);
    }

    /* ---------------- Halaman popup ---------------- */

    private function popup(bool $ok, ?string $platform, string $message)
    {
        $front = rtrim((string) config('krafio.frontend_url'), '/');
        $payload = json_encode(['type' => 'krafio:social', 'ok' => $ok, 'platform' => $platform, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $back = $front.'/app/social?'.http_build_query(['social' => $ok ? 'connected' : 'error', 'platform' => $platform, 'message' => $message]);
        $title = $ok ? 'Akun terhubung' : 'Koneksi gagal';
        $msg = e($message);
        $color = $ok ? '#16a34a' : '#dc2626';
        $html = <<<HTML
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{$title} — Krafio</title>
<style>body{font-family:system-ui,sans-serif;background:#0B1020;color:#E5E9F5;display:grid;place-items:center;min-height:100vh;margin:0}main{max-width:360px;padding:24px;text-align:center}h1{font-size:18px;color:{$color}}a{color:#818CF8}</style></head>
<body><main><h1>{$title}</h1><p>{$msg}</p><p><a href="{$back}">Kembali ke Krafio</a></p></main>
<script>(function(){var d={$payload};try{if(window.opener&&!window.opener.closed){window.opener.postMessage(d,window.location.origin);setTimeout(function(){window.close()},900);return}}catch(e){}setTimeout(function(){window.location.replace(document.querySelector('a').href)},1500)})();</script>
</body></html>
HTML;

        return response($html, $ok ? 200 : 400)->header('Content-Type', 'text/html; charset=utf-8')->header('Cache-Control', 'no-store')
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'");
    }
}
