<?php

namespace App\Services\Seo;

use App\Services\SeoAuditor;

/**
 * "Ambil dari URL" untuk form analisis konten: judul SEO (<title>), meta description, slug, dan isi utama
 * (<article>/<main>, tanpa header/nav/footer) dari halaman publik. Pengambilan memakai proteksi SSRF SeoAuditor.
 */
class PageImporter
{
    /** @return array{seo_title: string, meta_description: string, slug: string, content: string} */
    public static function import(string $url): array
    {
        return self::parse(SeoAuditor::fetchHtml($url), $url);
    }

    private static function remove(\DOMXPath $xp, array $queries): void
    {
        foreach ($queries as $q) {
            foreach (iterator_to_array($xp->query($q) ?: []) as $n) {
                $n->parentNode?->removeChild($n);
            }
        }
    }

    /** @return array{seo_title: string, meta_description: string, slug: string, content: string} */
    public static function parse(string $html, string $url): array
    {
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NONET);
        libxml_clear_errors();
        $xp = new \DOMXPath($dom);

        $title = trim((string) $xp->evaluate('string(//title)'));
        $desc = trim((string) $xp->evaluate('string(//meta[translate(@name,"DESCRIPTION","description")="description"]/@content)'));
        self::remove($xp, ['//script', '//style', '//noscript', '//template', '//form', '//iframe', '//svg', '//nav', '//aside', '//*[@role="navigation"]', '//*[@role="complementary"]']);

        // Isi utama: kandidat <article>/<main>/[role=main] paling spesifik yang masih memuat ≥ 60% teks kandidat terbesar.
        // Artikel tunggal → <article> (bukan <main> yang ikut memuat komentar); daftar blog berisi banyak <article> pendek → <main>.
        $candidates = [];
        foreach (iterator_to_array($xp->query('//article | //main | //*[@role="main"]') ?: []) as $n) {
            $candidates[] = [$n, mb_strlen(trim((string) preg_replace('/\s+/u', ' ', $n->textContent)))];
        }
        $largest = $candidates ? max(array_column($candidates, 1)) : 0;
        $main = null;
        $mainLen = PHP_INT_MAX;
        foreach ($candidates as [$n, $len]) {
            if ($largest > 0 && $len >= 0.6 * $largest && $len < $mainLen) {
                $main = $n;
                $mainLen = $len;
            }
        }
        if (! $main) {
            // Tanpa penanda isi utama: pakai <body> tanpa header/footer situs
            self::remove($xp, ['//header', '//footer', '//*[@role="banner"]', '//*[@role="contentinfo"]']);
            $main = $xp->query('//body')->item(0);
        }
        $content = '';
        if ($main) {
            foreach ($main->childNodes as $c) {
                $content .= $dom->saveHTML($c);
            }
        }
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $slug = $path === '' ? '' : (string) basename($path);

        return [
            'seo_title' => html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'meta_description' => html_entity_decode($desc, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'slug' => preg_replace('/\.(?:html?|php|aspx?)$/i', '', $slug) ?? $slug,
            'content' => trim($content),
        ];
    }
}
