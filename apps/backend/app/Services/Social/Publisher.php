<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\Upload;
use App\Services\Settings;
use App\Support\ApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Posting LANGSUNG ke API resmi tiap platform memakai token OAuth pengguna (tanpa Postiz).
 * Dijalankan di antrean (posting "sekarang") atau oleh scheduler (posting terjadwal).
 *
 * Aturan media per platform (sesuai API resmi):
 * - Facebook Page : teks, foto (≥1), atau 1 video — file dikirim langsung (multipart).
 * - Instagram     : WAJIB media; foto / carousel (≤10) / 1 video Reels — Instagram MENARIK file dari URL publik.
 * - Threads       : teks, atau 1 foto/video dari URL publik.
 * - TikTok        : 1 video (unggah file) atau foto (URL publik, domain harus terverifikasi di TikTok).
 * - YouTube       : WAJIB 1 video (unggah resumable).
 * - LinkedIn, X   : teks saja.
 */
class Publisher
{
    public const SUPPORTED = ['facebook', 'instagram', 'threads', 'tiktok', 'youtube', 'linkedin', 'x'];

    private const GRAPH = 'https://graph.facebook.com/v21.0';

    private const IG = 'https://graph.instagram.com/v21.0';

    private const THREADS = 'https://graph.threads.net/v1.0';

    /**
     * Validasi sebelum posting dibuat: media yang dipilih harus cocok dengan aturan platform.
     *
     * @param  array<int, Upload>  $media
     */
    public static function validate(array $platforms, array $media, string $content): void
    {
        $videos = count(array_filter($media, fn ($m) => str_starts_with($m->mime, 'video/')));
        $images = count($media) - $videos;
        $err = function (string $p, string $msg) {
            throw new ApiException('media_not_supported', Platforms::DEFS[$p]['label'].': '.$msg, 422, [], ['upload_ids' => [$msg]]);
        };
        if ($videos > 1 || ($videos && $images)) {
            $err($platforms[0], 'Pilih 1 video saja, atau beberapa foto (tidak dicampur).');
        }
        foreach ($platforms as $p) {
            match ($p) {
                'instagram' => count($media) === 0 ? $err($p, 'Instagram wajib memakai foto atau video.') : (count($media) > 10 ? $err($p, 'Maksimal 10 foto per carousel.') : null),
                'youtube' => $videos !== 1 ? $err($p, 'YouTube wajib memakai 1 video.') : null,
                'tiktok' => count($media) === 0 ? $err($p, 'TikTok wajib memakai video atau foto.') : ($images > 35 ? $err($p, 'Maksimal 35 foto.') : null),
                'threads' => count($media) > 1 ? $err($p, 'Threads: maksimal 1 foto/video per posting dari Krafio.') : null,
                'linkedin', 'x' => count($media) > 0 ? $err($p, 'Posting dari Krafio ke platform ini hanya teks.') : null,
                default => null,
            };
            if ($p === 'x' && mb_strlen($content) > 280) {
                $err($p, 'Teks untuk X maksimal 280 karakter.');
            }
        }
    }

    /** Jalankan posting (aman dipanggil ganda: hanya satu proses yang berhasil mengklaimnya). */
    public static function run(int $postId): void
    {
        $claimed = SocialPost::whereKey($postId)->where('provider', 'direct')->whereIn('status', ['queued', 'scheduled'])
            ->where(fn ($q) => $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))
            ->update(['status' => 'publishing', 'updated_at' => now()]);
        if (! $claimed) {
            return;
        }
        $post = SocialPost::findOrFail($postId);
        $media = Upload::whereIn('id', array_column((array) $post->media, 'upload_id'))->where('user_id', $post->user_id)->get()->all();
        $results = (array) ($post->external['results'] ?? []);
        foreach ($post->platforms as $platform) {
            if (($results[$platform]['ok'] ?? false) === true) {
                continue; // sudah terkirim di percobaan sebelumnya
            }
            try {
                $a = SocialAccount::where('user_id', $post->user_id)->where('platform', $platform)->where('provider', 'direct')->first()
                    ?? throw new \RuntimeException('Akun belum terhubung langsung.');
                self::freshToken($a);
                $results[$platform] = ['ok' => true] + self::publishTo($a, $post, $media);
            } catch (\Throwable $e) {
                Log::warning('Posting sosial gagal', ['post' => $post->id, 'platform' => $platform, 'error' => $e->getMessage()]);
                $results[$platform] = ['ok' => false, 'error' => Str::limit($e->getMessage(), 200)];
            }
        }
        $ok = count(array_filter($results, fn ($r) => $r['ok']));
        $failed = array_keys(array_filter($results, fn ($r) => ! $r['ok']));
        $post->update([
            'status' => $ok === count($results) ? 'sent' : ($ok > 0 ? 'partial' : 'failed'),
            'external' => ['results' => $results, 'published_at' => now()->toIso8601String()],
            'error' => $failed ? Str::limit(implode(' · ', array_map(fn ($p) => Platforms::DEFS[$p]['label'].': '.$results[$p]['error'], $failed)), 250) : null,
        ]);
        \App\Services\Audit::log('social.published', $post, ['status' => $post->status, 'platforms' => $post->platforms], $post->user_id);
    }

    /** Scheduler: kirim posting terjadwal yang sudah jatuh tempo. */
    public static function due(): int
    {
        $ids = SocialPost::where('provider', 'direct')->where('status', 'scheduled')->where('scheduled_at', '<=', now())->orderBy('scheduled_at')->limit(20)->pluck('id');
        foreach ($ids as $id) {
            self::run($id);
        }

        return $ids->count();
    }

    /** URL publik sementara (bertanda tangan, 6 jam) agar Instagram/Threads/TikTok bisa menarik file. */
    public static function publicUrl(Upload $u): string
    {
        $rel = URL::temporarySignedRoute('social.media', now()->addHours(6), ['upload' => $u->id], false);

        return rtrim((string) config('krafio.frontend_url'), '/').$rel;
    }

    private static function freshToken(SocialAccount $a): void
    {
        if ($a->expires_at && $a->expires_at->lt(now()->addMinutes(10))) {
            if (! Platforms::refresh($a)) {
                $a->update(['status' => 'expired']);
                throw new \RuntimeException('Token akun kedaluwarsa. Hubungkan ulang akun.');
            }
            $a->refresh();
        }
    }

    /**
     * @param  array<int, Upload>  $media
     * @return array{id:?string, url:?string}
     */
    private static function publishTo(SocialAccount $a, SocialPost $post, array $media): array
    {
        $token = (string) $a->access_token;
        $text = (string) $post->content;
        $video = collect($media)->first(fn ($m) => str_starts_with($m->mime, 'video/'));

        return match ($a->platform) {
            'facebook' => self::facebook($a, $token, $text, $media, $video),
            'instagram' => self::instagram($a, $token, $text, $media, $video),
            'threads' => self::threads($a, $token, $text, $media, $video),
            'tiktok' => self::tiktok($token, $text, $media, $video),
            'youtube' => self::youtube($token, $text, $video),
            'linkedin' => self::linkedin($a, $token, $text),
            'x' => self::x($token, $text),
            default => throw new \RuntimeException('Platform belum didukung untuk posting langsung.'),
        };
    }

    /* ---------------- Facebook Page ---------------- */

    private static function facebook(SocialAccount $a, string $token, string $text, array $media, ?Upload $video): array
    {
        $pages = self::ok(Http::timeout(20)->acceptJson()->get(self::GRAPH.'/me/accounts', ['fields' => 'id,name,access_token', 'access_token' => $token]), 'Daftar Page')['data'] ?? [];
        $want = $a->profile['page_id'] ?? null;
        $page = collect($pages)->firstWhere('id', $want) ?? ($pages[0] ?? null) ?? throw new \RuntimeException('Akun ini tidak mengelola Facebook Page mana pun.');
        $pt = (string) $page['access_token'];
        $id = $page['id'];
        $h = fn (int $t = 60) => Http::timeout($t)->acceptJson();
        if ($video) {
            $res = self::ok($h(600)->attach('source', self::stream($video), $video->original_name)->post(self::GRAPH."/{$id}/videos", ['description' => $text, 'access_token' => $pt]), 'Unggah video');

            return ['id' => (string) ($res['id'] ?? ''), 'url' => 'https://www.facebook.com/'.($res['id'] ?? '')];
        }
        if (count($media) === 1) {
            $res = self::ok($h(120)->attach('source', self::stream($media[0]), $media[0]->original_name)->post(self::GRAPH."/{$id}/photos", ['message' => $text, 'access_token' => $pt]), 'Unggah foto');

            return ['id' => (string) ($res['post_id'] ?? $res['id'] ?? ''), 'url' => 'https://www.facebook.com/'.($res['post_id'] ?? $res['id'] ?? '')];
        }
        $body = ['message' => $text, 'access_token' => $pt];
        foreach ($media as $i => $m) { // beberapa foto: unggah tanpa terbit → lampirkan ke satu posting
            $ph = self::ok($h(120)->attach('source', self::stream($m), $m->original_name)->post(self::GRAPH."/{$id}/photos", ['published' => 'false', 'access_token' => $pt]), 'Unggah foto');
            $body["attached_media[{$i}]"] = json_encode(['media_fbid' => $ph['id']]);
        }
        $res = self::ok($h()->asForm()->post(self::GRAPH."/{$id}/feed", $body), 'Posting Page');

        return ['id' => (string) ($res['id'] ?? ''), 'url' => 'https://www.facebook.com/'.($res['id'] ?? '')];
    }

    /* ---------------- Instagram (Instagram API with Instagram Login) ---------------- */

    private static function instagram(SocialAccount $a, string $token, string $text, array $media, ?Upload $video): array
    {
        $ig = $a->external_id ?: 'me';
        $h = fn () => Http::timeout(60)->acceptJson()->asForm();
        if ($video) {
            $c = self::ok($h()->post(self::IG."/{$ig}/media", ['media_type' => 'REELS', 'video_url' => self::publicUrl($video), 'caption' => $text, 'access_token' => $token]), 'Kontainer Reels')['id'];
            self::waitContainer(self::IG, $c, $token);
        } elseif (count($media) === 1) {
            $c = self::ok($h()->post(self::IG."/{$ig}/media", ['image_url' => self::publicUrl($media[0]), 'caption' => $text, 'access_token' => $token]), 'Kontainer foto')['id'];
            self::waitContainer(self::IG, $c, $token);
        } else {
            $children = [];
            foreach ($media as $m) {
                $children[] = $cid = self::ok($h()->post(self::IG."/{$ig}/media", ['image_url' => self::publicUrl($m), 'is_carousel_item' => 'true', 'access_token' => $token]), 'Kontainer carousel')['id'];
                self::waitContainer(self::IG, $cid, $token);
            }
            $c = self::ok($h()->post(self::IG."/{$ig}/media", ['media_type' => 'CAROUSEL', 'children' => implode(',', $children), 'caption' => $text, 'access_token' => $token]), 'Kontainer carousel')['id'];
            self::waitContainer(self::IG, $c, $token);
        }
        $id = (string) self::ok($h()->post(self::IG."/{$ig}/media_publish", ['creation_id' => $c, 'access_token' => $token]), 'Terbitkan')['id'];
        $link = Http::timeout(15)->acceptJson()->get(self::IG."/{$id}", ['fields' => 'permalink', 'access_token' => $token])->json('permalink');

        return ['id' => $id, 'url' => $link];
    }

    /* ---------------- Threads ---------------- */

    private static function threads(SocialAccount $a, string $token, string $text, array $media, ?Upload $video): array
    {
        $uid = $a->external_id ?: 'me';
        $body = ['text' => $text, 'access_token' => $token, 'media_type' => 'TEXT'];
        if ($video) {
            $body = ['media_type' => 'VIDEO', 'video_url' => self::publicUrl($video)] + $body;
        } elseif ($media) {
            $body = ['media_type' => 'IMAGE', 'image_url' => self::publicUrl($media[0])] + $body;
        }
        $c = self::ok(Http::timeout(60)->acceptJson()->asForm()->post(self::THREADS."/{$uid}/threads", $body), 'Kontainer Threads')['id'];
        if ($media) {
            self::waitContainer(self::THREADS, $c, $token, 'status');
        }
        $id = (string) self::ok(Http::timeout(60)->acceptJson()->asForm()->post(self::THREADS."/{$uid}/threads_publish", ['creation_id' => $c, 'access_token' => $token]), 'Terbitkan')['id'];

        return ['id' => $id, 'url' => $a->username ? 'https://www.threads.net/@'.ltrim($a->username, '@') : null];
    }

    /** Instagram/Threads memproses media secara asinkron → tunggu kontainer siap (maks ±5 menit). */
    private static function waitContainer(string $base, string $id, string $token, string $field = 'status_code'): void
    {
        $tries = (int) config('krafio.social_container_polls', 60);
        for ($i = 0; $i < $tries; $i++) {
            $st = (string) Http::timeout(15)->acceptJson()->get("{$base}/{$id}", ['fields' => $field, 'access_token' => $token])->json($field);
            if ($st === 'FINISHED' || $st === 'PUBLISHED' || $st === '') {
                return;
            }
            if ($st === 'ERROR' || $st === 'EXPIRED') {
                throw new \RuntimeException('Platform gagal memproses media ('.$st.'). Pastikan format/ukuran sesuai dan URL media dapat diakses publik.');
            }
            usleep((int) config('krafio.social_poll_ms', 5000) * 1000);
        }
        throw new \RuntimeException('Platform belum selesai memproses media. Coba lagi nanti.');
    }

    /* ---------------- TikTok (Content Posting API — Direct Post) ---------------- */

    private static function tiktok(string $token, string $text, array $media, ?Upload $video): array
    {
        $h = fn () => Http::timeout(60)->acceptJson()->withToken($token);
        $creator = self::tt(self::ok($h()->asJson()->post('https://open.tiktokapis.com/v2/post/publish/creator_info/query/', []), 'Info kreator'));
        $options = (array) ($creator['privacy_level_options'] ?? []);
        $want = (string) Settings::get('social.tiktok_privacy', 'SELF_ONLY');
        $privacy = in_array($want, $options, true) ? $want : (in_array('SELF_ONLY', $options, true) ? 'SELF_ONLY' : ($options[0] ?? 'SELF_ONLY'));
        $title = Str::limit($text, 2000, '');
        if ($video) {
            $size = (int) $video->size;
            $chunk = $size <= 64 * 1048576 ? $size : 10 * 1048576;
            $count = max(1, intdiv($size, $chunk));
            $init = self::tt(self::ok($h()->asJson()->post('https://open.tiktokapis.com/v2/post/publish/video/init/', [
                'post_info' => ['title' => $title, 'privacy_level' => $privacy, 'disable_comment' => false, 'disable_duet' => false, 'disable_stitch' => false],
                'source_info' => ['source' => 'FILE_UPLOAD', 'video_size' => $size, 'chunk_size' => $chunk, 'total_chunk_count' => $count],
            ]), 'Mulai unggah'));
            $fh = Storage::disk($video->disk ?: 'local')->readStream($video->path);
            try {
                for ($i = 0; $i < $count; $i++) {
                    $start = $i * $chunk;
                    $len = $i === $count - 1 ? $size - $start : $chunk; // potongan terakhir menampung sisa
                    $bytes = stream_get_contents($fh, $len);
                    $res = Http::timeout(600)->withHeaders(['Content-Range' => 'bytes '.$start.'-'.($start + $len - 1).'/'.$size])
                        ->withBody($bytes, $video->mime === 'video/quicktime' ? 'video/quicktime' : ($video->mime === 'video/webm' ? 'video/webm' : 'video/mp4'))->put((string) $init['upload_url']);
                    self::ok($res, 'Unggah video');
                }
            } finally {
                is_resource($fh) && fclose($fh);
            }

            return ['id' => (string) $init['publish_id'], 'url' => null, 'privacy' => $privacy];
        }
        $init = self::tt(self::ok($h()->asJson()->post('https://open.tiktokapis.com/v2/post/publish/content/init/', [
            'post_info' => ['title' => Str::limit($text, 90, ''), 'description' => Str::limit($text, 4000, ''), 'privacy_level' => $privacy, 'disable_comment' => false, 'auto_add_music' => true],
            'source_info' => ['source' => 'PULL_FROM_URL', 'photo_cover_index' => 0, 'photo_images' => array_map(fn ($m) => self::publicUrl($m), $media)],
            'post_mode' => 'DIRECT_POST', 'media_type' => 'PHOTO',
        ]), 'Posting foto'));

        return ['id' => (string) $init['publish_id'], 'url' => null, 'privacy' => $privacy];
    }

    /** TikTok membungkus galat dalam {error:{code,message}} meski HTTP 200. */
    private static function tt(array $json): array
    {
        $code = $json['error']['code'] ?? 'ok';
        if ($code !== 'ok') {
            throw new \RuntimeException('TikTok: '.($json['error']['message'] ?? $code));
        }

        return (array) ($json['data'] ?? []);
    }

    /* ---------------- YouTube (Data API v3 — resumable upload) ---------------- */

    private static function youtube(string $token, string $text, ?Upload $video): array
    {
        if (! $video) {
            throw new \RuntimeException('YouTube memerlukan video.');
        }
        $lines = preg_split('/\R/', trim($text));
        $title = Str::limit(trim(str_replace(['<', '>'], '', (string) ($lines[0] ?? ''))) ?: pathinfo($video->original_name, PATHINFO_FILENAME), 100, '');
        $privacy = (string) Settings::get('social.youtube_privacy', 'public');
        $init = Http::timeout(60)->acceptJson()->withToken($token)
            ->withHeaders(['X-Upload-Content-Length' => (string) $video->size, 'X-Upload-Content-Type' => $video->mime])
            ->post('https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status', [
                'snippet' => ['title' => $title, 'description' => Str::limit(str_replace(['<', '>'], '', $text), 5000, ''), 'categoryId' => '22'],
                'status' => ['privacyStatus' => in_array($privacy, ['public', 'unlisted', 'private'], true) ? $privacy : 'public', 'selfDeclaredMadeForKids' => false],
            ]);
        self::ok($init, 'Mulai unggah');
        $location = $init->header('Location') ?: throw new \RuntimeException('YouTube tidak mengembalikan URL unggah.');
        $res = Http::timeout(1800)->withToken($token)->withBody(\GuzzleHttp\Psr7\Utils::streamFor(self::stream($video)), $video->mime)->put($location);
        $id = (string) (self::ok($res, 'Unggah video')['id'] ?? '');

        return ['id' => $id, 'url' => $id ? 'https://youtu.be/'.$id : null];
    }

    /* ---------------- LinkedIn & X (teks) ---------------- */

    private static function linkedin(SocialAccount $a, string $token, string $text): array
    {
        $res = Http::timeout(30)->acceptJson()->withToken($token)->withHeaders(['X-Restli-Protocol-Version' => '2.0.0'])
            ->post('https://api.linkedin.com/v2/ugcPosts', [
                'author' => 'urn:li:person:'.$a->external_id, 'lifecycleState' => 'PUBLISHED',
                'specificContent' => ['com.linkedin.ugc.ShareContent' => ['shareCommentary' => ['text' => $text], 'shareMediaCategory' => 'NONE']],
                'visibility' => ['com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'],
            ]);
        $json = self::ok($res, 'Posting');
        $id = (string) ($json['id'] ?? $res->header('x-restli-id'));

        return ['id' => $id, 'url' => $id ? 'https://www.linkedin.com/feed/update/'.$id : null];
    }

    private static function x(string $token, string $text): array
    {
        $id = (string) (self::ok(Http::timeout(30)->acceptJson()->withToken($token)->post('https://api.x.com/2/tweets', ['text' => $text]), 'Posting')['data']['id'] ?? '');

        return ['id' => $id, 'url' => $id ? 'https://x.com/i/web/status/'.$id : null];
    }

    /* ---------------- Bantuan ---------------- */

    /** @return resource|string */
    private static function stream(Upload $u)
    {
        return Storage::disk($u->disk ?: 'local')->readStream($u->path) ?: throw new \RuntimeException('File media tidak ditemukan.');
    }

    private static function ok(Response $res, string $what): array
    {
        if (! $res->successful()) {
            $msg = $res->json('error.message') ?? $res->json('error.errors.0.message') ?? $res->json('error_description') ?? $res->json('detail') ?? $res->json('message') ?? ('HTTP '.$res->status());

            throw new \RuntimeException($what.' gagal (HTTP '.$res->status().'): '.Str::limit(is_string($msg) ? $msg : json_encode($msg), 160));
        }

        return (array) $res->json();
    }
}
