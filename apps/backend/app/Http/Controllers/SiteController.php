<?php

namespace App\Http\Controllers;

use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Website pengguna yang sudah dipublikasikan (PRD 28.4; temuan audit B1).
 * Disajikan dari <subdomain>.<KRAFIO_SITE_DOMAIN> atau domain kustom yang sudah terverifikasi — terpisah dari SPA Krafio.
 * HTML dirender di server (SEO): title, meta description, canonical, Open Graph, JSON-LD, plus robots.txt & sitemap.xml.
 * Hanya konten yang DIPUBLIKASIKAN (published_content) yang tampil; draft tidak pernah bocor.
 */
class SiteController extends Controller
{
    /** Subdomain yang tidak boleh dipakai website pengguna. */
    public const RESERVED = ['www', 'app', 'admin', 'api', 'mail', 'status', 'assets', 'sites', 'cdn'];

    public function home(Request $r, ?string $subdomain = null)
    {
        $w = $this->resolve($r, $subdomain);
        if (! $w) {
            return $this->notFound();
        }

        return response($this->render($w), 200, $this->headers('text/html; charset=utf-8'));
    }

    public function robots(Request $r, ?string $subdomain = null)
    {
        $w = $this->resolve($r, $subdomain);
        if (! $w) {
            return response("User-agent: *\nDisallow: /\n", 404, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        return response("User-agent: *\nAllow: /\n\nSitemap: ".$this->baseUrl($w)."/sitemap.xml\n", 200, $this->headers('text/plain; charset=utf-8'));
    }

    public function sitemap(Request $r, ?string $subdomain = null)
    {
        $w = $this->resolve($r, $subdomain);
        if (! $w) {
            return $this->notFound();
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .'<url><loc>'.e($this->baseUrl($w).'/').'</loc><lastmod>'.($w->published_at ?? $w->updated_at)->toAtomString().'</lastmod></url></urlset>'."\n";

        return response($xml, 200, $this->headers('application/xml; charset=utf-8'));
    }

    /** Subdomain dari rute, atau domain kustom terverifikasi dari header Host. */
    private function resolve(Request $r, ?string $subdomain): ?Website
    {
        $q = Website::query()->where('status', 'published')->whereNotNull('published_content');
        if ($subdomain !== null) {
            $sub = strtolower($subdomain);
            if (in_array($sub, self::RESERVED, true)) {
                return null;
            }
            $w = $q->where('subdomain', $sub)->first();
        } else {
            $host = strtolower(preg_replace('/:\d+$/', '', (string) $r->getHost()));
            $w = $host !== '' ? $q->where('custom_domain', $host)->where('domain_status', 'verified')->first() : null;
        }
        // Pemilik ditangguhkan/diblokir → website tidak ditampilkan
        if ($w && in_array($w->user?->status, ['suspended', 'banned', 'deactivated'], true)) {
            return null;
        }

        return $w;
    }

    private function baseUrl(Website $w): string
    {
        $host = $w->domain_status === 'verified' && $w->custom_domain ? $w->custom_domain : $w->subdomain.'.'.config('krafio.site_domain');

        return 'https://'.$host;
    }

    private function headers(string $type): array
    {
        return [
            'Content-Type' => $type,
            'Cache-Control' => 'public, max-age=300',
            'X-Frame-Options' => 'SAMEORIGIN',
            // HTML statis tanpa JavaScript; gambar dari https
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src https: data:; base-uri 'none'; form-action 'none'; frame-ancestors 'self'",
        ];
    }

    private function notFound()
    {
        $html = '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
            .'<title>Website tidak ditemukan</title><style>body{font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;color:#334155}</style></head>'
            .'<body><main style="text-align:center;padding:24px"><h1 style="font-size:20px">Website tidak ditemukan</h1><p>Alamat ini belum dipublikasikan atau sudah tidak aktif.</p></main></body></html>';

        return response($html, 404, $this->headers('text/html; charset=utf-8'));
    }

    /** Teks blok → paragraf; URL, email, dan nomor telepon jadi tautan. */
    private function paragraphs(string $body): string
    {
        $out = '';
        foreach (preg_split('/\n{2,}|\r?\n/', trim($body)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $t = e($line);
            $t = preg_replace('#\bhttps?://[^\s<]+#i', '<a href="$0" rel="nofollow noopener">$0</a>', $t);
            $t = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '<a href="mailto:$0">$0</a>', $t);
            $t = preg_replace_callback('/(?<![\w\/])(\+?62|0)8[0-9\- ]{7,13}\d/', function ($m) {
                $digits = preg_replace('/\D/', '', $m[0]);
                $wa = str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;

                return '<a href="https://wa.me/'.$wa.'" rel="nofollow noopener">'.$m[0].'</a>';
            }, $t);
            $out .= '<p>'.$t.'</p>';
        }

        return $out;
    }

    private function render(Website $w): string
    {
        $c = (array) $w->published_content;
        $blocks = array_values(array_filter((array) ($c['blocks'] ?? []), fn ($b) => trim((string) ($b['title'] ?? '')) !== '' || trim((string) ($b['body'] ?? '')) !== ''));
        $title = trim((string) ($c['seo']['title'] ?? '')) ?: $w->name;
        $desc = trim((string) ($c['seo']['description'] ?? '')) ?: Str::limit(strip_tags((string) ($blocks[0]['body'] ?? '')), 155);
        $url = $this->baseUrl($w).'/';
        $contact = collect($blocks)->firstWhere('type', 'contact');
        $sections = '';
        $nav = '';
        foreach ($blocks as $i => $b) {
            $type = preg_replace('/[^a-z]/', '', (string) ($b['type'] ?? 'section'));
            $anchor = $type.'-'.$i;
            $heading = e(trim((string) ($b['title'] ?? '')));
            $body = $this->paragraphs((string) ($b['body'] ?? ''));
            if ($i === 0 && $type === 'hero') {
                $cta = $contact ? '<a class="btn" href="#contact-'.array_search($contact, $blocks, true).'">Hubungi kami</a>' : '';
                $sections .= '<header class="hero"><div class="wrap"><h1>'.($heading ?: e($w->name)).'</h1>'.$body.$cta.'</div></header>';

                continue;
            }
            if ($heading !== '') {
                $nav .= '<a href="#'.$anchor.'">'.$heading.'</a>';
            }
            $sections .= '<section id="'.$anchor.'" class="sec sec-'.$type.'"><div class="wrap">'.($heading !== '' ? ($i === 0 ? '<h1>' : '<h2>').$heading.($i === 0 ? '</h1>' : '</h2>') : '').$body.'</div></section>';
        }
        $jsonLd = json_encode(['@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => $w->name, 'description' => $desc, 'url' => $url], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        $badge = ! empty(\App\Services\Entitlements::grants($w->user)['limits']['watermark']) || ($w->user?->activeSubscription()?->plan_code ?? 'free') === 'free'
            ? '<p class="made">Dibuat dengan <a href="https://krafio.id" rel="noopener">Krafio</a></p>' : '';

        return '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>'.e($title).'</title><meta name="description" content="'.e($desc).'"><link rel="canonical" href="'.e($url).'">'
            .'<meta property="og:type" content="website"><meta property="og:title" content="'.e($title).'"><meta property="og:description" content="'.e($desc).'"><meta property="og:url" content="'.e($url).'">'
            .'<meta name="twitter:card" content="summary"><script type="application/ld+json">'.$jsonLd.'</script>'
            .'<style>:root{--fg:#0f172a;--muted:#475569;--bg:#fff;--soft:#f1f5f9;--brand:#4f46e5}@media (prefers-color-scheme:dark){:root{--fg:#e2e8f0;--muted:#94a3b8;--bg:#0b1020;--soft:#141b33;--brand:#818cf8}}'
            .'*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:var(--fg);background:var(--bg);line-height:1.65}'
            .'.wrap{max-width:860px;margin:0 auto;padding:0 20px}.top{position:sticky;top:0;background:var(--bg);border-bottom:1px solid var(--soft);z-index:1}'
            .'.top .wrap{display:flex;gap:16px;align-items:center;justify-content:space-between;min-height:56px;flex-wrap:wrap}.brand{font-weight:700;color:var(--fg);text-decoration:none}'
            .'nav{display:flex;gap:14px;flex-wrap:wrap}nav a{color:var(--muted);text-decoration:none;font-size:14px}nav a:hover{color:var(--brand)}'
            .'.hero{padding:72px 0 56px;background:linear-gradient(135deg,var(--soft),var(--bg))}.hero h1{font-size:clamp(28px,5vw,44px);line-height:1.15;margin:0 0 12px}'
            .'.hero p{font-size:18px;color:var(--muted);max-width:640px}.btn{display:inline-block;margin-top:12px;background:var(--brand);color:#fff;padding:12px 20px;border-radius:10px;text-decoration:none;font-weight:600}'
            .'.sec{padding:48px 0;border-top:1px solid var(--soft)}.sec h2{font-size:26px;margin:0 0 12px}.sec p{color:var(--muted);margin:0 0 10px}'
            .'.sec-contact{background:var(--soft)}a{color:var(--brand)}footer{padding:28px 0;color:var(--muted);font-size:13px;border-top:1px solid var(--soft)}.made{margin:4px 0 0}</style>'
            .'</head><body>'
            .'<div class="top"><div class="wrap"><a class="brand" href="/">'.e($w->name).'</a><nav aria-label="Navigasi">'.$nav.'</nav></div></div>'
            .'<main>'.$sections.'</main>'
            .'<footer><div class="wrap">© '.date('Y').' '.e($w->name).$badge.'</div></footer>'
            .'</body></html>';
    }
}
