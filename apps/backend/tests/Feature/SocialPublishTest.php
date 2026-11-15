<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\Upload;
use App\Models\User;
use App\Services\Social\Publisher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/** Posting langsung ke API resmi platform (tanpa Postiz): validasi media, terbit sekarang, jadwal, kirim ulang, URL media bertanda tangan. */
class SocialPublishTest extends KrafioTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['krafio.social_poll_ms' => 1, 'krafio.frontend_url' => 'https://krafio.test']);
    }

    private function proUser(): User
    {
        $u = $this->user();
        $u->subscriptions()->update(['snapshot' => ['features' => ['social.integrations'], 'quotas' => [], 'limits' => ['social_accounts' => 10, 'storage_mb' => 100]]]);

        return $u->fresh();
    }

    private function account(User $u, string $platform, array $extra = []): SocialAccount
    {
        return SocialAccount::create(['user_id' => $u->id, 'platform' => $platform, 'provider' => 'direct', 'external_id' => 'ext-'.$platform, 'username' => 'kopisenja',
            'access_token' => 'tok-'.$platform, 'status' => 'connected', 'expires_at' => now()->addDays(30)] + $extra);
    }

    private function upload(User $u, string $mime = 'image/jpeg', int $bytes = 2048): Upload
    {
        $path = 'uploads/'.$u->id.'/'.uniqid().($mime === 'video/mp4' ? '.mp4' : '.jpg');
        Storage::disk('local')->put($path, str_repeat('x', $bytes));

        return Upload::create(['user_id' => $u->id, 'disk' => 'local', 'path' => $path, 'original_name' => basename($path), 'mime' => $mime, 'size' => $bytes]);
    }

    public function test_media_rules_are_validated_per_platform(): void
    {
        $u = $this->proUser();
        $this->account($u, 'youtube');
        $this->account($u, 'instagram');
        $this->account($u, 'x');
        $img = $this->upload($u);
        $this->actingUser($u);
        $post = fn (array $body) => $this->json_('POST', '/app/social/posts', $body + ['content' => 'Halo', 'type' => 'now']);
        $post(['platforms' => ['youtube'], 'upload_ids' => [$img->id]])->assertStatus(422)->assertJsonPath('code', 'media_not_supported');
        $post(['platforms' => ['instagram']])->assertStatus(422)->assertJsonPath('code', 'media_not_supported');
        $post(['platforms' => ['x'], 'upload_ids' => [$img->id]])->assertStatus(422);
        $this->json_('POST', '/app/social/posts', ['content' => str_repeat('a', 300), 'platforms' => ['x'], 'type' => 'now'])->assertStatus(422);
        // Media milik orang lain ditolak
        $foreign = $this->upload($this->proUser());
        $post(['platforms' => ['instagram'], 'upload_ids' => [$foreign->id]])->assertStatus(422);
        // Platform yang belum terhubung (dan tanpa Postiz) → pesan jelas
        $post(['platforms' => ['tiktok']])->assertStatus(422)->assertJsonPath('code', 'account_not_connected');
        $this->assertSame(0, SocialPost::count());
    }

    public function test_publish_now_to_instagram_and_facebook_records_per_platform_results(): void
    {
        $u = $this->proUser();
        $this->account($u, 'instagram');
        $this->account($u, 'facebook');
        $img = $this->upload($u);
        Http::fake([
            'graph.instagram.com/v21.0/ext-instagram/media_publish' => Http::response(['id' => 'ig-post-1']),
            'graph.instagram.com/v21.0/ext-instagram/media' => Http::response(['id' => 'container-1']),
            'graph.instagram.com/v21.0/container-1*' => Http::response(['status_code' => 'FINISHED']),
            'graph.instagram.com/v21.0/ig-post-1*' => Http::response(['permalink' => 'https://www.instagram.com/p/abc/']),
            'graph.facebook.com/v21.0/me/accounts*' => Http::response(['data' => [['id' => 'page-9', 'name' => 'Kopi Senja', 'access_token' => 'page-tok']]]),
            'graph.facebook.com/v21.0/page-9/photos' => Http::response(['id' => 'ph-1', 'post_id' => 'page-9_77']),
        ]);
        $this->actingUser($u);
        $id = $this->json_('POST', '/app/social/posts', ['content' => 'Promo kopi!', 'platforms' => ['instagram', 'facebook'], 'type' => 'now', 'upload_ids' => [$img->id]])->assertCreated()->json('data.id');

        $p = SocialPost::find($id);
        $this->assertSame(['sent', 'direct'], [$p->status, $p->provider]);
        $this->assertSame('https://www.instagram.com/p/abc/', $p->external['results']['instagram']['url']);
        $this->assertSame('page-9_77', $p->external['results']['facebook']['id']);
        Http::assertSent(function ($req) {
            if (! str_ends_with($req->url(), '/ext-instagram/media')) {
                return false;
            }
            // Instagram menarik file dari URL publik bertanda tangan di domain Krafio
            return $req['caption'] === 'Promo kopi!' && str_starts_with((string) $req['image_url'], 'https://krafio.test/api/social/media/') && str_contains((string) $req['image_url'], 'signature=');
        });
        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/page-9/photos') && str_contains($req->body(), 'page-tok'));
        $row = collect($this->json_('GET', '/app/social/posts')->json('data'))->firstWhere('id', $id);
        $this->assertTrue($row['results']['instagram']['ok']);
    }

    public function test_partial_failure_can_be_retried_without_reposting_successful_platforms(): void
    {
        $u = $this->proUser();
        $this->account($u, 'x');
        $this->account($u, 'linkedin');
        $xCalls = 0;
        $liFail = true;
        Http::fake([
            'api.x.com/2/tweets' => function () use (&$xCalls) {
                $xCalls++;

                return Http::response(['data' => ['id' => '555']], 201);
            },
            'api.linkedin.com/v2/ugcPosts' => function () use (&$liFail) {
                return $liFail ? Http::response(['message' => 'Token tidak valid'], 401) : Http::response(['id' => 'urn:li:share:1'], 201);
            },
        ]);
        $this->actingUser($u);
        $id = $this->json_('POST', '/app/social/posts', ['content' => 'Halo dunia', 'platforms' => ['x', 'linkedin'], 'type' => 'now'])->assertCreated()->json('data.id');
        $p = SocialPost::find($id);
        $this->assertSame('partial', $p->status);
        $this->assertStringContainsString('Token tidak valid', (string) $p->error);

        $liFail = false;
        $this->json_('POST', "/app/social/posts/{$id}/publish")->assertOk();
        $this->assertSame('sent', SocialPost::find($id)->status);
        $this->assertSame(1, $xCalls, 'X yang sudah terkirim tidak diposting dua kali');
        Http::assertSent(fn ($req) => str_contains($req->url(), 'ugcPosts') && $req['author'] === 'urn:li:person:ext-linkedin');
    }

    public function test_scheduled_post_runs_when_due_and_can_be_cancelled(): void
    {
        $u = $this->proUser();
        $this->account($u, 'youtube');
        $vid = $this->upload($u, 'video/mp4', 4096);
        Http::fake([
            'www.googleapis.com/upload/youtube/v3/videos*' => Http::response([], 200, ['Location' => 'https://upload.youtube.test/session-1']),
            'upload.youtube.test/*' => Http::response(['id' => 'yt123']),
        ]);
        $this->actingUser($u);
        $id = $this->json_('POST', '/app/social/posts', ['content' => "Resep kopi gula aren\nCara bikin di rumah", 'platforms' => ['youtube'], 'type' => 'schedule',
            'scheduled_at' => now()->addHour()->toIso8601String(), 'upload_ids' => [$vid->id]])->assertCreated()->json('data.id');
        $this->assertSame('scheduled', SocialPost::find($id)->status);
        $this->assertSame(0, Publisher::due(), 'Belum jatuh tempo');
        Http::assertNothingSent();

        $this->travel(61)->minutes();
        $this->assertSame(1, Publisher::due());
        $p = SocialPost::find($id);
        $this->assertSame(['sent', 'https://youtu.be/yt123'], [$p->status, $p->external['results']['youtube']['url']]);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'uploadType=resumable') && $req['snippet']['title'] === 'Resep kopi gula aren' && $req->header('X-Upload-Content-Length')[0] === '4096');

        // Batalkan jadwal lain
        $id2 = $this->json_('POST', '/app/social/posts', ['content' => 'Lain', 'platforms' => ['youtube'], 'type' => 'schedule', 'scheduled_at' => now()->addDay()->toIso8601String(), 'upload_ids' => [$vid->id]])->json('data.id');
        $this->json_('POST', "/app/social/posts/{$id2}/cancel")->assertNoContent();
        $this->assertNull(SocialPost::find($id2));
        // Posting orang lain tidak bisa disentuh
        $this->actingUser($this->proUser());
        $this->json_('POST', "/app/social/posts/{$id}/publish")->assertNotFound();
    }

    public function test_tiktok_video_uses_file_upload_with_allowed_privacy(): void
    {
        $u = $this->proUser();
        $this->account($u, 'tiktok');
        $vid = $this->upload($u, 'video/mp4', 3000);
        Http::fake([
            'open.tiktokapis.com/v2/post/publish/creator_info/query/' => Http::response(['data' => ['privacy_level_options' => ['SELF_ONLY']], 'error' => ['code' => 'ok']]),
            'open.tiktokapis.com/v2/post/publish/video/init/' => Http::response(['data' => ['publish_id' => 'v_pub_1', 'upload_url' => 'https://open-upload.tiktokapis.test/up/1'], 'error' => ['code' => 'ok']]),
            'open-upload.tiktokapis.test/*' => Http::response([], 201),
        ]);
        $this->actingUser($u);
        $id = $this->json_('POST', '/app/social/posts', ['content' => 'Video promo', 'platforms' => ['tiktok'], 'type' => 'now', 'upload_ids' => [$vid->id]])->assertCreated()->json('data.id');
        $this->assertSame('sent', SocialPost::find($id)->status);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'video/init') && $req['source_info']['video_size'] === 3000 && $req['source_info']['total_chunk_count'] === 1 && $req['post_info']['privacy_level'] === 'SELF_ONLY');
        Http::assertSent(fn ($req) => str_contains($req->url(), 'open-upload') && $req->method() === 'PUT' && $req->header('Content-Range')[0] === 'bytes 0-2999/3000');
    }

    public function test_media_url_requires_valid_signature(): void
    {
        $u = $this->proUser();
        $img = $this->upload($u);
        $this->get('/api/social/media/'.$img->id)->assertForbidden();
        $url = Publisher::publicUrl($img);
        $this->get(str_replace('https://krafio.test', '', $url))->assertOk();
        $this->get(str_replace('https://krafio.test', '', $url).'x')->assertForbidden();
    }
}
