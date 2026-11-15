<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\User;
use App\Services\Integrations;
use App\Services\Settings;
use Illuminate\Support\Facades\Http;

/** Integrasi medsos: OAuth popup → callback REST → data profil; Postiz untuk koneksi & posting (PRD 17.6, 30). */
class SocialIntegrationTest extends KrafioTestCase
{
    private function proUser()
    {
        $u = $this->user();
        $u->subscriptions()->update(['snapshot' => ['features' => ['social.integrations'], 'quotas' => [], 'limits' => ['social_accounts' => 5]]]);

        return $u->fresh();
    }

    private function app(string $platform): void
    {
        $admin = User::where('role', 'admin')->first();
        Integrations::save('social_'.$platform, 'oauth', ['client_id' => 'cid-'.$platform, 'client_secret' => 'sec-'.$platform], true, $admin->id);
    }

    public function test_instagram_popup_oauth_flow_stores_encrypted_token_and_profile(): void
    {
        $this->app('instagram');
        $u = $this->proUser();
        $this->actingUser($u);
        $list = collect($this->json_('GET', '/app/social')->assertOk()->json('data.platforms'))->keyBy('platform');
        $this->assertSame('disconnected', $list['instagram']['status']);
        $this->assertSame('not_available', $list['tiktok']['status'], 'Platform tanpa aplikasi resmi & tanpa Postiz belum tersedia');

        $url = $this->json_('POST', '/app/social/instagram/connect')->assertOk()->json('data.authorize_url');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $this->assertStringStartsWith('https://www.instagram.com/oauth/authorize', $url);
        $this->assertSame('cid-instagram', $q['client_id']);
        $this->assertSame('http://localhost:5173/api/social/callback/instagram', $q['redirect_uri']);

        Http::fake([
            'api.instagram.com/oauth/access_token' => Http::response(['access_token' => 'short-tok', 'user_id' => 1784, 'permissions' => ['instagram_business_basic']]),
            'graph.instagram.com/access_token*' => Http::response(['access_token' => 'long-tok', 'token_type' => 'bearer', 'expires_in' => 5183944]),
            'graph.instagram.com/me*' => Http::response(['user_id' => '1784', 'username' => 'kopisenja', 'name' => 'Kopi Senja', 'profile_picture_url' => 'https://cdn.test/a.jpg', 'followers_count' => 1520, 'follows_count' => 80, 'media_count' => 42]),
        ]);
        // Popup dibuka tanpa sesi login — identitas dari state sekali pakai
        $this->app['auth']->forgetGuards();
        $res = $this->get('/api/social/callback/instagram?code=abc&state='.$q['state']);
        $res->assertOk()->assertSee('krafio:social', false)->assertSee('@kopisenja', false);
        $a = SocialAccount::where('user_id', $u->id)->where('platform', 'instagram')->firstOrFail();
        $this->assertSame(['kopisenja', 'long-tok', 1520], [$a->username, $a->access_token, $a->profile['stats']['followers']]);
        $this->assertNotSame('long-tok', \DB::table('social_accounts')->where('id', $a->id)->value('access_token'), 'Token tersimpan terenkripsi');
        $this->assertTrue($a->expires_at->gt(now()->addDays(50)), 'Token jangka panjang 60 hari');
        // State sekali pakai: callback ulang ditolak
        $this->get('/api/social/callback/instagram?code=abc&state='.$q['state'])->assertStatus(400);

        $this->actingUser($u);
        $ig = collect($this->json_('GET', '/app/social')->json('data.platforms'))->firstWhere('platform', 'instagram');
        $this->assertSame(['connected', 'kopisenja', 42], [$ig['status'], $ig['username'], $ig['stats']['posts']]);
        $this->assertArrayNotHasKey('access_token', $ig);
    }

    public function test_x_uses_pkce_and_denied_consent_is_reported(): void
    {
        $this->app('x');
        $u = $this->proUser();
        $this->actingUser($u);
        $url = $this->json_('POST', '/app/social/x/connect')->json('data.authorize_url');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame('S256', $q['code_challenge_method']);
        $this->get('/api/social/callback/x?error=access_denied&state='.$q['state'])->assertStatus(400)->assertSee('Izin ditolak', false);
        $this->assertSame(0, SocialAccount::count());

        $url = $this->json_('POST', '/app/social/x/connect')->json('data.authorize_url');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        Http::fake([
            'api.x.com/2/oauth2/token' => Http::response(['access_token' => 'xt', 'refresh_token' => 'xr', 'expires_in' => 7200, 'scope' => 'tweet.read users.read']),
            'api.x.com/2/users/me*' => Http::response(['data' => ['id' => '9', 'name' => 'Kopi', 'username' => 'kopi', 'public_metrics' => ['followers_count' => 7, 'following_count' => 1, 'tweet_count' => 3]]]),
        ]);
        $this->get('/api/social/callback/x?code=zz&state='.$q['state'])->assertOk();
        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth2/token') && str_contains($r->body(), 'code_verifier=') && $r->hasHeader('Authorization'));
        $this->assertSame('xr', SocialAccount::first()->refresh_token);
    }

    public function test_sync_refreshes_expired_token_and_marks_expired_when_refresh_fails(): void
    {
        $this->app('youtube');
        $u = $this->proUser();
        $a = SocialAccount::create(['user_id' => $u->id, 'platform' => 'youtube', 'provider' => 'direct', 'access_token' => 'old', 'refresh_token' => 'r1', 'status' => 'connected', 'expires_at' => now()->subHour()]);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'new', 'expires_in' => 3600]),
            'www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [['id' => 'UC1', 'snippet' => ['title' => 'Kopi TV', 'customUrl' => '@kopitv'], 'statistics' => ['subscriberCount' => '1200', 'videoCount' => '30', 'viewCount' => '99000']]]]),
        ]);
        $this->actingUser($u);
        $yt = collect($this->json_('POST', '/app/social/youtube/sync')->assertOk()->json('data.platforms'))->firstWhere('platform', 'youtube');
        $this->assertSame(['connected', '@kopitv', 1200], [$yt['status'], $yt['username'], $yt['stats']['subscribers']]);
        $this->assertSame('new', $a->fresh()->access_token);

    }

    public function test_sync_marks_expired_when_refresh_fails(): void
    {
        $this->app('youtube');
        $u = $this->proUser();
        $a = SocialAccount::create(['user_id' => $u->id, 'platform' => 'youtube', 'provider' => 'direct', 'access_token' => 'old', 'refresh_token' => 'r1', 'status' => 'connected', 'expires_at' => now()->subMinute()]);
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400), 'www.googleapis.com/*' => Http::response(['error' => ['message' => 'Invalid Credentials']], 401)]);
        $this->actingUser($u);
        $yt = collect($this->json_('POST', '/app/social/youtube/sync')->assertOk()->json('data.platforms'))->firstWhere('platform', 'youtube');
        $this->assertSame('expired', $a->fresh()->status);
        $this->assertSame('expired', $yt['status']);
        $this->assertNotEmpty($yt['last_error']);
    }

    public function test_postiz_connect_claims_channel_per_user_and_publishes(): void
    {
        $admin = User::where('role', 'admin')->first();
        Integrations::save('postiz', 'postiz', ['base_url' => 'https://postiz.test', 'api_key' => 'pz-key'], true, $admin->id);
        $u = $this->proUser();
        $this->actingUser($u);
        $tk = collect($this->json_('GET', '/app/social')->json('data.platforms'))->firstWhere('platform', 'tiktok');
        $this->assertSame(['disconnected', true], [$tk['status'], $tk['via']['postiz']]);

        Http::fake([
            'postiz.test/public/v1/integrations/connect' => Http::response(['url' => 'https://app.postiz.test/integrations/connect/tok123']),
            'postiz.test/public/v1/integrations' => Http::response([
                ['id' => 'int-other', 'name' => 'Milik orang lain', 'identifier' => 'tiktok', 'picture' => null, 'disabled' => false, 'profile' => 'other'],
                ['id' => 'int-new', 'name' => 'Kopi Senja', 'identifier' => 'tiktok', 'picture' => 'https://p.test/a.png', 'disabled' => false, 'profile' => 'kopisenja'],
            ]),
            'postiz.test/public/v1/posts' => Http::response([['postId' => 'p1', 'integration' => 'int-new']]),
        ]);
        $other = $this->user();
        SocialAccount::create(['user_id' => $other->id, 'platform' => 'tiktok', 'provider' => 'postiz', 'external_id' => 'int-other', 'status' => 'connected']);

        $r = $this->json_('POST', '/app/social/tiktok/connect')->assertOk()->json('data');
        $this->assertSame(['postiz', 'https://app.postiz.test/integrations/connect/tok123'], [$r['via'], $r['authorize_url']]);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'integrations/connect') && $req->header('Authorization')[0] === 'pz-key' && str_contains((string) $req['redirectUrl'], '/api/social/postiz-return?state='));
        $sent = collect(Http::recorded())->first(fn ($p) => str_contains($p[0]->url(), 'integrations/connect'))[0];
        parse_str((string) parse_url((string) $sent['redirectUrl'], PHP_URL_QUERY), $q);
        $this->get('/api/social/postiz-return?state='.$q['state'])->assertOk();
        $a = SocialAccount::where('user_id', $u->id)->where('platform', 'tiktok')->firstOrFail();
        $this->assertSame(['postiz', 'int-new', 'kopisenja'], [$a->provider, $a->external_id, $a->username], 'Kanal milik pengguna lain tidak diklaim');

        $this->actingUser($u);
        $this->json_('POST', '/app/social/posts', ['content' => 'Promo Jumat!', 'platforms' => ['tiktok'], 'type' => 'schedule', 'scheduled_at' => now()->addDay()->toIso8601String()])->assertCreated()->assertJsonPath('data.status', 'scheduled');
        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/public/v1/posts') && $req['type'] === 'schedule' && $req['posts'][0]['integration']['id'] === 'int-new' && $req['posts'][0]['settings']['__type'] === 'tiktok' && $req['posts'][0]['value'][0]['content'] === 'Promo Jumat!');
        $this->assertSame(1, SocialPost::count());
        $this->assertCount(1, $this->json_('GET', '/app/social/posts')->json('data'));
    }

    public function test_connect_requires_entitlement_and_configured_app(): void
    {
        $this->actingUser($this->user()); // paket Gratis: tanpa social.integrations
        $this->json_('POST', '/app/social/instagram/connect')->assertStatus(403);
        $this->actingUser($this->proUser());
        $this->json_('POST', '/app/social/instagram/connect')->assertStatus(422)->assertJsonPath('code', 'integration_not_available');
        $this->json_('POST', '/app/social/posts', ['content' => 'x', 'platforms' => ['instagram'], 'type' => 'now'])->assertStatus(422)->assertJsonPath('code', 'account_not_connected');
        $this->assertFalse((bool) Settings::get('social.instagram.enabled'));
    }
}
