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
    /** Batas unduhan halaman (byte). Unduhan dihentikan di tengah jalan bila lebih besar. */
    public const MAX_BYTES = 3_000_000;

    /**
     * Validasi URL publik dan kembalikan IP (IPv4) hasil resolve yang aman dipakai.
     *
     * @return array{host: string, port: int, ips: string[]}
     */
    public static function assertPublicUrl(string $url): array
    {
        $parts = parse_url($url);
        if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            throw new ApiException('invalid_url', 'URL harus diawali http:// atau https://.', 422, [], ['url' => ['URL tidak valid.']]);
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new ApiException('invalid_url', 'URL tidak boleh memuat nama pengguna atau kata sandi.', 422, [], ['url' => ['URL tidak valid.']]);
        }
        if (isset($parts['port']) && ! in_array((int) $parts['port'], [80, 443], true)) {
            throw new ApiException('invalid_url', 'Port tidak diizinkan.', 422, [], ['url' => ['Port tidak diizinkan.']]);
        }
        $host = strtolower($parts['host']);
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (! $ips) {
            throw new ApiException('invalid_url', 'Domain tidak dapat ditemukan.', 422, [], ['url' => ['Domain tidak dapat ditemukan.']]);
        }
        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                throw new ApiException('blocked_url', 'Alamat internal tidak dapat diaudit.', 422, [], ['url' => ['Alamat internal tidak dapat diaudit.']]);
            }
        }
        $port = (int) ($parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80));

        return ['host' => $host, 'port' => $port, 'ips' => $ips];
    }

    /** IPv4 publik saja: bukan privat, reserved, loopback, link-local, maupun CGNAT (100.64.0.0/10). */
    public static function isPublicIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        $n = ip2long($ip);

        return ! ($n >= ip2long('100.64.0.0') && $n <= ip2long('100.127.255.255'));
    }

    /**
     * Ambil HTML halaman publik dengan proteksi SSRF (dipakai audit URL & impor konten analisis SEO):
     * koneksi dipaksa ke IP yang sudah divalidasi (mencegah DNS rebinding), IPv4 saja, tanpa redirect,
     * maksimal 12 detik dan MAX_BYTES, serta hanya menerima respons HTML.
     */
    public static function fetchHtml(string $url): string
    {
        $target = self::assertPublicUrl($url);
        $options = ['allow_redirects' => false, 'stream' => false, 'force_ip_resolve' => 'v4',
            'progress' => function ($total, $downloaded) {
                if ($total > self::MAX_BYTES || $downloaded > self::MAX_BYTES) {
                    throw new \RuntimeException('response_too_large');
                }
            },
        ];
        if (! filter_var($target['host'], FILTER_VALIDATE_IP)) {
            $options['curl'] = [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$target['ips'][0]}"]];
        }
        try {
            $res = Http::timeout(12)->connectTimeout(5)->withOptions($options)
                ->withHeaders(['User-Agent' => 'KrafioSEOBot/1.0', 'Accept' => 'text/html,application/xhtml+xml'])->get($url);
        } catch (\Throwable $e) {
            $tooLarge = str_contains($e->getMessage().' '.($e->getPrevious()?->getMessage() ?? ''), 'response_too_large');
            throw new ApiException('fetch_failed', $tooLarge ? 'Halaman terlalu besar untuk diambil (maksimal 3 MB).' : 'Halaman tidak dapat diambil.', 422, [], ['url' => [$tooLarge ? 'Halaman terlalu besar.' : 'Halaman tidak dapat diambil.']]);
        }
        if ($res->redirect()) {
            throw new ApiException('redirected', 'Halaman dialihkan. Masukkan URL tujuan akhirnya.', 422, [], ['url' => ['Halaman dialihkan ke: '.$res->header('Location')]]);
        }
        if (! $res->successful()) {
            throw new ApiException('fetch_failed', "Halaman merespons HTTP {$res->status()}. Pastikan URL benar dan halaman dapat dibuka publik.", 422, [], ['url' => ["Halaman merespons HTTP {$res->status()}."]]);
        }
        $type = strtolower((string) $res->header('Content-Type'));
        if ($type !== '' && ! str_contains($type, 'html') && ! str_contains($type, 'xml')) {
            throw new ApiException('not_html', 'URL tersebut bukan halaman web (HTML).', 422, [], ['url' => ['URL bukan halaman HTML.']]);
        }

        return substr($res->body(), 0, self::MAX_BYTES);
    }

    /** @return array{score:int, title: ?string, findings: array<int, array>} */
    public static function audit(string $url): array
    {
        $html = self::fetchHtml($url);
        $rules = SeoRule::where('engine', 'audit')->where('is_active', true)->get()->keyBy('code');
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

        return ['score' => $score, 'title' => $title !== '' ? mb_substr(html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 0, 300) : null, 'findings' => $findings];
    }
}
