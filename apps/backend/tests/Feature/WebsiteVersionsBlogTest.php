<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\WebsiteVersion;

/** B5 (audit 14 Nov 2026): riwayat versi website & blog publik. */
class WebsiteVersionsBlogTest extends KrafioTestCase
{
    public function test_versions_merge_autosaves_and_restore_as_draft(): void
    {
        $u = $this->user([], 'free', 1000000);
        $u->subscriptions()->update(['snapshot' => ['features' => ['website.builder'], 'quotas' => [], 'limits' => ['websites' => 3]]]);
        $this->actingUser($u->fresh());
        $id = $this->json_('POST', '/app/websites', ['name' => 'Toko Sari', 'business' => 'Kedai kopi rumahan di Bandung', 'subdomain' => 'toko-sari-v'])->assertCreated()->json('data.id');
        $content = fn (string $title) => ['content' => ['blocks' => [['id' => 'a', 'type' => 'hero', 'title' => $title, 'body' => 'x'], ['id' => 'b', 'type' => 'contact', 'title' => 'Kontak', 'body' => 'y']], 'seo' => ['title' => 'Toko Sari Bandung', 'description' => 'Kedai kopi rumahan di Bandung dengan biji kopi lokal dan suasana hangat.']]];
        $this->json_('PUT', "/app/websites/{$id}", $content('Versi A'))->assertOk();
        $this->json_('PUT', "/app/websites/{$id}", $content('Versi A2'))->assertOk(); // autosave beruntun → digabung
        $this->assertSame(1, WebsiteVersion::where('website_id', $id)->count());
        $this->travel(11)->minutes();
        $this->json_('PUT', "/app/websites/{$id}", $content('Versi B'))->assertOk();
        $list = $this->json_('GET', "/app/websites/{$id}/versions")->assertOk()->json('data');
        $this->assertSame(['Versi B', 'Versi A2'], array_column($list, 'title'));

        $this->json_('POST', "/app/websites/{$id}/versions/{$list[1]['id']}/restore")->assertOk()->assertJsonPath('data.content.blocks.0.title', 'Versi A2');
        $this->assertSame('restore', WebsiteVersion::where('website_id', $id)->latest('id')->value('kind'));

        $this->actingUser($this->user());
        $this->json_('GET', "/app/websites/{$id}/versions")->assertNotFound();
    }

    public function test_public_blog_lists_only_published_posts(): void
    {
        BlogPost::create(['slug' => 'tips-foto-produk', 'title_id' => 'Tips foto produk', 'title_en' => 'Product photo tips', 'body_id' => "## Cahaya\nGunakan cahaya jendela.\n- Tripod\n- Reflektor", 'status' => 'published', 'published_at' => now()->subDay()]);
        BlogPost::create(['slug' => 'draf', 'title_id' => 'Draf rahasia', 'body_id' => 'x', 'status' => 'draft']);
        BlogPost::create(['slug' => 'besok', 'title_id' => 'Terjadwal', 'body_id' => 'x', 'status' => 'published', 'published_at' => now()->addDay()]);
        $list = $this->json_('GET', '/public/blog')->assertOk()->json('data');
        $this->assertSame(['tips-foto-produk'], array_column($list, 'slug'));
        $this->assertStringNotContainsString('##', $list[0]['excerpt']);
        $this->json_('GET', '/public/blog?lang=en')->assertJsonPath('data.0.title', 'Product photo tips');
        $this->json_('GET', '/public/blog/tips-foto-produk')->assertOk()->assertJsonPath('data.title', 'Tips foto produk');
        $this->json_('GET', '/public/blog/draf')->assertNotFound();
        $this->json_('GET', '/public/blog/besok')->assertNotFound();
    }
}
