<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use App\Services\Integrations;
use App\Support\ApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * OAuth resmi tiap platform (PRD 30, 17.6). Pengguna login SENDIRI di halaman platform (popup browser);
 * Krafio hanya menerima `code` di callback REST, menukarnya jadi token (disimpan terenkripsi), lalu membaca data profil.
 * Kredensial aplikasi (client id/secret) diisi Admin di Integrasi → "social_<platform>".
 */
class Platforms
{
    public const LIST = ['instagram', 'facebook', 'threads', 'tiktok', 'youtube', 'linkedin', 'x'];

    /** Endpoint & scope resmi per platform. */
    public const DEFS = [
        'facebook' => [
            'label' => 'Facebook Page', 'authorize' => 'https://www.facebook.com/v21.0/dialog/oauth', 'token' => 'https://graph.facebook.com/v21.0/oauth/access_token',
            'scopes' => ['public_profile', 'pages_show_list', 'pages_read_engagement', 'pages_manage_posts'], 'separator' => ',', 'postiz' => 'facebook',
        ],
        'instagram' => [
            'label' => 'Instagram (akun bisnis/kreator)', 'authorize' => 'https://www.instagram.com/oauth/authorize', 'token' => 'https://api.instagram.com/oauth/access_token',
            'scopes' => ['instagram_business_basic', 'instagram_business_content_publish', 'instagram_business_manage_insights'], 'separator' => ',', 'postiz' => 'instagram-standalone',
        ],
        'threads' => [
            'label' => 'Threads', 'authorize' => 'https://threads.net/oauth/authorize', 'token' => 'https://graph.threads.net/oauth/access_token',
            'scopes' => ['threads_basic', 'threads_content_publish'], 'separator' => ',', 'postiz' => 'threads',
        ],
        'tiktok' => [
            'label' => 'TikTok', 'authorize' => 'https://www.tiktok.com/v2/auth/authorize/', 'token' => 'https://open.tiktokapis.com/v2/oauth/token/',
            'scopes' => ['user.info.basic', 'user.info.stats', 'video.publish'], 'separator' => ',', 'client_param' => 'client_key', 'postiz' => 'tiktok',
        ],
        'youtube' => [
            'label' => 'YouTube', 'authorize' => 'https://accounts.google.com/o/oauth2/v2/auth', 'token' => 'https://oauth2.googleapis.com/token',
            'scopes' => ['https://www.googleapis.com/auth/youtube.readonly', 'https://www.googleapis.com/auth/youtube.upload'], 'separator' => ' ',
            'extra' => ['access_type' => 'offline', 'prompt' => 'consent', 'include_granted_scopes' => 'true'], 'postiz' => 'youtube',
        ],
        'linkedin' => [
            'label' => 'LinkedIn', 'authorize' => 'https://www.linkedin.com/oauth/v2/authorization', 'token' => 'https://www.linkedin.com/oauth/v2/accessToken',
            'scopes' => ['openid', 'profile', 'w_member_social'], 'separator' => ' ', 'postiz' => 'linkedin',
        ],
        'x' => [
            'label' => 'X (Twitter)', 'authorize' => 'https://x.com/i/oauth2/authorize', 'token' => 'https://api.x.com/2/oauth2/token',
            'scopes' => ['tweet.read', 'tweet.write', 'users.read', 'offline.access'], 'separator' => ' ', 'pkce' => true, 'basic_auth' => true, 'postiz' => 'x',
        ],
    ];

    /** Kredensial aplikasi resmi (aktif) atau null. */
    public static function credentials(string $platform): ?array
    {
        $c = Integrations::active('social_'.$platform);

        return $c && ! empty($c['client_id']) && ! empty($c['client_secret']) ? $c : null;
    }

    /** URL callback yang didaftarkan di developer console platform (lewat domain SPA: same-origin di produksi & proxy Vite). */
    public static function redirectUri(string $platform): string
    {
        return rtrim((string) config('krafio.frontend_url'), '/').'/api/social/callback/'.$platform;
    }

    /** @return array{url: string, verifier: ?string} */
    public static function authorizeUrl(string $platform, string $state): array
    {
        $d = self::DEFS[$platform];
        $c = self::credentials($platform) ?? throw new ApiException('integration_not_configured', 'Aplikasi resmi platform ini belum dikonfigurasi Admin.', 422);
        $q = [
            $d['client_param'] ?? 'client_id' => $c['client_id'],
            'redirect_uri' => self::redirectUri($platform),
            'response_type' => 'code',
            'scope' => implode($d['separator'], $d['scopes']),
            'state' => $state,
        ] + ($d['extra'] ?? []);
        $verifier = null;
        if (! empty($d['pkce'])) {
            $verifier = Str::random(64);
            $q['code_challenge'] = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
            $q['code_challenge_method'] = 'S256';
        }

        return ['url' => $d['authorize'].'?'.http_build_query($q, '', '&', PHP_QUERY_RFC3986), 'verifier' => $verifier];
    }

    /** Tukar code → token. @return array{access_token:string, refresh_token:?string, expires_in:?int, scope:?string, user_id:?string} */
    public static function exchange(string $platform, string $code, ?string $verifier): array
    {
        $d = self::DEFS[$platform];
        $c = self::credentials($platform) ?? throw new \RuntimeException('Kredensial aplikasi tidak aktif.');
        $body = [
            $d['client_param'] ?? 'client_id' => $c['client_id'], 'client_secret' => $c['client_secret'], 'code' => $code,
            'grant_type' => 'authorization_code', 'redirect_uri' => self::redirectUri($platform),
        ];
        if ($verifier) {
            $body['code_verifier'] = $verifier;
        }
        $req = Http::timeout(20)->acceptJson()->asForm();
        if (! empty($d['basic_auth'])) {
            $req = $req->withBasicAuth($c['client_id'], $c['client_secret']);
            unset($body['client_secret']);
        }
        $res = $platform === 'facebook' ? Http::timeout(20)->acceptJson()->get($d['token'], $body) : $req->post($d['token'], $body);
        $tok = self::ok($res, 'Penukaran token');
        $tok = $tok['data'][0] ?? $tok; // Instagram kadang membungkus dalam data[]
        $access = (string) ($tok['access_token'] ?? '');
        if ($access === '') {
            throw new \RuntimeException('Platform tidak mengembalikan access token.');
        }
        $out = ['access_token' => $access, 'refresh_token' => $tok['refresh_token'] ?? null, 'expires_in' => isset($tok['expires_in']) ? (int) $tok['expires_in'] : null,
            'scope' => is_array($tok['scope'] ?? null) ? implode(',', $tok['scope']) : ($tok['scope'] ?? null), 'user_id' => isset($tok['user_id']) ? (string) $tok['user_id'] : ($tok['open_id'] ?? null)];
        // Token jangka panjang (Meta): 1 jam → 60 hari
        if (in_array($platform, ['instagram', 'threads'], true)) {
            $url = $platform === 'instagram' ? 'https://graph.instagram.com/access_token' : 'https://graph.threads.net/access_token';
            $grant = $platform === 'instagram' ? 'ig_exchange_token' : 'th_exchange_token';
            $long = Http::timeout(20)->acceptJson()->get($url, ['grant_type' => $grant, 'client_secret' => $c['client_secret'], 'access_token' => $access]);
            if ($long->successful() && $long->json('access_token')) {
                $out['access_token'] = (string) $long->json('access_token');
                $out['expires_in'] = (int) $long->json('expires_in', 5184000);
            }
        } elseif ($platform === 'facebook') {
            $long = Http::timeout(20)->acceptJson()->get($d['token'], ['grant_type' => 'fb_exchange_token', 'client_id' => $c['client_id'], 'client_secret' => $c['client_secret'], 'fb_exchange_token' => $access]);
            if ($long->successful() && $long->json('access_token')) {
                $out['access_token'] = (string) $long->json('access_token');
                $out['expires_in'] = (int) $long->json('expires_in', 5184000);
            }
        }

        return $out;
    }

    /** Perbarui access token memakai refresh token (TikTok, YouTube, LinkedIn, X). */
    public static function refresh(SocialAccount $a): bool
    {
        $d = self::DEFS[$a->platform] ?? null;
        $c = self::credentials($a->platform);
        if (! $d || ! $c) {
            return false;
        }
        if (in_array($a->platform, ['instagram', 'threads'], true)) {
            $url = $a->platform === 'instagram' ? 'https://graph.instagram.com/refresh_access_token' : 'https://graph.threads.net/refresh_access_token';
            $res = Http::timeout(20)->acceptJson()->get($url, ['grant_type' => $a->platform === 'instagram' ? 'ig_refresh_token' : 'th_refresh_token', 'access_token' => $a->access_token]);
            if (! $res->successful()) {
                return false;
            }
            $a->update(['access_token' => $res->json('access_token'), 'expires_at' => now()->addSeconds((int) $res->json('expires_in', 5184000)), 'status' => 'connected', 'last_error' => null]);

            return true;
        }
        if (! $a->refresh_token) {
            return false;
        }
        $body = [$d['client_param'] ?? 'client_id' => $c['client_id'], 'client_secret' => $c['client_secret'], 'grant_type' => 'refresh_token', 'refresh_token' => $a->refresh_token];
        $req = Http::timeout(20)->acceptJson()->asForm();
        if (! empty($d['basic_auth'])) {
            $req = $req->withBasicAuth($c['client_id'], $c['client_secret']);
            unset($body['client_secret']);
        }
        $res = $req->post($d['token'], $body);
        if (! $res->successful() || ! $res->json('access_token')) {
            return false;
        }
        $a->update(['access_token' => $res->json('access_token'), 'refresh_token' => $res->json('refresh_token') ?: $a->refresh_token,
            'expires_at' => $res->json('expires_in') ? now()->addSeconds((int) $res->json('expires_in')) : $a->expires_at, 'status' => 'connected', 'last_error' => null]);

        return true;
    }

    /**
     * Data profil dari API resmi (nama, username, foto, statistik).
     *
     * @return array{external_id:?string, name:?string, username:?string, avatar:?string, stats: array<string,int>}
     */
    public static function profile(string $platform, string $token, ?string $userId = null): array
    {
        $h = Http::timeout(20)->acceptJson();
        switch ($platform) {
            case 'facebook':
                $me = self::ok($h->get('https://graph.facebook.com/v21.0/me', ['fields' => 'id,name,picture.type(large)', 'access_token' => $token]), 'Profil');
                $pages = $h->get('https://graph.facebook.com/v21.0/me/accounts', ['fields' => 'id,name,fan_count,followers_count', 'access_token' => $token])->json('data') ?? [];

                return ['external_id' => $me['id'] ?? null, 'name' => $me['name'] ?? null, 'username' => $pages[0]['name'] ?? null, 'avatar' => $me['picture']['data']['url'] ?? null,
                    'stats' => ['pages' => count($pages), 'followers' => (int) array_sum(array_map(fn ($p) => (int) ($p['followers_count'] ?? $p['fan_count'] ?? 0), $pages))]];
            case 'instagram':
                $me = self::ok($h->get('https://graph.instagram.com/me', ['fields' => 'user_id,username,name,profile_picture_url,followers_count,follows_count,media_count', 'access_token' => $token]), 'Profil');

                return ['external_id' => (string) ($me['user_id'] ?? $me['id'] ?? $userId), 'name' => $me['name'] ?? null, 'username' => $me['username'] ?? null, 'avatar' => $me['profile_picture_url'] ?? null,
                    'stats' => ['followers' => (int) ($me['followers_count'] ?? 0), 'following' => (int) ($me['follows_count'] ?? 0), 'posts' => (int) ($me['media_count'] ?? 0)]];
            case 'threads':
                $me = self::ok($h->get('https://graph.threads.net/v1.0/me', ['fields' => 'id,username,name,threads_profile_picture_url', 'access_token' => $token]), 'Profil');

                return ['external_id' => $me['id'] ?? $userId, 'name' => $me['name'] ?? null, 'username' => $me['username'] ?? null, 'avatar' => $me['threads_profile_picture_url'] ?? null, 'stats' => []];
            case 'tiktok':
                $u = self::ok($h->withToken($token)->get('https://open.tiktokapis.com/v2/user/info/', ['fields' => 'open_id,display_name,username,avatar_url,follower_count,following_count,likes_count,video_count']), 'Profil')['data']['user'] ?? [];

                return ['external_id' => $u['open_id'] ?? $userId, 'name' => $u['display_name'] ?? null, 'username' => $u['username'] ?? null, 'avatar' => $u['avatar_url'] ?? null,
                    'stats' => ['followers' => (int) ($u['follower_count'] ?? 0), 'following' => (int) ($u['following_count'] ?? 0), 'likes' => (int) ($u['likes_count'] ?? 0), 'videos' => (int) ($u['video_count'] ?? 0)]];
            case 'youtube':
                $ch = self::ok($h->withToken($token)->get('https://www.googleapis.com/youtube/v3/channels', ['part' => 'snippet,statistics', 'mine' => 'true']), 'Profil')['items'][0] ?? [];

                return ['external_id' => $ch['id'] ?? null, 'name' => $ch['snippet']['title'] ?? null, 'username' => $ch['snippet']['customUrl'] ?? null, 'avatar' => $ch['snippet']['thumbnails']['default']['url'] ?? null,
                    'stats' => ['subscribers' => (int) ($ch['statistics']['subscriberCount'] ?? 0), 'videos' => (int) ($ch['statistics']['videoCount'] ?? 0), 'views' => (int) ($ch['statistics']['viewCount'] ?? 0)]];
            case 'linkedin':
                $me = self::ok($h->withToken($token)->get('https://api.linkedin.com/v2/userinfo'), 'Profil');

                return ['external_id' => $me['sub'] ?? null, 'name' => $me['name'] ?? null, 'username' => $me['email'] ?? null, 'avatar' => $me['picture'] ?? null, 'stats' => []];
            case 'x':
                $u = self::ok($h->withToken($token)->get('https://api.x.com/2/users/me', ['user.fields' => 'profile_image_url,public_metrics']), 'Profil')['data'] ?? [];

                return ['external_id' => $u['id'] ?? null, 'name' => $u['name'] ?? null, 'username' => $u['username'] ?? null, 'avatar' => $u['profile_image_url'] ?? null,
                    'stats' => ['followers' => (int) ($u['public_metrics']['followers_count'] ?? 0), 'following' => (int) ($u['public_metrics']['following_count'] ?? 0), 'posts' => (int) ($u['public_metrics']['tweet_count'] ?? 0)]];
        }

        throw new \RuntimeException('Platform tidak dikenal.');
    }

    /** Ambil ulang profil; token kedaluwarsa → coba refresh sekali, gagal → status expired. */
    public static function sync(SocialAccount $a): SocialAccount
    {
        if ($a->provider !== 'direct') {
            return $a;
        }
        $attempt = function () use ($a) {
            $p = self::profile($a->platform, (string) $a->access_token, $a->external_id);
            $a->update(['account_name' => $p['name'] ?? $a->account_name, 'username' => $p['username'] ?? $a->username, 'avatar_url' => $p['avatar'] ?? $a->avatar_url,
                'external_id' => $p['external_id'] ?? $a->external_id, 'profile' => ['stats' => $p['stats']], 'synced_at' => now(), 'status' => 'connected', 'last_error' => null]);
        };
        try {
            if ($a->expires_at && $a->expires_at->lt(now()->addDay())) {
                self::refresh($a);
                $a->refresh();
            }
            $attempt();
        } catch (\Throwable $e) {
            if (self::refresh($a)) {
                try {
                    $attempt();

                    return $a->fresh();
                } catch (\Throwable $e2) {
                    $e = $e2;
                }
            }
            $expired = str_contains($e->getMessage(), '401') || str_contains(strtolower($e->getMessage()), 'expired') || str_contains($e->getMessage(), '190');
            $a->update(['status' => $expired ? 'expired' : $a->status, 'last_error' => mb_substr($e->getMessage(), 0, 250)]);
        }

        return $a->fresh();
    }

    private static function ok(Response $res, string $what): array
    {
        if (! $res->successful()) {
            $msg = $res->json('error.message') ?? $res->json('error_description') ?? $res->json('error.code') ?? $res->json('message') ?? ('HTTP '.$res->status());

            throw new \RuntimeException($what.' gagal (HTTP '.$res->status().'): '.Str::limit(is_string($msg) ? $msg : json_encode($msg), 160));
        }

        return (array) $res->json();
    }
}
