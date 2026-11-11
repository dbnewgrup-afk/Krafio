<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Penyusun judul & deskripsi SEO berbasis aturan (tanpa AI) yang selalu lolos validasi publish
 * website: judul 10–60 karakter, deskripsi 50–160 karakter, dipotong di batas kata.
 */
class SeoWriter
{
    public const TITLE_MIN = 10;

    public const TITLE_MAX = 60;

    public const DESC_MIN = 50;

    public const DESC_MAX = 160;

    /** @param  string[]  $services */
    public static function title(string $name, array $services = [], string $business = ''): string
    {
        $name = self::clean($name);
        $lead = $services[0] ?? self::firstPhrase($business);
        $candidates = array_filter([
            $lead ? "{$name} — ".self::clean($lead) : null,
            $lead ? "{$name} | ".self::clean($lead) : null,
            "{$name} — Website Resmi",
            $name,
        ]);
        foreach ($candidates as $c) {
            $len = mb_strlen($c);
            if ($len >= self::TITLE_MIN && $len <= self::TITLE_MAX) {
                return $c;
            }
        }

        return self::fit(self::pad($name.' — Website Resmi', self::TITLE_MIN), self::TITLE_MAX);
    }

    /** @param  string[]  $services */
    public static function description(string $name, string $business, array $services = []): string
    {
        $name = self::clean($name);
        $base = self::clean($business);
        if ($base !== '' && ! Str::contains(mb_strtolower($base), mb_strtolower($name))) {
            $base = "{$name}: {$base}";
        }
        if ($base === '') {
            $base = "{$name} melayani pelanggan dengan sepenuh hati.";
        }
        $base = rtrim($base, '.').'.';
        if ($services && mb_strlen($base) < self::DESC_MAX - 20) {
            $base .= ' Layanan: '.implode(', ', array_slice(array_map([self::class, 'clean'], $services), 0, 4)).'.';
        }
        if (mb_strlen($base) < self::DESC_MIN) {
            $base .= ' Hubungi kami untuk info harga dan pemesanan.';
        }

        return self::fit(self::pad($base, self::DESC_MIN), self::DESC_MAX);
    }

    /** Lengkapi/perbaiki SEO pada konten website dari blok yang ada. */
    public static function fix(string $name, array $content): array
    {
        $blocks = collect($content['blocks'] ?? []);
        $business = (string) ($blocks->firstWhere('type', 'about')['body'] ?? $blocks->firstWhere('type', 'hero')['body'] ?? '');
        $servicesBody = (string) ($blocks->firstWhere('type', 'services')['body'] ?? '');
        $services = array_values(array_filter(array_map('trim', preg_split('/[\n,]/', $servicesBody)), fn ($s) => $s !== '' && mb_strlen($s) <= 60 && ! str_starts_with($s, 'Tuliskan layanan')));
        $seo = $content['seo'] ?? [];
        $t = mb_strlen((string) ($seo['title'] ?? ''));
        $d = mb_strlen((string) ($seo['description'] ?? ''));
        if ($t < self::TITLE_MIN || $t > self::TITLE_MAX) {
            $seo['title'] = self::title($name, $services, $business);
        }
        if ($d < self::DESC_MIN || $d > self::DESC_MAX) {
            $seo['description'] = self::description($name, $business, $services);
        }
        $content['seo'] = $seo;

        return $content;
    }

    private static function clean(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags($s)) ?? '');
    }

    private static function firstPhrase(string $s): string
    {
        $s = self::clean($s);
        $first = preg_split('/[.,;:\n]/u', $s)[0] ?? '';

        return mb_strlen($first) <= 40 ? $first : '';
    }

    /** Potong di batas kata tanpa melebihi $max. */
    private static function fit(string $s, int $max): string
    {
        if (mb_strlen($s) <= $max) {
            return $s;
        }
        $cut = mb_substr($s, 0, $max);
        $space = mb_strrpos($cut, ' ');
        $cut = $space !== false && $space > $max * 0.6 ? mb_substr($cut, 0, $space) : $cut;

        return rtrim($cut, ' ,;:—|-');
    }

    private static function pad(string $s, int $min): string
    {
        while (mb_strlen($s) < $min) {
            $s .= ' Krafio';
        }

        return $s;
    }
}
