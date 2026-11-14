<?php

namespace Tests\Feature;

use App\Models\Website;

/** Website pengguna di subdomain / domain kustom (PRD 28.4, audit B1). */
class SiteRendererTest extends KrafioTestCase
{
    private function site(array $attrs = []): Website
    {
        $u = $this->user();
        $content = ['blocks' => [
            ['id' => 'a', 'type' => 'hero', 'title' => 'Kopi Senja <script>alert(1)</script>', 'body' => 'Kopi susu gula aren khas Bandung.'],
            ['id' => 'b', 'type' => 'services', 'title' => 'Menu', 'body' => "Es kopi susu\nAmericano"],
            ['id' => 'c', 'type' => 'contact', 'title' => 'Hubungi kami', 'body' => "WA 0812-3456-7890\nhalo@kopisenja.id"],
        ], 'seo' => ['title' => 'Kopi Senja — Kopi Susu Bandung', 'description' => 'Kedai kopi susu gula aren di Bandung dengan biji kopi lokal pilihan dan harga ramah.']];

        return Website::create(array_merge(['user_id' => $u->id, 'name' => 'Kopi Senja', 'subdomain' => 'kopisenja', 'status' => 'published', 'content' => $content, 'published_content' => $content, 'published_at' => now()], $attrs));
    }

    public function test_published_site_renders_on_subdomain_with_seo_and_escaping(): void
    {
        $this->site();
        $r = $this->get('http://kopisenja.'.config('krafio.site_domain').'/')->assertOk();
        $html = $r->getContent();
        $this->assertStringContainsString('<title>Kopi Senja — Kopi Susu Bandung</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Kedai kopi susu gula aren', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://kopisenja.'.config('krafio.site_domain').'/">', $html);
        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html, 'Isi pengguna di-escape');
        $this->assertStringContainsString('https://wa.me/6281234567890', $html, 'Nomor HP jadi tautan WhatsApp');
        $this->assertStringContainsString('mailto:halo@kopisenja.id', $html);
        $this->assertStringContainsString("default-src 'none'", $r->headers->get('Content-Security-Policy'));
        $this->get('http://kopisenja.'.config('krafio.site_domain').'/robots.txt')->assertOk()->assertSee('Sitemap: https://kopisenja.'.config('krafio.site_domain').'/sitemap.xml', false);
        $this->get('http://kopisenja.'.config('krafio.site_domain').'/sitemap.xml')->assertOk()->assertSee('<loc>https://kopisenja.'.config('krafio.site_domain').'/</loc>', false);
    }

    public function test_drafts_reserved_names_and_suspended_owners_are_not_served(): void
    {
        $w = $this->site(['published_content' => ['blocks' => [['id' => 'a', 'type' => 'hero', 'title' => 'Versi terbit', 'body' => 'x']], 'seo' => []]]);
        $w->update(['content' => ['blocks' => [['id' => 'a', 'type' => 'hero', 'title' => 'DRAFT RAHASIA', 'body' => 'x']], 'seo' => []]]);
        $html = $this->get('http://kopisenja.'.config('krafio.site_domain').'/')->assertOk()->getContent();
        $this->assertStringContainsString('Versi terbit', $html);
        $this->assertStringNotContainsString('DRAFT RAHASIA', $html, 'Draft tidak pernah tampil');

        $this->get('http://belumada.'.config('krafio.site_domain').'/')->assertNotFound();
        $this->get('http://www.'.config('krafio.site_domain').'/')->assertNotFound();
        $w->update(['status' => 'draft']);
        $this->get('http://kopisenja.'.config('krafio.site_domain').'/')->assertNotFound();
        $w->update(['status' => 'published']);
        $w->user->forceFill(['status' => 'suspended'])->save();
        $this->get('http://kopisenja.'.config('krafio.site_domain').'/')->assertNotFound();
    }

    public function test_custom_domain_served_only_when_verified(): void
    {
        $w = $this->site(['custom_domain' => 'www.kopisenja.id', 'domain_status' => 'pending']);
        $this->get('http://www.kopisenja.id/')->assertNotFound();
        $w->update(['domain_status' => 'verified', 'domain_verified_at' => now()]);
        $html = $this->get('http://www.kopisenja.id/')->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="https://www.kopisenja.id/">', $html, 'Canonical memakai domain kustom');
        $this->get('http://www.kopisenja.id/sitemap.xml')->assertOk()->assertSee('https://www.kopisenja.id/', false);
        $this->get('http://lain.example.com/')->assertNotFound();
    }
}
