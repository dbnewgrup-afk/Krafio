<?php

namespace App\Services;

use App\Models\SeoRule;
use App\Support\ApiException;
use Illuminate\Support\Facades\Http;

/**
 * SEO Tools dengan Rules Engine Krafio (PRD 6.8, 29). Skor adalah "Skor evaluasi Krafio",
 * bukan skor mesin pencari (Design Spec §19.6).
 * Proteksi SSRF (PRD 53.8): hanya http/https, port standar, host harus resolve ke IP publik,
 * tanpa redirect otomatis, batas ukuran & waktu.
 */
class SeoAuditor
{
    public static function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            throw new ApiException('invalid_url', 'URL harus diawali http:// atau https://.', 422, [], ['url' => ['URL tidak valid.']]);
        }
        if (isset($parts['port']) && ! in_array((int) $parts['port'], [80, 443], true)) {
            throw new ApiException('invalid_url', 'Port tidak diizinkan.', 422, [], ['url' => ['Port tidak diizinkan.']]);
        }
        $host = $parts['host'];
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (! $ips) {
            throw new ApiException('invalid_url', 'Domain tidak dapat ditemukan.', 422, [], ['url' => ['Domain tidak dapat ditemukan.']]);
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new ApiException('blocked_url', 'Alamat internal tidak dapat diaudit.', 422, [], ['url' => ['Alamat internal tidak dapat diaudit.']]);
            }
        }
    }

    /** @return array{score:int, findings: array<int, array>} */
    public static function audit(string $url): array
    {
        self::assertPublicUrl($url);
        try {
            $res = Http::timeout(12)->withOptions(['allow_redirects' => false, 'stream' => false])
                ->withHeaders(['User-Agent' => 'KrafioSEOBot/1.0'])->get($url);
        } catch (\Throwable $e) {
            throw new ApiException('fetch_failed', 'Halaman tidak dapat diambil.', 422, [], ['url' => ['Halaman tidak dapat diambil.']]);
        }
        if ($res->redirect()) {
            throw new ApiException('redirected', 'Halaman dialihkan. Masukkan URL tujuan akhirnya.', 422, [], ['url' => ['Halaman dialihkan ke: '.$res->header('Location')]]);
        }
        $html = substr($res->body(), 0, 2_000_000);
        $rules = SeoRule::where('is_active', true)->get()->keyBy('code');
        $doc = new \DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        $xp = new \DOMXPath($doc);

        $title = trim($xp->evaluate('string(//title)'));
        $desc = trim($xp->evaluate('string(//meta[translate(@name,"DESCRIPTION","description")="description"]/@content)'));
        $h1 = $xp->query('//h1')->length;
        $imgs = $xp->query('//img');
        $noAlt = 0;
        foreach ($imgs as $img) {
            if (trim((string) $img->getAttribute('alt')) === '') {
                $noAlt++;
            }
        }
        $lang = trim($xp->evaluate('string(//html/@lang)'));
        $viewport = $xp->query('//meta[@name="viewport"]')->length > 0;
        $canonical = $xp->query('//link[@rel="canonical"]')->length > 0;
        $https = str_starts_with(strtolower($url), 'https://');
        $og = $xp->query('//meta[@property="og:title"]')->length > 0;

        $checks = [
            'title_present' => [$title !== '', 'Halaman tidak memiliki <title>.', 'Tambahkan judul unik 30–60 karakter yang memuat kata kunci utama.', '<head>'],
            'title_length' => [$title === '' || (mb_strlen($title) >= 30 && mb_strlen($title) <= 60), 'Panjang judul '.mb_strlen($title).' karakter.', 'Usahakan 30–60 karakter agar tidak terpotong di hasil pencarian.', '<title>'],
            'meta_description' => [$desc !== '', 'Meta description tidak ditemukan.', 'Tambahkan meta description 70–160 karakter yang menjelaskan isi halaman.', '<head>'],
            'single_h1' => [$h1 === 1, "Ditemukan {$h1} elemen <h1>.", 'Gunakan tepat satu <h1> per halaman sebagai judul utama.', '<body>'],
            'img_alt' => [$noAlt === 0, "{$noAlt} gambar tanpa teks alt.", 'Isi atribut alt yang deskriptif untuk setiap gambar bermakna.', '<img>'],
            'html_lang' => [$lang !== '', 'Atribut lang pada <html> kosong.', 'Tambahkan lang="id" atau bahasa yang sesuai.', '<html>'],
            'viewport' => [$viewport, 'Meta viewport tidak ditemukan.', 'Tambahkan <meta name="viewport" content="width=device-width, initial-scale=1">.', '<head>'],
            'canonical' => [$canonical, 'Tag canonical tidak ditemukan.', 'Tambahkan <link rel="canonical"> untuk mencegah konten duplikat.', '<head>'],
            'https' => [$https, 'Halaman tidak memakai HTTPS.', 'Aktifkan sertifikat TLS dan arahkan HTTP ke HTTPS.', 'URL'],
            'open_graph' => [$og, 'Tag Open Graph tidak ditemukan.', 'Tambahkan og:title, og:description, dan og:image untuk pratinjau berbagi.', '<head>'],
        ];

        $total = 0;
        $lost = 0;
        $findings = [];
        foreach ($checks as $code => [$ok, $msg, $rec, $loc]) {
            $rule = $rules[$code] ?? null;
            if (! $rule) {
                continue;
            }
            $total += $rule->weight;
            if (! $ok) {
                $lost += $rule->weight;
                $findings[] = ['rule' => $code, 'severity' => $rule->severity, 'location' => $loc, 'message' => $msg, 'recommendation' => $rec];
            }
        }
        $score = $total > 0 ? (int) round(100 * ($total - $lost) / $total) : 0;

        return ['score' => $score, 'findings' => $findings];
    }
}
