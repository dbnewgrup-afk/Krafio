<?php

namespace Tests\Feature;

use App\Models\SeoRule;
use App\Models\UsageCounter;
use App\Services\Seo\ContentAnalyzer;
use App\Services\Seo\IndonesianLexicon;
use App\Services\Seo\PageImporter;
use App\Services\Seo\TextDocument;
use App\Services\Seo\Topic;
use App\Services\SeoAuditor;
use App\Services\Settings;
use App\Support\ApiException;
use Illuminate\Support\Facades\Http;

/** Analisis konten SEO bergaya Yoast (docs/seo.md): form pengguna, rule yang diatur Admin, dan ambang Yoast. */
class SeoContentAnalysisTest extends KrafioTestCase
{
    private const ARTICLE = <<<'HTML'
<p>Jasa pembuatan website dari Krafio membantu UMKM tampil profesional di internet. Kami merancang situs yang cepat, rapi, dan mudah Anda kelola sendiri. Selain itu, setiap paket sudah mencakup domain, hosting, dan dukungan teknis selama satu tahun.</p>
<h2>Mengapa UMKM Butuh Jasa Pembuatan Website</h2>
<p>Pelanggan kini mencari produk lewat Google sebelum membeli. Tanpa situs resmi, bisnis Anda mudah kalah dari pesaing yang lebih siap. Oleh karena itu, kehadiran daring menjadi modal penting bagi usaha kecil.</p>
<p>Website juga bekerja selama 24 jam tanpa libur. Calon pembeli bisa membaca katalog, melihat harga, lalu menghubungi Anda lewat WhatsApp. Akibatnya, peluang penjualan tetap terbuka meskipun toko fisik sedang tutup.</p>
<p>Banyak pemilik usaha masih ragu karena takut biayanya mahal. Padahal, investasi awal ini sering kembali dalam beberapa bulan saja. Misalnya, sebuah toko kue di Bandung mendapat puluhan pesanan baru setelah situsnya tayang.</p>
<h2>Proses Kerja Tim Kami</h2>
<p>Pertama, kami mengadakan sesi konsultasi singkat untuk memahami tujuan bisnis Anda. Kedua, desainer menyusun rancangan tampilan sesuai identitas merek. Setelah itu, pengembang memasang fitur yang Anda perlukan, seperti formulir kontak dan galeri produk.</p>
<p>Anda bisa memberi masukan pada setiap tahap. Revisi tersedia hingga tiga kali tanpa biaya tambahan. Dengan begitu, hasil akhirnya benar-benar sesuai harapan.</p>
<p><img src="/img/tim-krafio.jpg" alt="Tim desainer sedang merancang tampilan toko daring"></p>
<h2>Biaya Jasa Pembuatan Website</h2>
<p>Harga paket mulai dari Rp1.500.000 untuk situs profil sederhana. Paket toko daring dengan sistem pembayaran tersedia mulai Rp4.000.000. Namun, semua harga sudah mencakup pelatihan singkat mengelola konten.</p>
<p>Kami tidak menarik biaya tersembunyi. Rincian harga tertulis jelas di proposal sehingga Anda bisa membandingkan penawaran dengan tenang. Lihat juga <a href="/layanan">daftar layanan lengkap</a> kami untuk pilihan lainnya.</p>
<h2>Tips Memilih Mitra Digital</h2>
<p>Periksa portofolio vendor sebelum menandatangani kontrak. Selain itu, pastikan mereka memberi akses penuh ke akun domain dan hosting. Menurut <a href="https://www.kominfo.go.id">data resmi Kominfo</a>, jumlah UMKM yang berjualan daring terus naik setiap tahun.</p>
<p>Terakhir, pilih tim yang responsif ketika Anda butuh bantuan. Jasa pembuatan website yang baik tidak berhenti saat situs selesai. Hubungi kami hari ini untuk konsultasi gratis tanpa komitmen. Tim kami siap menjawab pertanyaan Anda setiap hari kerja, mulai pagi hingga sore.</p>
<p>Ceritakan kebutuhan usaha Anda, kemudian kami kirimkan contoh tampilan awal dalam dua hari. Semakin cepat Anda memulai, semakin cepat pula pelanggan baru menemukan bisnis Anda di mesin pencari.</p>
HTML;

    private function form(array $over = []): array
    {
        return array_merge([
            'content_type' => 'post',
            'keyphrase' => 'jasa pembuatan website',
            'seo_title' => 'Jasa Pembuatan Website untuk UMKM, Siap dalam 7 Hari',
            'meta_description' => 'Jasa pembuatan website untuk UMKM: desain rapi, cepat, dan mudah Anda kelola. Gratis domain, hosting, dan revisi tiga kali. Konsultasi sekarang!',
            'slug' => 'jasa-pembuatan-website',
            'url' => 'https://contoh-umkm.id/jasa-pembuatan-website',
            'content' => self::ARTICLE,
        ], $over);
    }

    private function finding(array $audit, string $rule): ?array
    {
        return collect($audit['findings'])->firstWhere('rule', $rule);
    }

    public function test_well_optimized_article_gets_green_scores_and_consumes_quota(): void
    {
        $u = $this->user();
        $this->actingUser($u);
        $r = $this->json_('POST', '/app/seo/analyze', $this->form())->assertCreated();
        $a = $r->json('data');

        $this->assertSame('content', $a['kind']);
        $this->assertSame('good', $a['score_rating'], 'Skor SEO: '.$a['score'].' — '.json_encode(collect($a['findings'])->where('rating', '!=', 'good')->values()));
        $this->assertSame(90, $a['readability_score'], json_encode(collect($a['findings'])->where('category', 'readability')->where('rating', '!=', 'good')->values()));
        foreach (['yoast_keyphrase_in_introduction', 'yoast_keyphrase_in_seo_title', 'yoast_keyphrase_in_slug', 'yoast_meta_description_length',
            'yoast_keyphrase_in_meta_description', 'yoast_keyphrase_density', 'yoast_keyphrase_in_subheadings', 'yoast_internal_links',
            'yoast_outbound_links', 'yoast_images', 'yoast_text_length', 'yoast_single_h1', 'yoast_keyphrase_previously_used',
            'yoast_competing_links', 'yoast_seo_title_width', 'yoast_keyphrase_length'] as $rule) {
            $this->assertSame('good', $this->finding($a, $rule)['rating'] ?? null, $rule.': '.json_encode($this->finding($a, $rule)));
        }
        // Cek alt text tanpa masalah tidak ditampilkan (tidak ada lampu hijau, docs/seo.md 4.17–4.21)
        $this->assertNull($this->finding($a, 'yoast_alt_text_length'));
        // Rule tier advanced (padanan Premium) tidak berlaku untuk paket Gratis
        $this->assertNull($this->finding($a, 'yoast_keyphrase_distribution'));
        $this->assertSame(1, (int) UsageCounter::where('user_id', $u->id)->where('entitlement', 'seo.basic')->value('used'));

        // Daftar audit memuat analisis konten tanpa isi konten; detail memuat konten untuk "ubah & analisis ulang"
        $list = $this->json_('GET', '/app/seo/audits')->assertOk()->json('data');
        $this->assertSame('content', $list[0]['kind']);
        $this->assertArrayNotHasKey('content', $list[0]['input']);
        $this->json_('GET', '/app/seo/audits/'.$a['id'])->assertOk()->assertJsonPath('data.input.content', self::ARTICLE);
        $this->actingUser($this->user())->json_('GET', '/app/seo/audits/'.$a['id'])->assertNotFound();
    }

    public function test_weak_article_is_flagged_with_yoast_thresholds(): void
    {
        $this->actingUser($this->user());
        $a = $this->json_('POST', '/app/seo/analyze', $this->form([
            'seo_title' => 'Panduan lengkap untuk pemilik usaha kecil yang ingin punya website dan jasa pembuatan website murah',
            'meta_description' => 'Website murah.',
            'slug' => 'panduan',
            'content' => "Kami membantu bisnis Anda.\n\n<h1>Judul satu</h1><h1>Judul dua</h1><p>Paket dimulai hari ini. Paket dikirim cepat. Paket dibuat rapi.</p><img src=\"/a/IMG_1234.jpg\" alt=\"IMG_1234\"><img src=\"/a/b.jpg\" alt=\"Gambar kucing tidur di sofa\">",
        ]))->assertCreated()->json('data');

        $this->assertLessThanOrEqual(70, $a['score']);
        $this->assertNotSame('good', $a['score_rating']);
        $this->assertSame(6, $this->finding($a, 'yoast_keyphrase_in_seo_title')['score']); // ada, tetapi tidak di awal
        $this->assertSame(3, $this->finding($a, 'yoast_seo_title_width')['score']); // > 600 piksel
        $this->assertSame(6, $this->finding($a, 'yoast_meta_description_length')['score']); // terlalu pendek
        $this->assertSame(3, $this->finding($a, 'yoast_keyphrase_in_meta_description')['score']);
        $this->assertSame(6, $this->finding($a, 'yoast_keyphrase_in_slug')['score']);
        $this->assertSame(3, $this->finding($a, 'yoast_keyphrase_in_introduction')['score']);
        $this->assertSame(-20, $this->finding($a, 'yoast_text_length')['score']); // < 100 kata
        $this->assertSame(1, $this->finding($a, 'yoast_single_h1')['score']);
        $this->assertSame(3, $this->finding($a, 'yoast_alt_text_non_descriptive')['score']);
        $this->assertSame(6, $this->finding($a, 'yoast_alt_text_redundant_phrase')['score']);
        $this->assertSame(3, $this->finding($a, 'yoast_consecutive_sentences')['score']); // 3 kalimat diawali "Paket"
        $this->assertSame(3, $this->finding($a, 'yoast_passive_voice')['score']); // dikirim, dibuat
        $this->assertSame('critical', $this->finding($a, 'yoast_single_h1')['severity']);
        $this->assertSame('warning', $this->finding($a, 'yoast_keyphrase_in_slug')['severity']);
    }

    public function test_analyzer_rules_match_yoast_details(): void
    {
        $fw = IndonesianLexicon::functionWords();
        // Kata fungsi dibuang (docs/seo.md 3.2)
        $this->assertSame(['website'], (new Topic('cara membuat website', $fw))->contentWords);
        $this->assertSame(['jasa', 'pembuatan', 'website'], (new Topic('jasa pembuatan website terbaik', $fw))->contentWords);
        $this->assertTrue((new Topic('cara membuat', $fw))->onlyFunctionWords);
        $exact = new Topic('"jasa website"', $fw);
        $this->assertTrue($exact->exact);
        $this->assertSame(1, $exact->countIn(['jasa', 'website', 'murah']));
        $this->assertSame(0, $exact->countIn(['website', 'jasa']));
        // Per kata dalam satu kalimat, kemunculan berulang dihitung berulang
        $this->assertSame(2, (new Topic('desain logo', $fw))->countIn(['logo', 'desain', 'dan', 'desain', 'logo']));

        // Kalimat pasif bahasa Indonesia (docs/seo.md 5.5)
        $this->assertTrue(IndonesianLexicon::sentenceIsPassive(['pesanan', 'diproses', 'hari', 'ini']));
        $this->assertFalse(IndonesianLexicon::sentenceIsPassive(['diskon', 'digital', 'besar']));
        $this->assertFalse(IndonesianLexicon::sentenceIsPassive(['rumah', 'untuk', 'dijual']));
        $this->assertFalse(IndonesianLexicon::sentenceIsPassive(['tinggal', 'di', 'rumah']));

        // Alt text (docs/seo.md 4.19–4.20)
        $this->assertTrue(IndonesianLexicon::hasRedundantAltPhrase('Gambar kucing tidur'));
        $this->assertTrue(IndonesianLexicon::hasRedundantAltPhrase('Pada gambar ini terlihat dua orang'));
        $this->assertFalse(IndonesianLexicon::hasRedundantAltPhrase('Gambar latar biru muda'));
        $this->assertFalse(IndonesianLexicon::hasRedundantAltPhrase('Foto udara kota Bandung'));
        $this->assertTrue(IndonesianLexicon::isNonDescriptiveAlt('IMG_1234'));
        $this->assertTrue(IndonesianLexicon::isNonDescriptiveAlt('sepatu-merah.jpg'));
        $this->assertTrue(IndonesianLexicon::isNonDescriptiveAlt('sepatu merah', '/up/sepatu-merah-1024x576.jpg'));
        $this->assertTrue(IndonesianLexicon::isNonDescriptiveAlt('Product image'));
        $this->assertFalse(IndonesianLexicon::isNonDescriptiveAlt('Sepatu lari merah di atas aspal'));

        // Kalimat & kata: tanda hubung tidak memisah kata, singkatan tidak memutus kalimat
        $this->assertSame(['anak-anak', 'bermain'], TextDocument::words('Anak-anak bermain'));
        $this->assertCount(2, TextDocument::splitSentences('Dr. Budi membuka klinik. Pasien datang pagi.'));

        // Skor keterbacaan: Bahasa Indonesia = dukungan parsial (docs/seo.md 2.3)
        $r = fn (string ...$ratings) => array_map(fn ($x) => ['score' => ['good' => 9, 'ok' => 6, 'bad' => 3][$x], 'rating' => $x, 'message' => 'x'], $ratings);
        $this->assertSame(90, ContentAnalyzer::readabilityScore($r('good', 'ok', 'good')));
        $this->assertSame(60, ContentAnalyzer::readabilityScore($r('good', 'bad', 'good')));
        $this->assertSame(30, ContentAnalyzer::readabilityScore($r('bad', 'ok', 'good')));
        $this->assertSame(30, ContentAnalyzer::readabilityScore($r('ok', 'ok', 'ok')));
        $this->assertSame(60, ContentAnalyzer::readabilityScore($r('bad', 'ok', 'good'), true));
        // Skor SEO: rata-rata skor ÷ 9 × 100 (contoh docs/seo.md 2.2: 3, 6, 9 → 67)
        $this->assertSame(67, ContentAnalyzer::seoScore([['weight' => 5, 'score' => 3], ['weight' => 5, 'score' => 6], ['weight' => 5, 'score' => 9]]));
        $this->assertGreaterThan(600, ContentAnalyzer::pixelWidth('Panduan lengkap untuk pemilik usaha kecil yang ingin punya website murah'));
        $this->assertLessThan(600, ContentAnalyzer::pixelWidth('Jasa Pembuatan Website untuk UMKM, Siap dalam 7 Hari'));

        // Kepadatan: 1.000 kata, keyphrase 2 kata → disarankan 4–21 kali (tabel docs/seo.md 4.5)
        $body = implode(' ', array_fill(0, 99, 'Kalimat pengisi berisi sembilan kata yang netral dan aman.')).' Desain logo kami rapi.';
        $res = ContentAnalyzer::run(['content_type' => 'post', 'keyphrase' => 'desain logo', 'content' => $body]);
        $density = collect($res['results'])->firstWhere('code', 'yoast_keyphrase_density');
        $this->assertSame(4, $density['score']); // 1 kali → kurang dari minimum
        $this->assertStringContainsString('(4 kali)', $density['message']);
    }

    public function test_admin_controls_rules_params_and_setup(): void
    {
        $user = $this->user();
        $rule = SeoRule::where('code', 'yoast_text_length')->firstOrFail();
        $this->assertSame('content', $rule->engine);

        $this->admin();
        // Parameter ambang diubah Admin: minimal 300 → 100 kata
        $this->json_('PUT', '/admin/r/seo-rules/'.$rule->id, ['params' => ['good' => 100, 'ok' => 80, 'bad' => 60, 'far' => 40]])->assertOk();
        $this->json_('PUT', '/admin/r/seo-rules/'.$rule->id, ['params' => ['tidak_ada' => 1]])->assertStatus(422)->assertJsonPath('code', 'invalid_params');
        $this->json_('PUT', '/admin/r/seo-rules/'.$rule->id, ['code' => 'ganti_kode'])->assertStatus(422)->assertJsonPath('code', 'rule_code_locked');
        $this->json_('POST', '/admin/r/seo-rules', ['code' => 'yoast_rule_baru', 'name' => 'Baru', 'engine' => 'content', 'severity' => 'warning', 'weight' => 5, 'ruleset_version' => '2.0'])
            ->assertStatus(422)->assertJsonPath('code', 'unknown_rule');
        // Rule dinonaktifkan & severity merah diturunkan
        $h1 = SeoRule::where('code', 'yoast_single_h1')->firstOrFail();
        $this->json_('PUT', '/admin/r/seo-rules/'.$h1->id, ['severity' => 'warning'])->assertOk();
        $passive = SeoRule::where('code', 'yoast_passive_voice')->firstOrFail();
        $this->json_('PUT', '/admin/r/seo-rules/'.$passive->id, ['is_active' => false])->assertOk();

        $content = '<h1>A</h1><h1>B</h1><p>'.str_repeat('Paket desain kami dibuat rapi dan cepat untuk usaha Anda. ', 12).'</p>';
        $a = $this->actingUser($user)->json_('POST', '/app/seo/analyze', $this->form(['content' => $content]))->assertCreated()->json('data');
        $this->assertSame('good', $this->finding($a, 'yoast_text_length')['rating']); // 120 kata ≥ 100
        $this->assertSame('warning', $this->finding($a, 'yoast_single_h1')['severity']);
        $this->assertNull($this->finding($a, 'yoast_passive_voice'));

        // Setup: fitur dimatikan, impor URL dimatikan, jenis konten dibatasi
        $this->admin();
        $this->json_('PUT', '/admin/settings', ['values' => ['seo.content.content_types' => ['post'], 'seo.content.url_import' => false]])->assertOk();
        $cfg = $this->actingUser($user)->json_('GET', '/app/seo/config')->assertOk()->json('data');
        $this->assertSame(['post'], $cfg['content_types']);
        $this->assertFalse($cfg['url_import']);
        $this->assertFalse(collect($cfg['rules'])->firstWhere('code', 'yoast_keyphrase_distribution')['available']);
        $this->json_('POST', '/app/seo/analyze', $this->form(['content_type' => 'product']))->assertStatus(422);
        $this->json_('POST', '/app/seo/import', ['url' => 'https://contoh.id'])->assertStatus(403)->assertJsonPath('code', 'url_import_disabled');
        Settings::set('seo.content.enabled', false);
        $this->json_('POST', '/app/seo/analyze', $this->form())->assertStatus(403)->assertJsonPath('code', 'feature_disabled');
    }

    public function test_previously_used_keyphrase_quota_and_validation(): void
    {
        $u = $this->user();
        $this->actingUser($u);
        $this->json_('POST', '/app/seo/analyze', $this->form(['slug' => 'halaman-a']))->assertCreated();
        // Analisis ulang halaman yang sama tidak dihitung sebagai "pernah dipakai"
        $same = $this->json_('POST', '/app/seo/analyze', $this->form(['slug' => 'halaman-a']))->assertCreated()->json('data');
        $this->assertSame(9, $this->finding($same, 'yoast_keyphrase_previously_used')['score']);
        $other = $this->json_('POST', '/app/seo/analyze', $this->form(['slug' => 'halaman-b', 'keyphrase' => 'Jasa Pembuatan  Website']))->assertCreated()->json('data');
        $this->assertSame(6, $this->finding($other, 'yoast_keyphrase_previously_used')['score']);
        // Paket Gratis: 3 audit SEO per bulan → analisis ke-4 ditolak
        $this->json_('POST', '/app/seo/analyze', $this->form())->assertStatus(403)->assertJsonPath('code', 'quota_exceeded');

        $v = $this->user();
        $this->actingUser($v);
        $this->json_('POST', '/app/seo/analyze', $this->form(['keyphrase' => '']))->assertStatus(422)->assertJsonValidationErrors('keyphrase');
        $this->json_('POST', '/app/seo/analyze', $this->form(['content' => '']))->assertStatus(422)->assertJsonValidationErrors('content');
        $this->json_('POST', '/app/seo/import', ['url' => 'http://127.0.0.1/admin'])->assertStatus(422)->assertJsonPath('code', 'blocked_url');
        $this->assertSame(0, (int) UsageCounter::where('user_id', $v->id)->sum('used'));
    }

    public function test_page_importer_extracts_main_content(): void
    {
        $html = '<html><head><title>Jasa Desain Logo | Studio</title><meta name="description" content="Desain logo profesional untuk UMKM."></head>'
            .'<body><header><nav><a href="/">Beranda</a></nav></header><main><h1>Jasa Desain Logo</h1><p>Isi utama halaman.</p></main><footer>Hak cipta</footer></body></html>';
        $p = PageImporter::parse($html, 'https://studio.id/layanan/jasa-desain-logo.html');
        $this->assertSame('Jasa Desain Logo | Studio', $p['seo_title']);
        $this->assertSame('Desain logo profesional untuk UMKM.', $p['meta_description']);
        $this->assertSame('jasa-desain-logo', $p['slug']);
        $this->assertStringContainsString('Isi utama halaman.', $p['content']);
        $this->assertStringNotContainsString('Beranda', $p['content']);
        $this->assertStringNotContainsString('Hak cipta', $p['content']);
    }

    public function test_audit_fixes_parser_slug_synonyms_and_import(): void
    {
        // <br> tidak menempelkan kata; angka ribuan = satu kata
        $d = TextDocument::fromInput('<p>Baris satu<br>baris dua</p>');
        $this->assertSame('Baris satu baris dua', $d->blocks[0]['text']);
        $this->assertSame(['rp1.500.000', 'saja'], TextDocument::words('Rp1.500.000 saja'));
        // Dokumen HTML utuh: hanya isi utama yang dinilai (tanpa <title>, menu, footer)
        $d = TextDocument::fromInput('<html><head><title>Judul Tab</title></head><body><nav><a href="/">Beranda</a></nav><main><p>Isi utama artikel.</p></main><footer>Hak cipta</footer></body></html>');
        $this->assertSame(['Isi utama artikel.'], array_column($d->blocks, 'text'));
        $this->assertCount(0, $d->links);
        // Paragraf di dalam daftar Markdown bukan paragraf pembuka
        $r = ContentAnalyzer::run(['content_type' => 'post', 'keyphrase' => 'desain logo', 'content' => "- satu\n- dua\n\nDesain logo kami rapi."]);
        $this->assertSame(9, collect($r['results'])->firstWhere('code', 'yoast_keyphrase_in_introduction')['score']);
        // Kata keyphrase bertanda hubung cocok dengan slug
        $r = ContentAnalyzer::run(['content_type' => 'post', 'keyphrase' => 'toko e-commerce', 'slug' => 'toko-e-commerce', 'content' => '<p>Toko e-commerce kami lengkap.</p>']);
        $this->assertSame(9, collect($r['results'])->firstWhere('code', 'yoast_keyphrase_in_slug')['score']);
        // Sinonim yang tumpang tindih tidak dihitung ganda di alt text
        $r = ContentAnalyzer::run(['content_type' => 'post', 'keyphrase' => 'desain logo', 'synonyms' => 'logo', 'content' => '<p>x</p><img src="/a.jpg" alt="Desain logo modern untuk kafe kecil">']);
        $this->assertNull(collect($r['results'])->firstWhere('code', 'yoast_alt_text_keyphrase_stuffing'));
        // Importer memilih isi utama yang paling spesifik
        $p = PageImporter::parse('<body><main><article><header><h1>Judul</h1></header><p>'.str_repeat('Isi panjang artikel utama. ', 30).'</p></article><section><p>Komentar pembaca.</p></section></main></body>', 'https://x.id/blog/a');
        $this->assertStringContainsString('<h1>Judul</h1>', $p['content']);
        $this->assertStringNotContainsString('Komentar', $p['content']);
        $p = PageImporter::parse('<body><main><article><p>Teaser pendek.</p></article><article><p>'.str_repeat('Isi panjang artikel utama. ', 30).'</p></article></main></body>', 'https://x.id/blog/');
        $this->assertStringNotContainsString('Teaser', $p['content']);
    }

    public function test_audit_fixes_validation_admin_guards_and_lists(): void
    {
        $u = $this->user();
        $this->actingUser($u);
        $this->json_('POST', '/app/seo/analyze', $this->form(['keyphrase' => '???']))->assertStatus(422)->assertJsonValidationErrors('keyphrase');
        $this->json_('POST', '/app/seo/analyze', $this->form())->assertCreated();
        $this->json_('POST', '/app/seo/audits', ['url' => 'http://10.0.0.5/'])->assertStatus(422);
        // Riwayat per jenis; isi konten penuh tidak ikut di daftar
        $content = $this->json_('GET', '/app/seo/audits', ['kind' => 'content'])->assertOk()->json('data');
        $this->assertCount(1, $content);
        $this->assertArrayNotHasKey('content', $content[0]['input']);
        $this->assertSame('jasa pembuatan website', $content[0]['input']['keyphrase']);
        $this->assertCount(0, $this->json_('GET', '/app/seo/audits', ['kind' => 'url'])->assertOk()->json('data'));

        $this->admin();
        // Daftar Admin tidak memuat isi form/konten pengguna
        $rows = $this->json_('GET', '/admin/r/seo-audits')->assertOk()->json('data');
        $this->assertArrayNotHasKey('input', $rows[0]);
        // Urutan & batas ambang divalidasi
        $density = SeoRule::where('code', 'yoast_keyphrase_density')->firstOrFail();
        $this->json_('PUT', '/admin/r/seo-rules/'.$density->id, ['params' => ['min' => 5]])->assertStatus(422)->assertJsonPath('code', 'invalid_params');
        $passive = SeoRule::where('code', 'yoast_passive_voice')->firstOrFail();
        $this->json_('PUT', '/admin/r/seo-rules/'.$passive->id, ['params' => ['ok_max' => 150]])->assertStatus(422)->assertJsonPath('code', 'invalid_params');
        $this->json_('PUT', '/admin/r/seo-rules/'.$passive->id, ['params' => ['good_max' => 12, 'ok_max' => 20]])->assertOk();
        // Rule analisis konten tidak bisa dihapus; bulk delete campuran dibatalkan seluruhnya
        $this->json_('DELETE', '/admin/r/seo-rules/'.$passive->id)->assertStatus(422)->assertJsonPath('code', 'rule_locked');
        $audit = SeoRule::where('code', 'open_graph')->firstOrFail();
        $this->json_('POST', '/admin/r/seo-rules/bulk', ['ids' => [$audit->id, $passive->id], 'action' => 'delete', 'confirm' => 'HAPUS'])->assertStatus(422);
        $this->assertNotNull(SeoRule::find($audit->id));
        // Rule audit baru tanpa engine → otomatis "audit"
        $this->json_('POST', '/admin/r/seo-rules', ['code' => 'uji_rule', 'name' => 'Uji', 'severity' => 'info', 'weight' => 3, 'ruleset_version' => '2.0', 'params' => []])->assertCreated();
        $this->assertSame('audit', SeoRule::where('code', 'uji_rule')->value('engine'));
    }

    public function test_url_fetch_is_hardened(): void
    {
        foreach (['http://100.64.1.1/', 'http://169.254.169.254/latest/meta-data', 'http://[::1]/', 'http://0.0.0.0/'] as $bad) {
            try {
                SeoAuditor::assertPublicUrl($bad);
                $this->fail('Seharusnya diblokir: '.$bad);
            } catch (ApiException $e) {
                $this->assertContains($e->getMessage(), ['Alamat internal tidak dapat diaudit.', 'Domain tidak dapat ditemukan.']);
            }
        }
        try {
            SeoAuditor::assertPublicUrl('https://user:rahasia@93.184.216.34/');
            $this->fail('URL berkredensial harus ditolak');
        } catch (ApiException $e) {
            $this->assertStringContainsString('kata sandi', $e->getMessage());
        }

        Http::fake([
            '93.184.216.34/file.pdf' => Http::response('%PDF-1.7', 200, ['Content-Type' => 'application/pdf']),
            '93.184.216.34/hilang' => Http::response('<html><body>Not found</body></html>', 404, ['Content-Type' => 'text/html']),
            '93.184.216.34/toko-kopi' => Http::response('<html><head><title>Toko Kopi</title><meta name="description" content="Kopi lokal."></head><body><main><p>Isi.</p></main></body></html>', 200, ['Content-Type' => 'text/html; charset=utf-8']),
        ]);
        $this->actingUser($this->user());
        $this->json_('POST', '/app/seo/import', ['url' => 'http://93.184.216.34/file.pdf'])->assertStatus(422)->assertJsonPath('code', 'not_html');
        $this->json_('POST', '/app/seo/import', ['url' => 'http://93.184.216.34/hilang'])->assertStatus(422)->assertJsonPath('code', 'fetch_failed')
            ->assertJsonPath('message', 'Halaman merespons HTTP 404. Pastikan URL benar dan halaman dapat dibuka publik.');
        $this->json_('POST', '/app/seo/import', ['url' => 'http://93.184.216.34/toko-kopi'])->assertOk()
            ->assertJsonPath('data.seo_title', 'Toko Kopi')->assertJsonPath('data.slug', 'toko-kopi')->assertJsonPath('data.content', '<p>Isi.</p>');
    }
}
