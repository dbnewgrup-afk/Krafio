<?php

namespace App\Services\Seo;

use App\Models\SeoRule;
use App\Support\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * Definisi rule analisis konten (engine "content") — padanan cek Yoast SEO di docs/seo.md Bab 4–6.
 * Nilai default = ambang Yoast. Admin dapat mengubah status aktif, severity (untuk lampu merah),
 * bobot, parameter ambang, dan teks rekomendasi lewat Admin › SEO rules tanpa deploy.
 */
class ContentRules
{
    public const TYPES = ['post', 'cornerstone', 'taxonomy', 'product'];

    private const ALL = self::TYPES;

    private const NO_TAXONOMY = ['post', 'cornerstone', 'product'];

    private const NO_PRODUCT_NO_TAXONOMY = ['post', 'cornerstone'];

    /**
     * code => [category, tier, name, types, params default, recommendation]
     * category: seo | readability; tier: basic | advanced (butuh entitlement seo.advanced, padanan fitur Premium Yoast).
     */
    public const DEFINITIONS = [
        // ---------- SEO (docs/seo.md Bab 4) ----------
        'yoast_keyphrase_length' => ['seo', 'basic', 'Panjang keyphrase', self::ALL,
            ['good_max' => 4, 'ok_max' => 8, 'product_bad_max' => 2, 'product_ok_low_max' => 3, 'product_good_max' => 6, 'product_ok_max' => 8],
            'Pakai 1–4 kata konten (halaman produk 4–6). Kata fungsi seperti "cara", "untuk", "terbaik" tidak dihitung.'],
        'yoast_keyphrase_function_words' => ['seo', 'basic', 'Kata fungsi di keyphrase', self::ALL, [],
            'Pastikan keyphrase memuat minimal satu kata bermakna, bukan hanya kata fungsi.'],
        'yoast_keyphrase_previously_used' => ['seo', 'basic', 'Keyphrase pernah dipakai', self::ALL, [],
            'Satu keyphrase untuk satu halaman agar halaman Anda tidak saling bersaing di hasil pencarian.'],
        'yoast_keyphrase_in_introduction' => ['seo', 'basic', 'Keyphrase di paragraf pembuka', self::ALL, [],
            'Tulis keyphrase utuh dalam satu kalimat di paragraf pertama.'],
        'yoast_keyphrase_density' => ['seo', 'basic', 'Kepadatan keyphrase', self::ALL,
            ['min' => 0.5, 'max' => 3, 'over_max' => 4],
            'Capai batas bawah jumlah kemunculan lalu tambah secara alami. Jangan mendekati batas atas.'],
        'yoast_keyphrase_distribution' => ['seo', 'advanced', 'Sebaran keyphrase', self::NO_TAXONOMY,
            ['min_sentences' => 15, 'good_max' => 0.3, 'ok_max' => 0.5],
            'Munculkan keyphrase atau sinonimnya di pembuka, bagian tengah (mis. subjudul), dan penutup.'],
        'yoast_keyphrase_in_seo_title' => ['seo', 'basic', 'Keyphrase di judul SEO', self::ALL, [],
            'Awali judul SEO dengan keyphrase persis.'],
        'yoast_seo_title_width' => ['seo', 'basic', 'Lebar judul SEO', self::ALL, ['max_px' => 600],
            'Batasi judul SEO maksimal 600 piksel (sekitar 50–60 karakter) agar tidak terpotong di Google.'],
        'yoast_keyphrase_in_meta_description' => ['seo', 'basic', 'Keyphrase di meta description', self::ALL, ['max' => 2],
            'Muat keyphrase atau sinonimnya 1–2 kali di meta description.'],
        'yoast_meta_description_length' => ['seo', 'basic', 'Panjang meta description', self::ALL,
            ['short_max' => 120, 'max' => 156],
            'Tulis meta description 121–156 karakter dan akhiri dengan ajakan bertindak.'],
        'yoast_keyphrase_in_slug' => ['seo', 'basic', 'Keyphrase di slug', self::ALL, [],
            'Pakai slug pendek, huruf kecil, dipisah tanda hubung, dan memuat kata konten keyphrase.'],
        'yoast_keyphrase_in_subheadings' => ['seo', 'basic', 'Keyphrase di subjudul', self::NO_TAXONOMY,
            ['min_percent' => 30, 'max_percent' => 75, 'long_text_words' => 300, 'cornerstone_long_text_words' => 250],
            'Isi 30%–75% subjudul H2/H3 dengan keyphrase atau sinonimnya.'],
        'yoast_competing_links' => ['seo', 'basic', 'Tautan yang bersaing', self::NO_TAXONOMY, [],
            'Jangan menautkan ke halaman lain memakai keyphrase halaman ini sebagai anchor text.'],
        'yoast_internal_links' => ['seo', 'basic', 'Tautan internal', self::NO_PRODUCT_NO_TAXONOMY, [],
            'Tambahkan minimal satu tautan internal biasa (follow) ke halaman relevan di situs Anda.'],
        'yoast_outbound_links' => ['seo', 'basic', 'Tautan keluar', self::NO_PRODUCT_NO_TAXONOMY, [],
            'Tambahkan minimal satu tautan biasa (follow) ke sumber tepercaya.'],
        'yoast_images' => ['seo', 'basic', 'Gambar', self::NO_TAXONOMY, ['min' => 1, 'product_min' => 4],
            'Tambahkan gambar yang relevan (halaman produk minimal 4).'],
        'yoast_product_image_alt' => ['seo', 'basic', 'Atribut alt gambar produk', ['product'], [],
            'Isi atribut alt di setiap gambar produk.'],
        'yoast_alt_text_length' => ['seo', 'basic', 'Panjang alt text', self::NO_TAXONOMY, ['short_max' => 10, 'long_min' => 200],
            'Tulis alt text 11–199 karakter.'],
        'yoast_alt_text_duplicate' => ['seo', 'basic', 'Alt text duplikat', self::NO_TAXONOMY, [],
            'Beri setiap gambar alt text sendiri.'],
        'yoast_alt_text_redundant_phrase' => ['seo', 'basic', 'Frasa mubazir di alt text', self::NO_TAXONOMY, [],
            'Hapus awalan seperti "Gambar ..." atau "Foto ...", karena pembaca layar sudah menyebut bahwa itu gambar.'],
        'yoast_alt_text_non_descriptive' => ['seo', 'basic', 'Alt text tidak deskriptif', self::NO_TAXONOMY, [],
            'Jelaskan isi gambar, atau kosongkan alt bila gambar hanya dekorasi. Jangan memakai nama file.'],
        'yoast_alt_text_keyphrase_stuffing' => ['seo', 'basic', 'Keyphrase berlebihan di alt text', self::NO_TAXONOMY, ['ok_count' => 2],
            'Pakai keyphrase maksimal 1 kali per alt text, dan hanya bila relevan dengan gambar.'],
        'yoast_text_length' => ['seo', 'basic', 'Panjang teks', self::ALL,
            ['good' => 300, 'ok' => 250, 'bad' => 200, 'far' => 100,
                'cornerstone_good' => 900, 'cornerstone_ok' => 400, 'cornerstone_bad' => 300,
                'taxonomy_good' => 30, 'taxonomy_ok' => 10,
                'product_good' => 200, 'product_ok' => 150, 'product_bad' => 100, 'product_far' => 50],
            'Tambah isi yang bermanfaat hingga mencapai panjang yang disarankan.'],
        'yoast_single_h1' => ['seo', 'basic', 'Satu H1', self::ALL, [],
            'Pakai satu H1 saja sebagai judul utama. Ubah H1 lainnya menjadi H2 atau H3.'],

        // ---------- Keterbacaan (docs/seo.md Bab 5) ----------
        'yoast_subheading_distribution' => ['readability', 'basic', 'Sebaran subjudul', self::ALL,
            ['ok_max' => 300, 'bad_max' => 350, 'cornerstone_ok_max' => 250, 'cornerstone_bad_max' => 300],
            'Beri subjudul setiap 200–300 kata.'],
        'yoast_paragraph_length' => ['readability', 'basic', 'Panjang paragraf', self::ALL,
            ['good_max' => 150, 'ok_max' => 200, 'product_good_max' => 70, 'product_ok_max' => 100],
            'Pecah paragraf panjang menjadi beberapa paragraf pendek.'],
        'yoast_sentence_length' => ['readability', 'basic', 'Panjang kalimat', self::ALL,
            ['max_words' => 20, 'good_max_percent' => 25, 'ok_max_percent' => 30, 'strict_good_max_percent' => 20, 'strict_ok_max_percent' => 25],
            'Pecah kalimat yang lebih dari 20 kata.'],
        'yoast_consecutive_sentences' => ['readability', 'basic', 'Awal kalimat berulang', ['post', 'cornerstone', 'taxonomy'],
            ['max_run' => 2],
            'Variasikan kata pertama kalimat yang berurutan.'],
        'yoast_passive_voice' => ['readability', 'basic', 'Kalimat pasif', self::ALL, ['good_max' => 10, 'ok_max' => 15],
            'Ubah kalimat pasif (mis. "dibuat", "diproses") menjadi aktif.'],
        'yoast_transition_words' => ['readability', 'basic', 'Kata transisi', self::ALL,
            ['min_words' => 200, 'good_min' => 30, 'ok_min' => 20],
            'Pakai kata transisi seperti "selain itu", "namun", "oleh karena itu" di lebih banyak kalimat.'],
        'yoast_text_presence' => ['readability', 'basic', 'Keberadaan teks', self::ALL, ['min_chars' => 50],
            'Tambahkan konten agar analisis dapat berjalan.'],
        'yoast_lists' => ['readability', 'basic', 'Daftar (list)', ['product'], [],
            'Tambahkan minimal satu daftar berpoin atau bernomor, mis. spesifikasi produk.'],
        'yoast_text_alignment' => ['readability', 'advanced', 'Perataan teks', self::ALL, ['min_chars' => 50],
            'Ratakan kiri paragraf panjang. Teks rata tengah sulit dibaca.'],
    ];

    public const SEVERITIES = ['critical', 'warning', 'info'];

    /** Urutan ambang yang harus naik (kiri ≤ kanan) agar logika lampu tidak terbalik saat Admin mengubah parameter. */
    private const ASCENDING = [
        'yoast_keyphrase_length' => [['good_max', 'ok_max'], ['product_bad_max', 'product_ok_low_max', 'product_good_max', 'product_ok_max']],
        'yoast_keyphrase_density' => [['min', 'max', 'over_max']],
        'yoast_keyphrase_distribution' => [['good_max', 'ok_max']],
        'yoast_meta_description_length' => [['short_max', 'max']],
        'yoast_keyphrase_in_subheadings' => [['min_percent', 'max_percent']],
        'yoast_alt_text_length' => [['short_max', 'long_min']],
        'yoast_text_length' => [['far', 'bad', 'ok', 'good'], ['cornerstone_bad', 'cornerstone_ok', 'cornerstone_good'], ['taxonomy_ok', 'taxonomy_good'], ['product_far', 'product_bad', 'product_ok', 'product_good']],
        'yoast_subheading_distribution' => [['ok_max', 'bad_max'], ['cornerstone_ok_max', 'cornerstone_bad_max']],
        'yoast_paragraph_length' => [['good_max', 'ok_max'], ['product_good_max', 'product_ok_max']],
        'yoast_sentence_length' => [['good_max_percent', 'ok_max_percent'], ['strict_good_max_percent', 'strict_ok_max_percent']],
        'yoast_passive_voice' => [['good_max', 'ok_max']],
        'yoast_transition_words' => [['ok_min', 'good_min']],
    ];

    /** Parameter persentase/rasio tidak boleh melebihi batas logisnya. */
    private const MAXIMUM = [
        'yoast_keyphrase_distribution' => ['good_max' => 1, 'ok_max' => 1],
        'yoast_keyphrase_in_subheadings' => ['min_percent' => 100, 'max_percent' => 100],
        'yoast_sentence_length' => ['good_max_percent' => 100, 'ok_max_percent' => 100, 'strict_good_max_percent' => 100, 'strict_ok_max_percent' => 100],
        'yoast_passive_voice' => ['good_max' => 100, 'ok_max' => 100],
        'yoast_transition_words' => ['good_min' => 100, 'ok_min' => 100],
        'yoast_keyphrase_density' => ['min' => 100, 'max' => 100, 'over_max' => 100],
    ];

    /** Upsert definisi ke tabel seo_rules tanpa menimpa perubahan Admin. */
    public static function seed(): void
    {
        $now = now();
        $sort = 0;
        foreach (self::DEFINITIONS as $code => [$category, $tier, $name, , , $rec]) {
            $sort += 10;
            DB::table('seo_rules')->insertOrIgnore([
                'code' => $code, 'name' => $name, 'severity' => 'critical', 'weight' => 5, 'is_active' => true, 'ruleset_version' => '2.0',
                'engine' => 'content', 'category' => $category, 'tier' => $tier, 'params' => null, 'recommendation' => $rec,
                'description' => null, 'sort' => $sort, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    /** @return array<string, array{rule: ?SeoRule, params: array, recommendation: string, name: string, category: string, tier: string, types: string[]}> */
    public static function effective(): array
    {
        $rows = SeoRule::query()->where('engine', 'content')->get()->keyBy('code');
        $out = [];
        foreach (self::DEFINITIONS as $code => [$category, $tier, $name, $types, $params, $rec]) {
            $row = $rows[$code] ?? null;
            if ($row && ! $row->is_active) {
                continue;
            }
            $out[$code] = [
                'rule' => $row,
                'params' => self::mergeParams($params, is_array($row?->params) ? $row->params : []),
                'recommendation' => trim((string) ($row?->recommendation ?? '')) !== '' ? (string) $row->recommendation : $rec,
                'name' => $row?->name ?: $name,
                'category' => $category,
                'tier' => $row?->tier ?: $tier,
                'types' => $types,
            ];
        }

        return $out;
    }

    /**
     * Validasi isian Admin untuk rule engine "content": kode harus dikenal dan parameter hanya kunci default
     * dengan nilai angka ≥ 0. Dipanggil dari AdminResourceHooks.
     */
    public static function assertValid(array $data, ?SeoRule $existing = null): void
    {
        $engine = $data['engine'] ?? $existing?->engine ?? 'audit';
        if ($engine !== 'content') {
            return;
        }
        $code = $data['code'] ?? $existing?->code;
        if (! isset(self::DEFINITIONS[$code])) {
            throw new ApiException('unknown_rule', 'Kode rule analisis konten tidak dikenal. Gunakan salah satu kode bawaan (awalan yoast_).', 422, [], ['code' => ['Kode rule tidak dikenal.']]);
        }
        $params = $data['params'] ?? null;
        if ($params === null) {
            return;
        }
        $allowed = self::DEFINITIONS[$code][4];
        foreach ($params as $k => $v) {
            if (! array_key_exists($k, $allowed)) {
                throw new ApiException('invalid_params', "Parameter \"{$k}\" tidak dikenal untuk rule ini. Parameter yang tersedia: ".(implode(', ', array_keys($allowed)) ?: 'tidak ada').'.', 422, [], ['params' => ['Parameter tidak dikenal: '.$k]]);
            }
            if (! is_numeric($v) || $v < 0) {
                throw new ApiException('invalid_params', "Parameter \"{$k}\" harus berupa angka ≥ 0.", 422, [], ['params' => ["{$k} harus angka ≥ 0."]]);
            }
            $cap = self::MAXIMUM[$code][$k] ?? null;
            if ($cap !== null && $v > $cap) {
                throw new ApiException('invalid_params', "Parameter \"{$k}\" maksimal {$cap}.", 422, [], ['params' => ["{$k} maksimal {$cap}."]]);
            }
        }
        // Cek urutan memakai nilai efektif (default ← tersimpan ← isian baru)
        $effective = self::mergeParams(self::mergeParams($allowed, is_array($existing?->params) ? $existing->params : []), $params);
        foreach (self::ASCENDING[$code] ?? [] as $chain) {
            for ($i = 1; $i < count($chain); $i++) {
                if ($effective[$chain[$i - 1]] > $effective[$chain[$i]]) {
                    throw new ApiException('invalid_params', "Parameter \"{$chain[$i - 1]}\" ({$effective[$chain[$i - 1]]}) tidak boleh lebih besar dari \"{$chain[$i]}\" ({$effective[$chain[$i]]}).", 422, [], ['params' => ['Urutan ambang tidak valid: '.implode(' ≤ ', $chain).'.']]);
                }
            }
        }
    }

    /** @return array<string, float|int> */
    private static function mergeParams(array $defaults, array $override): array
    {
        foreach ($override as $k => $v) {
            if (array_key_exists($k, $defaults) && is_numeric($v)) {
                $defaults[$k] = $v + 0;
            }
        }

        return $defaults;
    }
}
