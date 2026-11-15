<?php

namespace App\Support;

use App\Services\Ai\ImageTools;
use App\Services\Ai\VideoTools;
use App\Services\AiGateway;
use App\Services\Settings;

/**
 * Registry AI Tools: satu sumber kebenaran untuk SEMUA fitur AI di panel user.
 *
 * Tiap tool mendefinisikan: jenis job, kapabilitas (text/image/video), entitlement, form (field dasar & lanjutan,
 * field bersyarat `show_if`), struktur hasil (bagian per part / kolom per item), dan prompt bawaan.
 * Admin mengubah perilaku tanpa deploy:
 *  - Settings `ai_tools.overrides` → aktif/nonaktif, label, deskripsi, biaya per unit, batas part/item, default, field disembunyikan.
 *  - Prompt Library `tool.<key>.system` & `tool.<key>.instruction` → isi prompt.
 *  - AI Providers/Models → penyedia & model (routing + fallback di AiGateway).
 *
 * Label ditulis "Indonesia|English"; dipilih sesuai bahasa pengguna saat diserialisasi.
 */
class AiTools
{
    /* ---------------- Opsi bersama ---------------- */

    private static function platforms(): array
    {
        return self::opts([
            'tiktok' => 'TikTok', 'instagram_reels' => 'Instagram Reels', 'instagram_feed' => 'Instagram Feed/Carousel', 'youtube_shorts' => 'YouTube Shorts',
            'youtube' => 'YouTube (video panjang)|YouTube (long video)', 'facebook' => 'Facebook', 'threads' => 'Threads', 'x' => 'X (Twitter)',
            'linkedin' => 'LinkedIn', 'whatsapp' => 'WhatsApp (status/broadcast)', 'shopee' => 'Shopee/Tokopedia', 'website' => 'Website/Blog',
        ]);
    }

    private static function tones(): array
    {
        return self::opts([
            'friendly' => 'Santai & ramah|Friendly', 'professional' => 'Profesional|Professional', 'playful' => 'Lucu & jenaka|Playful',
            'persuasive' => 'Persuasif (jualan)|Persuasive', 'inspirational' => 'Inspiratif|Inspirational', 'educational' => 'Edukatif|Educational',
            'emotional' => 'Emosional/menyentuh|Emotional', 'bold' => 'Berani & provokatif|Bold', 'luxury' => 'Mewah/eksklusif|Luxury',
        ]);
    }

    private static function languages(): array
    {
        return self::opts([
            'id' => 'Bahasa Indonesia baku|Formal Indonesian', 'id_casual' => 'Bahasa Indonesia gaul|Casual Indonesian', 'en' => 'English',
            'mix' => 'Campuran Indonesia–Inggris|Indonesian–English mix', 'jv' => 'Bahasa Jawa|Javanese', 'su' => 'Bahasa Sunda|Sundanese',
        ]);
    }

    private static function brandFields(): array
    {
        return [
            ['key' => 'brand', 'type' => 'text', 'label' => 'Nama brand/usaha|Brand name', 'max' => 80, 'group' => 'advanced', 'placeholder' => 'Kopi Senja|Kopi Senja'],
            ['key' => 'product', 'type' => 'textarea', 'label' => 'Produk/layanan & keunggulan|Product & key benefits', 'max' => 500, 'rows' => 2, 'group' => 'advanced', 'placeholder' => 'Kopi susu gula aren, pakai susu segar, harga Rp18rb|Palm sugar iced coffee, fresh milk, Rp18k'],
            ['key' => 'audience', 'type' => 'text', 'label' => 'Target audiens|Target audience', 'max' => 160, 'group' => 'advanced', 'placeholder' => 'Mahasiswa & pekerja muda 18–30 th|Students & young workers 18–30'],
            ['key' => 'keywords', 'type' => 'text', 'label' => 'Kata kunci wajib|Must-use keywords', 'max' => 200, 'group' => 'advanced', 'hint' => 'Pisahkan dengan koma.|Comma-separated.'],
            ['key' => 'avoid', 'type' => 'text', 'label' => 'Hal yang dihindari|Things to avoid', 'max' => 200, 'group' => 'advanced', 'placeholder' => 'Jangan sebut kompetitor, tanpa klaim medis|No competitor names, no medical claims'],
        ];
    }

    /* ---------------- Definisi tool ---------------- */

    public static function definitions(): array
    {
        $ratio = ['key' => 'ratio', 'type' => 'segmented', 'label' => 'Rasio|Aspect ratio', 'options' => self::opts(['1:1' => '1:1', '4:5' => '4:5', '9:16' => '9:16', '16:9' => '16:9']), 'default' => '1:1'];

        return [
            /* ================= TEKS ================= */
            'content_script' => [
                'type' => 'content_script', 'capability' => 'text', 'entitlement' => 'content.brainstorm', 'group' => 'write', 'icon' => 'clapperboard',
                'label' => 'Naskah Video & Konten|Video & Content Script', 'description' => 'Naskah siap rekam: hook, adegan, teks layar, voice over, CTA, caption — bisa dibuat bersambung per part.|Ready-to-shoot scripts with hook, scenes, on-screen text, voice-over, CTA and caption — single or multi-part series.',
                'mode' => 'parts', 'prompt_field' => 'topic', 'max_parts' => 10, 'cost_per_unit' => 2,
                'fields' => array_merge([
                    ['key' => 'topic', 'type' => 'textarea', 'label' => 'Tema / topik konten|Topic', 'required' => true, 'max' => 600, 'rows' => 3, 'placeholder' => 'Cara bikin kopi susu gula aren ala kafe di rumah|How to make café-style palm sugar coffee at home'],
                    ['key' => 'platform', 'type' => 'select', 'label' => 'Platform', 'options' => self::platforms(), 'default' => 'tiktok'],
                    ['key' => 'format', 'type' => 'select', 'label' => 'Format konten|Content format', 'default' => 'voice_over', 'options' => self::opts([
                        'talking_head' => 'Talking head (bicara ke kamera)|Talking head', 'voice_over' => 'Voice over + B-roll', 'skit' => 'Skit / drama pendek|Skit / short drama',
                        'tutorial' => 'Tutorial langkah demi langkah|Step-by-step tutorial', 'review' => 'Review / unboxing produk|Product review / unboxing', 'storytelling' => 'Storytelling',
                        'listicle' => 'Listicle (3–7 poin)|Listicle', 'pov' => 'POV / relatable', 'before_after' => 'Before–after', 'podcast' => 'Podcast / ngobrol|Podcast clip',
                    ])],
                    ['key' => 'goal', 'type' => 'select', 'label' => 'Tujuan|Goal', 'default' => 'engagement', 'options' => self::opts([
                        'awareness' => 'Dikenal (awareness)|Awareness', 'engagement' => 'Interaksi (like, komen, share)|Engagement', 'sales' => 'Penjualan/konversi|Sales',
                        'education' => 'Edukasi|Education', 'traffic' => 'Kunjungan ke link/toko|Traffic', 'followers' => 'Tambah followers|Grow followers',
                    ])],
                    ['key' => 'duration', 'type' => 'select', 'label' => 'Durasi per part|Duration per part', 'default' => '30', 'options' => self::opts(['15' => '15 detik|15 s', '30' => '30 detik|30 s', '60' => '60 detik|60 s', '90' => '90 detik|90 s', '180' => '3 menit|3 min', '600' => '10 menit|10 min'])],
                    ['key' => 'tone', 'type' => 'select', 'label' => 'Gaya bahasa|Tone', 'options' => self::tones(), 'default' => 'friendly'],
                    ['key' => 'series', 'type' => 'toggle', 'label' => 'Buat bersambung (beberapa part)|Make it a multi-part series', 'default' => false, 'hint' => 'Tiap part punya hook sendiri dan menyambung ke part berikutnya.|Each part has its own hook and leads into the next.'],
                    ['key' => 'parts', 'type' => 'number', 'label' => 'Jumlah part|Number of parts', 'min' => 2, 'max' => 10, 'default' => 3, 'show_if' => ['series' => true]],
                    ['key' => 'series_style', 'type' => 'select', 'label' => 'Pola series|Series pattern', 'show_if' => ['series' => true], 'default' => 'cliffhanger', 'options' => self::opts([
                        'cliffhanger' => 'Cliffhanger (bikin penasaran)|Cliffhanger', 'chapter' => 'Bab berurutan (materi bertahap)|Sequential chapters', 'episodic' => 'Episodik (bisa ditonton terpisah)|Episodic',
                    ])],
                    ['key' => 'language', 'type' => 'select', 'label' => 'Bahasa|Language', 'options' => self::languages(), 'default' => 'id_casual', 'group' => 'advanced'],
                    ['key' => 'hook_style', 'type' => 'select', 'label' => 'Gaya hook|Hook style', 'group' => 'advanced', 'default' => 'auto', 'options' => self::opts([
                        'auto' => 'Pilihkan otomatis|Auto', 'question' => 'Pertanyaan|Question', 'shocking' => 'Fakta mengejutkan|Shocking fact', 'pain' => 'Masalah audiens|Pain point',
                        'promise' => 'Janji hasil|Result promise', 'contrarian' => 'Opini berlawanan|Contrarian', 'story' => 'Awal cerita|Story opener',
                    ])],
                    ['key' => 'cta', 'type' => 'text', 'label' => 'Ajakan (CTA)|Call to action', 'max' => 160, 'group' => 'advanced', 'placeholder' => 'Klik link di bio / follow untuk part 2|Tap the link in bio / follow for part 2'],
                    ['key' => 'include', 'type' => 'multiselect', 'label' => 'Sertakan dalam naskah|Include', 'group' => 'advanced', 'default' => ['scenes', 'onscreen', 'caption', 'hashtags'], 'options' => self::opts([
                        'scenes' => 'Arahan adegan/visual|Scene directions', 'onscreen' => 'Teks di layar|On-screen text', 'caption' => 'Caption posting|Post caption',
                        'hashtags' => 'Hashtag', 'music' => 'Saran musik/sound|Music suggestion', 'thumbnail' => 'Ide thumbnail/cover|Thumbnail idea',
                    ])],
                ], self::brandFields()),
                'sections' => [
                    'hook' => ['Hook (3 detik pertama)|Hook (first 3 seconds)', 'kalimat pembuka yang menghentikan scroll'],
                    'script' => ['Naskah / voice over|Script / voice-over', 'naskah lengkap yang diucapkan, per baris, sesuai durasi'],
                    'scenes' => ['Arahan adegan|Scene directions', 'maksimal 6 adegan bernomor; tiap adegan satu baris: visual, angle kamera, durasi', 'scenes'],
                    'onscreen' => ['Teks di layar|On-screen text', 'maksimal 6 teks singkat yang muncul di layar', 'onscreen'],
                    'cta' => ['Penutup & CTA|Closing & CTA', 'kalimat penutup + ajakan bertindak'],
                    'caption' => ['Caption', 'caption posting siap salin', 'caption'],
                    'hashtags' => ['Hashtag', '8–15 hashtag relevan dipisah spasi', 'hashtags'],
                    'music' => ['Saran musik|Music', 'jenis musik/sound yang cocok', 'music'],
                    'thumbnail' => ['Ide thumbnail|Thumbnail idea', 'teks & visual cover', 'thumbnail'],
                ],
                'system' => 'Anda penulis naskah konten media sosial profesional untuk UMKM dan kreator Indonesia. Tulis naskah yang natural diucapkan, ritmenya cepat, sesuai durasi, dan langsung bisa direkam. Ikuti brief dengan tepat.',
                'instruction' => 'Tulis naskah {{format}} untuk {{platform}} tentang: {{topic}}. Tujuan: {{goal}}. Durasi sekitar {{duration}} detik. Gaya bahasa: {{tone}}. Bahasa: {{language}}.',
            ],

            'content_caption' => [
                'type' => 'content_caption', 'capability' => 'text', 'entitlement' => 'content.brainstorm', 'group' => 'write', 'icon' => 'message-square-text',
                'label' => 'Caption & Hashtag', 'description' => 'Beberapa variasi caption siap posting lengkap dengan hashtag dan CTA.|Multiple ready-to-post caption variations with hashtags and CTA.',
                'mode' => 'parts', 'parts_field' => 'variations', 'prompt_field' => 'topic', 'max_parts' => 5, 'cost_per_unit' => 1,
                'fields' => array_merge([
                    ['key' => 'topic', 'type' => 'textarea', 'label' => 'Isi postingan / produk|What is the post about', 'required' => true, 'max' => 600, 'rows' => 3, 'placeholder' => 'Promo kopi susu beli 2 gratis 1 khusus Jumat|Buy 2 get 1 iced coffee every Friday'],
                    ['key' => 'platform', 'type' => 'select', 'label' => 'Platform', 'options' => self::platforms(), 'default' => 'instagram_feed'],
                    ['key' => 'goal', 'type' => 'select', 'label' => 'Tujuan|Goal', 'default' => 'sales', 'options' => self::opts(['sales' => 'Jualan|Sell', 'engagement' => 'Interaksi|Engagement', 'education' => 'Edukasi|Educate', 'announcement' => 'Pengumuman|Announcement', 'awareness' => 'Awareness'])],
                    ['key' => 'tone', 'type' => 'select', 'label' => 'Gaya bahasa|Tone', 'options' => self::tones(), 'default' => 'friendly'],
                    ['key' => 'length', 'type' => 'segmented', 'label' => 'Panjang|Length', 'default' => 'medium', 'options' => self::opts(['short' => 'Pendek|Short', 'medium' => 'Sedang|Medium', 'long' => 'Panjang (storytelling)|Long'])],
                    ['key' => 'variations', 'type' => 'number', 'label' => 'Jumlah variasi|Variations', 'min' => 1, 'max' => 5, 'default' => 3],
                    ['key' => 'emoji', 'type' => 'segmented', 'label' => 'Emoji', 'group' => 'advanced', 'default' => 'some', 'options' => self::opts(['none' => 'Tanpa|None', 'some' => 'Secukupnya|Some', 'many' => 'Banyak|Lots'])],
                    ['key' => 'hashtag_count', 'type' => 'number', 'label' => 'Jumlah hashtag|Hashtag count', 'min' => 0, 'max' => 30, 'default' => 10, 'group' => 'advanced'],
                    ['key' => 'cta', 'type' => 'text', 'label' => 'Ajakan (CTA)|Call to action', 'max' => 160, 'group' => 'advanced'],
                    ['key' => 'language', 'type' => 'select', 'label' => 'Bahasa|Language', 'options' => self::languages(), 'default' => 'id_casual', 'group' => 'advanced'],
                ], self::brandFields()),
                'sections' => [
                    'caption' => ['Caption', 'caption lengkap siap salin'],
                    'hashtags' => ['Hashtag', 'hashtag sesuai jumlah diminta, dipisah spasi'],
                    'first_comment' => ['Komentar pertama|First comment', 'komentar pin untuk memancing interaksi'],
                ],
                'system' => 'Anda copywriter media sosial untuk UMKM Indonesia. Caption harus enak dibaca, punya kalimat pembuka kuat, dan jelas ajakannya.',
                'instruction' => 'Tulis caption {{platform}} tentang: {{topic}}. Tujuan: {{goal}}. Gaya: {{tone}}. Panjang: {{length}}. Emoji: {{emoji}}. Jumlah hashtag: {{hashtag_count}}. Bahasa: {{language}}. Setiap variasi harus berbeda sudut pandangnya.',
            ],

            'content_brainstorm' => [
                'type' => 'content_brainstorm', 'capability' => 'text', 'entitlement' => 'content.brainstorm', 'group' => 'write', 'icon' => 'lightbulb',
                'label' => 'Ide Konten|Content Ideas', 'description' => 'Daftar ide konten lengkap dengan hook, format, dan alasan kenapa ide itu bekerja.|Content ideas with hook, format and why it works.',
                'mode' => 'list', 'count_field' => 'ideas', 'batch' => 6, 'prompt_field' => 'topic', 'max_parts' => 30, 'cost_per_unit' => 1,
                'fields' => array_merge([
                    ['key' => 'topic', 'type' => 'textarea', 'label' => 'Niche / topik usaha|Niche / business topic', 'required' => true, 'max' => 500, 'rows' => 2, 'placeholder' => 'Kedai kopi kekinian di Bandung|Trendy coffee shop in Bandung'],
                    ['key' => 'platform', 'type' => 'select', 'label' => 'Platform', 'options' => self::platforms(), 'default' => 'tiktok'],
                    ['key' => 'pillar', 'type' => 'multiselect', 'label' => 'Pilar konten|Content pillars', 'default' => ['educate', 'entertain', 'promote'], 'options' => self::opts([
                        'educate' => 'Edukasi|Educate', 'entertain' => 'Hiburan|Entertain', 'inspire' => 'Inspirasi|Inspire', 'promote' => 'Promosi|Promote',
                        'behind' => 'Behind the scene', 'ugc' => 'Testimoni/UGC|Testimonials/UGC', 'trend' => 'Ikut tren|Trends',
                    ])],
                    ['key' => 'ideas', 'type' => 'number', 'label' => 'Jumlah ide|Number of ideas', 'min' => 3, 'max' => 30, 'default' => 8],
                    ['key' => 'tone', 'type' => 'select', 'label' => 'Gaya bahasa|Tone', 'options' => self::tones(), 'default' => 'friendly'],
                    ['key' => 'language', 'type' => 'select', 'label' => 'Bahasa|Language', 'options' => self::languages(), 'default' => 'id_casual', 'group' => 'advanced'],
                ], self::brandFields()),
                'item_fields' => [
                    'title' => ['Judul ide|Idea', 'judul ide singkat'],
                    'hook' => ['Hook', 'kalimat pembuka'],
                    'format' => ['Format', 'format video/posting'],
                    'pillar' => ['Pilar|Pillar', 'pilar konten'],
                    'why' => ['Kenapa berhasil|Why it works', 'alasan singkat'],
                ],
                'system' => 'Anda strategist konten media sosial untuk UMKM Indonesia. Ide harus spesifik, bisa langsung dieksekusi dengan HP, dan tidak klise.',
                'instruction' => 'Buat ide konten {{platform}} untuk: {{topic}}. Pilar: {{pillar}}. Gaya: {{tone}}. Bahasa: {{language}}.',
            ],

            'content_plan' => [
                'type' => 'content_plan', 'capability' => 'text', 'entitlement' => 'content.brainstorm', 'group' => 'write', 'icon' => 'calendar-days',
                'label' => 'Kalender Konten|Content Calendar', 'description' => 'Rencana posting harian lengkap dengan jam, format, judul, dan CTA.|Day-by-day posting plan with time, format, title and CTA.',
                'mode' => 'list', 'count_field' => 'days', 'batch' => 7, 'prompt_field' => 'topic', 'max_parts' => 30, 'cost_per_unit' => 1,
                'fields' => array_merge([
                    ['key' => 'topic', 'type' => 'textarea', 'label' => 'Usaha & fokus bulan ini|Business & focus', 'required' => true, 'max' => 500, 'rows' => 2, 'placeholder' => 'Toko hijab online, fokus launching koleksi Ramadan|Online hijab store, launching Ramadan collection'],
                    ['key' => 'days', 'type' => 'select', 'label' => 'Periode|Period', 'default' => '7', 'options' => self::opts(['7' => '7 hari|7 days', '14' => '14 hari|14 days', '30' => '30 hari|30 days'])],
                    ['key' => 'platforms', 'type' => 'multiselect', 'label' => 'Platform', 'default' => ['instagram_reels', 'tiktok'], 'options' => self::platforms()],
                    ['key' => 'goal', 'type' => 'select', 'label' => 'Tujuan utama|Main goal', 'default' => 'sales', 'options' => self::opts(['sales' => 'Penjualan|Sales', 'awareness' => 'Awareness', 'engagement' => 'Interaksi|Engagement', 'launch' => 'Peluncuran produk|Product launch'])],
                    ['key' => 'tone', 'type' => 'select', 'label' => 'Gaya bahasa|Tone', 'options' => self::tones(), 'default' => 'friendly', 'group' => 'advanced'],
                    ['key' => 'language', 'type' => 'select', 'label' => 'Bahasa|Language', 'options' => self::languages(), 'default' => 'id_casual', 'group' => 'advanced'],
                ], self::brandFields()),
                'item_fields' => [
                    'day' => ['Hari|Day', 'hari ke-'],
                    'time' => ['Jam posting|Time', 'jam posting terbaik (WIB)'],
                    'platform' => ['Platform', 'platform'],
                    'format' => ['Format', 'reels/carousel/story/dll'],
                    'title' => ['Judul konten|Title', 'judul/topik konten'],
                    'cta' => ['CTA', 'ajakan bertindak'],
                ],
                'system' => 'Anda social media planner untuk UMKM Indonesia. Susun rencana yang realistis, variatif, dan mengarah ke tujuan.',
                'instruction' => 'Susun kalender konten {{days}} hari untuk: {{topic}}. Platform: {{platforms}}. Tujuan: {{goal}}. Gaya: {{tone}}. Bahasa: {{language}}.',
            ],

            'copywriting' => [
                'type' => 'copywriting', 'capability' => 'text', 'entitlement' => 'content.brainstorm', 'group' => 'write', 'icon' => 'megaphone',
                'label' => 'Copywriting Iklan & Produk|Ad & Product Copywriting', 'description' => 'Copy iklan, deskripsi marketplace, landing page, broadcast WA, dan email dengan framework AIDA/PAS.|Ads, marketplace descriptions, landing pages, WhatsApp broadcasts and emails using AIDA/PAS.',
                'mode' => 'parts', 'parts_field' => 'variations', 'prompt_field' => 'product', 'max_parts' => 5, 'cost_per_unit' => 1,
                'fields' => [
                    ['key' => 'kind', 'type' => 'select', 'label' => 'Jenis copy|Copy type', 'default' => 'meta_ads', 'options' => self::opts([
                        'meta_ads' => 'Iklan Facebook/Instagram|Meta ads', 'google_ads' => 'Iklan Google (headline + deskripsi)|Google ads', 'tiktok_ads' => 'Iklan TikTok|TikTok ads',
                        'marketplace' => 'Deskripsi produk marketplace|Marketplace description', 'landing' => 'Landing page', 'wa_broadcast' => 'Broadcast WhatsApp|WhatsApp broadcast',
                        'email' => 'Email promosi|Promo email', 'banner' => 'Teks banner/poster|Banner text',
                    ])],
                    ['key' => 'product', 'type' => 'textarea', 'label' => 'Produk & keunggulan|Product & benefits', 'required' => true, 'max' => 800, 'rows' => 3, 'placeholder' => 'Serum vitamin C 20ml, BPOM, mencerahkan dalam 14 hari, Rp89rb|Vitamin C serum 20ml, brightens in 14 days, Rp89k'],
                    ['key' => 'offer', 'type' => 'text', 'label' => 'Promo / penawaran|Offer', 'max' => 200, 'placeholder' => 'Diskon 30% sampai 31 Des + gratis ongkir|30% off until Dec 31 + free shipping'],
                    ['key' => 'framework', 'type' => 'select', 'label' => 'Framework', 'default' => 'aida', 'options' => self::opts(['aida' => 'AIDA', 'pas' => 'PAS (Problem–Agitate–Solve)', 'fab' => 'FAB (Feature–Advantage–Benefit)', 'bab' => 'BAB (Before–After–Bridge)', 'free' => 'Bebas|Free-form'])],
                    ['key' => 'tone', 'type' => 'select', 'label' => 'Gaya bahasa|Tone', 'options' => self::tones(), 'default' => 'persuasive'],
                    ['key' => 'variations', 'type' => 'number', 'label' => 'Jumlah variasi|Variations', 'min' => 1, 'max' => 5, 'default' => 2],
                    ['key' => 'audience', 'type' => 'text', 'label' => 'Target audiens|Target audience', 'max' => 160, 'group' => 'advanced'],
                    ['key' => 'brand', 'type' => 'text', 'label' => 'Nama brand|Brand name', 'max' => 80, 'group' => 'advanced'],
                    ['key' => 'cta', 'type' => 'text', 'label' => 'Ajakan (CTA)|Call to action', 'max' => 160, 'group' => 'advanced'],
                    ['key' => 'language', 'type' => 'select', 'label' => 'Bahasa|Language', 'options' => self::languages(), 'default' => 'id', 'group' => 'advanced'],
                    ['key' => 'avoid', 'type' => 'text', 'label' => 'Hal yang dihindari|Things to avoid', 'max' => 200, 'group' => 'advanced', 'placeholder' => 'Tanpa klaim berlebihan/medis|No exaggerated or medical claims'],
                ],
                'sections' => [
                    'headline' => ['Headline', 'judul utama yang menarik perhatian'],
                    'body' => ['Isi copy|Body', 'isi copy sesuai framework'],
                    'cta' => ['CTA', 'ajakan bertindak'],
                    'short' => ['Versi pendek|Short version', 'versi ringkas maksimal 125 karakter'],
                ],
                'system' => 'Anda copywriter direct-response berpengalaman untuk pasar Indonesia. Patuhi aturan iklan: tanpa klaim palsu, medis, atau jaminan hasil yang menyesatkan.',
                'instruction' => 'Tulis copy {{kind}} untuk produk: {{product}}. Penawaran: {{offer}}. Framework: {{framework}}. Gaya: {{tone}}. Bahasa: {{language}}. Tiap variasi memakai sudut berbeda.',
            ],

            'article_writer' => [
                'type' => 'article_writer', 'capability' => 'text', 'entitlement' => 'content.brainstorm', 'group' => 'write', 'icon' => 'file-text',
                'label' => 'Artikel Blog SEO|SEO Blog Article', 'description' => 'Artikel blog terstruktur: judul SEO, meta description, outline, isi, FAQ.|Structured blog article with SEO title, meta description, outline, body and FAQ.',
                'mode' => 'parts', 'prompt_field' => 'topic', 'max_parts' => 1, 'cost_per_unit' => 3,
                'fields' => [
                    ['key' => 'topic', 'type' => 'textarea', 'label' => 'Judul / topik artikel|Article topic', 'required' => true, 'max' => 300, 'rows' => 2, 'placeholder' => '7 cara menyimpan biji kopi agar tetap segar|7 ways to keep coffee beans fresh'],
                    ['key' => 'keyword', 'type' => 'text', 'label' => 'Keyphrase utama|Focus keyphrase', 'max' => 80, 'placeholder' => 'menyimpan biji kopi|storing coffee beans'],
                    ['key' => 'length', 'type' => 'segmented', 'label' => 'Panjang|Length', 'default' => '800', 'options' => self::opts(['500' => '±500 kata|±500 words', '800' => '±800 kata|±800 words', '1200' => '±1.200 kata|±1,200 words'])],
                    ['key' => 'tone', 'type' => 'select', 'label' => 'Gaya bahasa|Tone', 'options' => self::tones(), 'default' => 'educational'],
                    ['key' => 'audience', 'type' => 'text', 'label' => 'Pembaca|Readers', 'max' => 160, 'group' => 'advanced'],
                    ['key' => 'brand', 'type' => 'text', 'label' => 'Nama brand (untuk CTA)|Brand name (for CTA)', 'max' => 80, 'group' => 'advanced'],
                    ['key' => 'faq', 'type' => 'toggle', 'label' => 'Tambahkan FAQ|Add FAQ section', 'default' => true, 'group' => 'advanced'],
                    ['key' => 'language', 'type' => 'select', 'label' => 'Bahasa|Language', 'options' => self::languages(), 'default' => 'id', 'group' => 'advanced'],
                ],
                'sections' => [
                    'seo_title' => ['Judul SEO|SEO title', 'maksimal 60 karakter, memuat keyphrase'],
                    'meta' => ['Meta description', '120–156 karakter, memuat keyphrase'],
                    'slug' => ['Slug', 'slug URL huruf kecil dengan tanda hubung'],
                    'body' => ['Isi artikel|Article body', 'artikel lengkap dengan subjudul (## ), paragraf pendek, keyphrase di paragraf pertama'],
                    'faq' => ['FAQ', '3–5 tanya jawab', 'faq'],
                ],
                'system' => 'Anda penulis konten SEO berbahasa Indonesia. Tulis artikel orisinal, akurat, mudah dibaca (paragraf pendek, subjudul jelas), dan tidak berlebihan memakai kata kunci.',
                'instruction' => 'Tulis artikel tentang: {{topic}}. Keyphrase: {{keyword}}. Panjang sekitar {{length}} kata. Gaya: {{tone}}. Bahasa: {{language}}.',
            ],

            'story_series' => [
                'type' => 'story_series', 'capability' => 'text', 'entitlement' => 'content.brainstorm', 'group' => 'write', 'icon' => 'book-open',
                'label' => 'Cerita Bersambung|Serialized Story', 'description' => 'Cerita per bab/part untuk konten storytelling, thread, atau novel pendek.|Chapter-by-chapter stories for storytelling content, threads or short novels.',
                'mode' => 'parts', 'prompt_field' => 'premise', 'max_parts' => 10, 'cost_per_unit' => 2,
                'fields' => [
                    ['key' => 'premise', 'type' => 'textarea', 'label' => 'Premis cerita|Story premise', 'required' => true, 'max' => 800, 'rows' => 3, 'placeholder' => 'Barista baru yang tiap malam menerima pesanan misterius dari meja nomor 7|A new barista receives mysterious orders from table 7 every night'],
                    ['key' => 'genre', 'type' => 'select', 'label' => 'Genre', 'default' => 'drama', 'options' => self::opts(['drama' => 'Drama', 'romance' => 'Romansa|Romance', 'comedy' => 'Komedi|Comedy', 'horror' => 'Horor|Horror', 'mystery' => 'Misteri|Mystery', 'motivational' => 'Motivasi|Motivational', 'slice' => 'Slice of life'])],
                    ['key' => 'parts', 'type' => 'number', 'label' => 'Jumlah part/bab|Parts / chapters', 'min' => 1, 'max' => 10, 'default' => 3],
                    ['key' => 'length', 'type' => 'segmented', 'label' => 'Panjang per part|Length per part', 'default' => '300', 'options' => self::opts(['150' => '±150 kata|±150 words', '300' => '±300 kata|±300 words', '600' => '±600 kata|±600 words'])],
                    ['key' => 'pov', 'type' => 'segmented', 'label' => 'Sudut pandang|Point of view', 'default' => 'first', 'group' => 'advanced', 'options' => self::opts(['first' => 'Orang pertama|First person', 'third' => 'Orang ketiga|Third person'])],
                    ['key' => 'characters', 'type' => 'textarea', 'label' => 'Tokoh|Characters', 'max' => 500, 'rows' => 2, 'group' => 'advanced', 'placeholder' => 'Raka (25, barista pendiam), Bu Sari (pemilik kedai)|Raka (25, quiet barista), Mrs Sari (owner)'],
                    ['key' => 'platform', 'type' => 'select', 'label' => 'Untuk platform|For platform', 'options' => self::platforms(), 'default' => 'threads', 'group' => 'advanced'],
                    ['key' => 'language', 'type' => 'select', 'label' => 'Bahasa|Language', 'options' => self::languages(), 'default' => 'id_casual', 'group' => 'advanced'],
                ],
                'sections' => [
                    'story' => ['Cerita|Story', 'isi part cerita sesuai panjang'],
                    'teaser' => ['Teaser part berikutnya|Next-part teaser', 'satu kalimat penasaran untuk part berikutnya'],
                ],
                'system' => 'Anda penulis cerita bersambung untuk media sosial. Cerita harus mengalir, punya konflik jelas, dan tiap part berakhir dengan rasa penasaran. Jaga konsistensi tokoh dan alur antar part.',
                'instruction' => 'Tulis cerita bergenre {{genre}} dengan premis: {{premise}}. Sudut pandang: {{pov}}. Panjang sekitar {{length}} kata per part. Bahasa: {{language}}.',
            ],

            // Repo 07 prompt-optimizer (AGPL → konsep diterapkan ulang): optimasi & variasi prompt
            'prompt_optimizer' => [
                'type' => 'prompt_optimizer', 'capability' => 'text', 'entitlement' => 'content.brainstorm', 'group' => 'write', 'icon' => 'sparkles',
                'label' => 'Prompt Optimizer', 'description' => 'Ubah ide singkat jadi prompt yang jelas & terstruktur untuk foto, video, konten, atau chatbot — lengkap dengan alasan perubahan.|Turn a rough idea into a clear, structured prompt for photos, video, content or chatbots — with the reasons for each change.',
                'mode' => 'parts', 'parts_field' => 'variations', 'prompt_field' => 'prompt', 'max_parts' => 3, 'cost_per_unit' => 1,
                'fields' => [
                    ['key' => 'prompt', 'type' => 'textarea', 'label' => 'Prompt / ide awal|Original prompt', 'required' => true, 'max' => 2000, 'rows' => 4, 'placeholder' => 'foto kopi buat promo|coffee photo for a promo'],
                    ['key' => 'target', 'type' => 'select', 'label' => 'Prompt untuk|Prompt for', 'default' => 'image', 'options' => self::opts([
                        'image' => 'Generate foto (AI gambar)|Image generation', 'video' => 'Generate video (AI video)|Video generation', 'content' => 'Menulis konten (AI teks)|Content writing',
                        'chatbot' => 'Instruksi chatbot/asisten|Chatbot system prompt', 'general' => 'Umum|General',
                    ])],
                    ['key' => 'style', 'type' => 'select', 'label' => 'Gaya optimasi|Optimization style', 'default' => 'structured', 'options' => self::opts([
                        'structured' => 'Terstruktur (subjek, gaya, detail, batasan)|Structured', 'detailed' => 'Sangat detail|Highly detailed', 'concise' => 'Ringkas & tajam|Concise',
                        'role' => 'Berbasis peran (persona ahli)|Role-based', 'creative' => 'Lebih kreatif|More creative',
                    ])],
                    ['key' => 'variations', 'type' => 'number', 'label' => 'Jumlah versi|Versions', 'min' => 1, 'max' => 3, 'default' => 1],
                    ['key' => 'negative', 'type' => 'toggle', 'label' => 'Sertakan prompt negatif|Include negative prompt', 'default' => true, 'show_if' => ['target' => ['image', 'video']]],
                    ['key' => 'goal', 'type' => 'text', 'label' => 'Tujuan akhir|End goal', 'max' => 200, 'group' => 'advanced', 'placeholder' => 'poster promo Instagram, nuansa hangat|warm Instagram promo poster'],
                    ['key' => 'language', 'type' => 'select', 'label' => 'Bahasa prompt|Prompt language', 'default' => 'en', 'group' => 'advanced', 'hint' => 'Model gambar/video umumnya paling akurat dengan prompt bahasa Inggris.|Image/video models are usually most accurate with English prompts.', 'options' => self::opts(['en' => 'English', 'id' => 'Bahasa Indonesia'])],
                ],
                'sections' => [
                    'optimized' => ['Prompt teroptimasi|Optimized prompt', 'prompt final siap pakai'],
                    'negative' => ['Prompt negatif|Negative prompt', 'hal yang harus dihindari, dipisah koma', 'negative'],
                    'changes' => ['Yang diperbaiki|What changed', 'daftar singkat perubahan dan alasannya'],
                    'tips' => ['Tips pemakaian|Usage tips', '1–3 saran parameter/penggunaan'],
                ],
                'system' => 'Anda prompt engineer berpengalaman. Perbaiki prompt agar spesifik, tidak ambigu, dan sesuai jenis model target. Pertahankan maksud asli pengguna; jangan menambah klaim atau merek yang tidak disebut.',
                'instruction' => 'Optimalkan prompt berikut untuk {{target}} dengan gaya {{style}}. Tulis prompt final dalam bahasa {{language}}. Prompt asli: {{prompt}}',
            ],

            /* ================= GAMBAR & VIDEO ================= */
            'photo_generate' => [
                'type' => 'photo_generate', 'capability' => 'image', 'entitlement' => 'photo.generate', 'group' => 'create', 'icon' => 'image',
                'label' => 'Generate Foto|Generate Photo', 'description' => 'Foto produk & konten dari deskripsi, dengan kontrol gaya, latar, cahaya, dan sudut kamera.|Product and content photos with control over style, background, lighting and camera angle.',
                'prompt_field' => 'subject',
                'fields' => [
                    ['key' => 'subject', 'type' => 'textarea', 'label' => 'Objek / produk utama|Main subject', 'required' => true, 'max' => 600, 'rows' => 3, 'placeholder' => 'Gelas kopi susu gula aren dengan es batu, embun di gelas|Iced palm sugar coffee with condensation on the glass'],
                    $ratio,
                    ['key' => 'style', 'type' => 'select', 'label' => 'Gaya foto|Photo style', 'default' => 'product', 'options' => self::opts([
                        'product' => 'Foto produk komersial|Commercial product', 'lifestyle' => 'Lifestyle (dipakai orang)|Lifestyle', 'flatlay' => 'Flat lay', 'studio' => 'Studio polos|Plain studio',
                        'minimal' => 'Minimalis|Minimalist', 'illustration' => 'Ilustrasi|Illustration', '3d' => 'Render 3D|3D render', 'cinematic' => 'Sinematik|Cinematic', 'food' => 'Food photography',
                    ])],
                    ['key' => 'count', 'type' => 'segmented', 'label' => 'Jumlah gambar|Images', 'default' => '1', 'options' => self::opts(['1' => '1', '2' => '2', '3' => '3', '4' => '4'])],
                    ['key' => 'quality', 'type' => 'segmented', 'label' => 'Kualitas|Quality', 'default' => 'standard', 'options' => self::opts(['standard' => 'Standar|Standard', 'hd' => 'HD'])],
                    ['key' => 'background', 'type' => 'select', 'label' => 'Latar|Background', 'group' => 'advanced', 'default' => 'auto', 'options' => self::opts([
                        'auto' => 'Sesuai gaya|Match style', 'white' => 'Putih bersih|Clean white', 'wood' => 'Meja kayu|Wooden table', 'marble' => 'Marmer|Marble', 'outdoor' => 'Luar ruangan|Outdoor',
                        'cafe' => 'Kafe|Café', 'kitchen' => 'Dapur|Kitchen', 'gradient' => 'Gradasi warna|Color gradient', 'nature' => 'Alam/tanaman|Nature',
                    ])],
                    ['key' => 'lighting', 'type' => 'select', 'label' => 'Pencahayaan|Lighting', 'group' => 'advanced', 'default' => 'soft', 'options' => self::opts([
                        'soft' => 'Lembut|Soft', 'natural' => 'Cahaya alami jendela|Natural window light', 'golden' => 'Golden hour', 'studio' => 'Studio terang|Bright studio', 'dramatic' => 'Dramatis/kontras|Dramatic', 'neon' => 'Neon',
                    ])],
                    ['key' => 'angle', 'type' => 'select', 'label' => 'Sudut kamera|Camera angle', 'group' => 'advanced', 'default' => 'eye', 'options' => self::opts([
                        'eye' => 'Sejajar mata|Eye level', 'top' => 'Dari atas (top down)|Top down', '45' => '45 derajat|45°', 'close' => 'Close-up / makro|Close-up', 'wide' => 'Lebar|Wide',
                    ])],
                    ['key' => 'mood', 'type' => 'text', 'label' => 'Mood & warna|Mood & colors', 'max' => 120, 'group' => 'advanced', 'placeholder' => 'hangat, cokelat krem, cozy|warm, cream & brown, cozy'],
                    ['key' => 'negative', 'type' => 'text', 'label' => 'Jangan tampilkan|Exclude', 'max' => 200, 'group' => 'advanced', 'placeholder' => 'teks, logo, tangan|text, logos, hands'],
                ],
                'prompt_template' => '{{subject}}. Gaya: {{style}}. Latar: {{background}}. Pencahayaan: {{lighting}}. Sudut kamera: {{angle}}. Mood: {{mood}}. Foto berkualitas tinggi, fokus tajam, tanpa teks, tanpa watermark.',
            ],
            'video_generate' => [
                'type' => 'video_generate', 'capability' => 'video', 'entitlement' => 'video.generate', 'group' => 'create', 'icon' => 'video',
                'label' => 'Generate Video', 'description' => 'Video pendek dari deskripsi adegan, dengan kontrol gerak kamera, gaya, dan durasi.|Short videos from a scene description with camera motion, style and duration control.',
                'prompt_field' => 'scene',
                'fields' => [
                    ['key' => 'scene', 'type' => 'textarea', 'label' => 'Deskripsi adegan|Scene description', 'required' => true, 'max' => 800, 'rows' => 3, 'placeholder' => 'Kopi dituang ke gelas berisi es, gerak lambat, uap dan embun terlihat|Coffee poured over ice in slow motion'],
                    array_merge($ratio, ['default' => '9:16']),
                    ['key' => 'duration', 'type' => 'segmented', 'label' => 'Durasi|Duration', 'default' => '5', 'options' => self::opts(['5' => '5 detik|5 s', '10' => '10 detik|10 s', '15' => '15 detik|15 s'])],
                    ['key' => 'style', 'type' => 'select', 'label' => 'Gaya visual|Visual style', 'default' => 'commercial', 'options' => self::opts(['commercial' => 'Iklan komersial|Commercial', 'cinematic' => 'Sinematik|Cinematic', 'ugc' => 'UGC (seperti direkam HP)|UGC phone-shot', 'anime' => 'Anime', '3d' => 'Animasi 3D|3D animation'])],
                    ['key' => 'camera', 'type' => 'select', 'label' => 'Gerak kamera|Camera motion', 'group' => 'advanced', 'default' => 'slow_push', 'options' => self::opts(['static' => 'Diam|Static', 'slow_push' => 'Maju perlahan|Slow push-in', 'orbit' => 'Memutari objek|Orbit', 'pan' => 'Pan kiri–kanan|Pan', 'handheld' => 'Handheld'])],
                    ['key' => 'mood', 'type' => 'text', 'label' => 'Mood & warna|Mood & colors', 'max' => 120, 'group' => 'advanced'],
                    ['key' => 'negative', 'type' => 'text', 'label' => 'Jangan tampilkan|Exclude', 'max' => 200, 'group' => 'advanced'],
                ],
                'prompt_template' => '{{scene}}. Gaya: {{style}}. Gerak kamera: {{camera}}. Mood: {{mood}}. Rasio {{ratio}}, durasi {{duration}} detik, gerakan halus, tanpa teks.',
            ],
            'photo_edit' => [
                'type' => 'photo_edit', 'capability' => 'image', 'entitlement' => 'photo.edit', 'group' => 'edit', 'icon' => 'wand', 'upload' => 'image',
                'label' => 'Edit Foto|Edit Photo', 'description' => 'Hapus latar, perbesar resolusi, ganti latar, atau ubah bagian foto dengan instruksi.|Remove background, upscale, replace background or edit parts of a photo.',
                'prompt_field' => 'instruction',
                'fields' => [
                    ['key' => 'operation', 'type' => 'select', 'label' => 'Operasi|Operation', 'default' => 'remove_bg', 'options' => self::opts([
                        'remove_bg' => 'Hapus latar belakang|Remove background', 'upscale' => 'Perbesar resolusi 2×|Upscale 2×', 'replace_bg' => 'Ganti latar|Replace background', 'inpaint' => 'Ubah bagian foto (inpaint)|Edit part (inpaint)',
                        'enhance' => 'Perbaiki warna & ketajaman|Enhance color & sharpness',
                    ])],
                    ['key' => 'instruction', 'type' => 'textarea', 'label' => 'Instruksi|Instruction', 'max' => 600, 'rows' => 2, 'show_if' => ['operation' => ['replace_bg', 'inpaint']], 'placeholder' => 'Ganti latar dengan meja marmer putih dan tanaman hijau|Replace background with white marble and plants'],
                    ['key' => 'output', 'type' => 'segmented', 'label' => 'Format hasil|Output format', 'group' => 'advanced', 'default' => 'png', 'options' => self::opts(['png' => 'PNG', 'jpg' => 'JPG', 'webp' => 'WebP'])],
                ],
                'prompt_template' => '{{instruction}}',
            ],
            // Repo 15 video-shotcraft / 23 remotion (konsep): naskah → video teks per adegan, dirender FFmpeg
            'script_video' => [
                'type' => 'script_video', 'capability' => 'video', 'entitlement' => 'video.edit', 'group' => 'create', 'icon' => 'clapperboard',
                'label' => 'Naskah → Video|Script → Video', 'description' => 'Ubah naskah jadi video teks per adegan (kinetic text) siap posting — tanpa AI berbayar.|Turn a script into a ready-to-post kinetic-text video, scene by scene — no paid AI needed.',
                'prompt_field' => 'script',
                'fields' => [
                    ['key' => 'script', 'type' => 'textarea', 'label' => 'Teks per adegan|Text per scene', 'required' => true, 'max' => 3000, 'rows' => 6, 'hint' => 'Satu baris = satu adegan (maks 30 adegan).|One line = one scene (max 30).', 'placeholder' => "Kopi susu gula aren?\nIni cara bikinnya di rumah\n1. Seduh kopi 30 ml\n2. Tambah gula aren cair\n3. Tuang susu & es batu\nFollow untuk resep lainnya!"],
                    array_merge($ratio, ['default' => '9:16']),
                    ['key' => 'seconds', 'type' => 'number', 'label' => 'Detik per adegan|Seconds per scene', 'min' => 1, 'max' => 10, 'default' => 3],
                    ['key' => 'theme', 'type' => 'select', 'label' => 'Tema warna|Color theme', 'default' => 'night', 'options' => self::opts([
                        'night' => 'Malam (ungu tua)|Night (deep purple)', 'coffee' => 'Kopi (cokelat hangat)|Coffee (warm brown)', 'ocean' => 'Laut (biru)|Ocean (blue)',
                        'sunset' => 'Senja (oranye–merah muda)|Sunset (orange–pink)', 'mint' => 'Mint (hijau)|Mint (green)', 'mono' => 'Monokrom|Monochrome',
                    ])],
                    ['key' => 'position', 'type' => 'segmented', 'label' => 'Posisi teks|Text position', 'default' => 'center', 'group' => 'advanced', 'options' => self::opts(['top' => 'Atas|Top', 'center' => 'Tengah|Center', 'bottom' => 'Bawah|Bottom'])],
                    ['key' => 'size', 'type' => 'segmented', 'label' => 'Ukuran teks|Text size', 'default' => 'm', 'group' => 'advanced', 'options' => self::opts(['s' => 'Kecil|Small', 'm' => 'Sedang|Medium', 'l' => 'Besar|Large'])],
                    ['key' => 'animate', 'type' => 'toggle', 'label' => 'Animasi masuk teks|Text entrance animation', 'default' => true, 'group' => 'advanced'],
                    ['key' => 'brand', 'type' => 'text', 'label' => 'Watermark/handle|Handle watermark', 'max' => 40, 'group' => 'advanced', 'placeholder' => '@kopisenja'],
                    ['key' => 'resolution', 'type' => 'segmented', 'label' => 'Resolusi|Resolution', 'default' => '720', 'group' => 'advanced', 'options' => self::opts(['720' => '720p', '1080' => '1080p'])],
                ],
                'prompt_template' => '{{script}}',
            ],
            'video_edit' => [
                'type' => 'video_edit', 'capability' => 'video', 'entitlement' => 'video.edit', 'group' => 'edit', 'icon' => 'scissors', 'upload' => 'video',
                'label' => 'Edit Video', 'description' => 'Ubah rasio (crop/latar blur), potong, kompres, hapus suara, atau ubah kecepatan. Durasi video diukur otomatis.|Reframe (crop/blurred background), trim, compress, mute or change speed. Video length is measured automatically.',
                'prompt_field' => 'note',
                'fields' => [
                    ['key' => 'operation', 'type' => 'select', 'label' => 'Operasi|Operation', 'default' => 'reframe', 'options' => self::opts([
                        'reframe' => 'Ubah rasio (vertikal/kotak)|Reframe', 'trim' => 'Potong durasi|Trim', 'compress' => 'Kompres ukuran file|Compress',
                        'mute' => 'Hapus suara|Remove audio', 'speed' => 'Ubah kecepatan|Change speed',
                    ])],
                    array_merge($ratio, ['default' => '9:16', 'show_if' => ['operation' => 'reframe']]),
                    ['key' => 'layout', 'type' => 'segmented', 'label' => 'Tata letak|Layout', 'default' => 'blur', 'show_if' => ['operation' => 'reframe'], 'hint' => 'Latar blur: video utuh tanpa terpotong.|Blurred background keeps the whole frame.', 'options' => self::opts(['crop' => 'Crop tengah|Center crop', 'blur' => 'Latar blur|Blurred background', 'fit' => 'Bingkai hitam|Black bars'])],
                    ['key' => 'resolution', 'type' => 'segmented', 'label' => 'Resolusi|Resolution', 'default' => '1080', 'show_if' => ['operation' => 'reframe'], 'options' => self::opts(['720' => '720p', '1080' => '1080p'])],
                    ['key' => 'start', 'type' => 'number', 'label' => 'Mulai (detik)|Start (s)', 'min' => 0, 'max' => 7200, 'default' => 0, 'show_if' => ['operation' => 'trim']],
                    ['key' => 'length', 'type' => 'number', 'label' => 'Durasi (detik)|Length (s)', 'min' => 1, 'max' => 3600, 'default' => 30, 'show_if' => ['operation' => 'trim']],
                    ['key' => 'speed', 'type' => 'segmented', 'label' => 'Kecepatan|Speed', 'default' => '1.5', 'show_if' => ['operation' => 'speed'], 'options' => self::opts(['0.5' => '0,5×', '1.25' => '1,25×', '1.5' => '1,5×', '2' => '2×'])],
                    ['key' => 'note', 'type' => 'text', 'label' => 'Catatan|Note', 'max' => 200, 'group' => 'advanced'],
                ],
                'prompt_template' => '{{note}}',
            ],
            'video_clipper' => [
                'type' => 'video_clipper', 'capability' => 'video', 'entitlement' => 'video.clipper', 'group' => 'edit', 'icon' => 'film', 'upload' => 'video',
                'label' => 'Video Clipper', 'description' => 'Potong video panjang jadi klip pendek siap posting — momen terbaik dipilih otomatis, atau tentukan rentang waktu sendiri.|Turn long videos into short ready-to-post clips — best moments picked automatically, or set your own time ranges.',
                'prompt_field' => 'title',
                'fields' => [
                    ['key' => 'mode', 'type' => 'segmented', 'label' => 'Cara memotong|Cutting mode', 'default' => 'auto', 'options' => self::opts(['auto' => 'Otomatis (momen terbaik)|Automatic', 'manual' => 'Rentang waktu sendiri|Manual ranges'])],
                    ['key' => 'clips', 'type' => 'number', 'label' => 'Jumlah klip|Number of clips', 'min' => 1, 'max' => 10, 'default' => 3, 'show_if' => ['mode' => 'auto']],
                    ['key' => 'clip_len', 'type' => 'number', 'label' => 'Panjang klip (detik)|Clip length (s)', 'min' => 5, 'max' => 180, 'default' => 30, 'show_if' => ['mode' => 'auto'], 'hint' => 'Reels/TikTok: 15–60 detik.|Reels/TikTok: 15–60 s.'],
                    ['key' => 'ranges', 'type' => 'textarea', 'label' => 'Rentang waktu|Time ranges', 'rows' => 3, 'max' => 400, 'show_if' => ['mode' => 'manual'], 'placeholder' => "0:30-1:05\n2:10-2:40", 'hint' => 'Satu rentang per baris atau dipisah koma (maks 10, maks 3 menit per klip).|One range per line or comma-separated (max 10, max 3 min each).'],
                    array_merge($ratio, ['default' => '9:16']),
                    ['key' => 'layout', 'type' => 'segmented', 'label' => 'Tata letak|Layout', 'default' => 'crop', 'options' => self::opts(['crop' => 'Crop tengah|Center crop', 'blur' => 'Latar blur|Blurred background', 'fit' => 'Bingkai hitam|Black bars'])],
                    ['key' => 'title', 'type' => 'text', 'label' => 'Judul/hook di video|On-video title', 'max' => 120, 'placeholder' => '3 kesalahan menyeduh kopi|3 coffee brewing mistakes'],
                    ['key' => 'title_mode', 'type' => 'segmented', 'label' => 'Tampilkan judul|Show title', 'default' => 'first3', 'group' => 'advanced', 'options' => self::opts(['first3' => '3 detik pertama|First 3 s', 'full' => 'Sepanjang klip|Whole clip', 'none' => 'Tidak|No'])],
                    ['key' => 'pick', 'type' => 'select', 'label' => 'Pilih momen berdasarkan|Pick moments by', 'group' => 'advanced', 'default' => 'loud', 'show_if' => ['mode' => 'auto'], 'options' => self::opts(['loud' => 'Suara paling ramai|Loudest audio', 'scene' => 'Perubahan adegan terbanyak|Most scene changes', 'even' => 'Merata sepanjang video|Evenly spaced'])],
                    ['key' => 'min_gap', 'type' => 'number', 'label' => 'Jeda minimal antar klip (detik)|Minimum gap (s)', 'min' => 0, 'max' => 600, 'default' => 5, 'group' => 'advanced', 'show_if' => ['mode' => 'auto']],
                    ['key' => 'resolution', 'type' => 'segmented', 'label' => 'Resolusi|Resolution', 'default' => '1080', 'group' => 'advanced', 'options' => self::opts(['720' => '720p (lebih cepat)|720p (faster)', '1080' => '1080p'])],
                    ['key' => 'fade', 'type' => 'toggle', 'label' => 'Fade masuk & keluar|Fade in & out', 'default' => true, 'group' => 'advanced'],
                ],
                'prompt_template' => '{{title}}',
            ],
        ];
    }

    /* ---------------- Helper definisi ---------------- */

    /** @param array<string,string> $map value => "Label ID|Label EN" */
    private static function opts(array $map): array
    {
        $out = [];
        foreach ($map as $v => $l) {
            $out[] = ['value' => (string) $v, 'label' => $l];
        }

        return $out;
    }

    public static function exists(string $key): bool
    {
        return isset(self::definitions()[$key]);
    }

    public static function overrides(): array
    {
        return (array) Settings::get('ai_tools.overrides', []);
    }

    /** Definisi + override admin (tanpa terjemahan). */
    public static function get(string $key): ?array
    {
        $d = self::definitions()[$key] ?? null;
        if (! $d) {
            return null;
        }
        $o = (array) (self::overrides()[$key] ?? []);
        $d['key'] = $key;
        $d['enabled'] = (bool) ($o['enabled'] ?? true);
        foreach (['label', 'description'] as $k) {
            if (! empty($o[$k])) {
                $d[$k] = (string) $o[$k];
            }
        }
        // Model teks pilihan (model_id) untuk tool ini; kosong = urutan prioritas provider
        if (! empty($o['model']) && ($d['capability'] ?? '') === 'text') {
            $d['model'] = (string) $o['model'];
        }
        if (isset($o['cost_per_unit']) && isset($d['cost_per_unit'])) {
            $d['cost_per_unit'] = max(0, (int) $o['cost_per_unit']);
        }
        if (! empty($o['max_parts']) && isset($d['max_parts'])) {
            $d['max_parts'] = max(1, min((int) $d['max_parts'], (int) $o['max_parts']));
        }
        $hidden = (array) ($o['hidden_fields'] ?? []);
        $defaults = (array) ($o['defaults'] ?? []);
        $d['fields'] = array_values(array_map(function ($f) use ($defaults, $d) {
            if (array_key_exists($f['key'], $defaults)) {
                $f['default'] = $defaults[$f['key']];
            }
            // Batas jumlah part/item mengikuti max_parts tool
            if (in_array($f['key'], [$d['parts_field'] ?? 'parts', $d['count_field'] ?? '__'], true) && isset($d['max_parts'], $f['max'])) {
                $f['max'] = min((int) $f['max'], (int) $d['max_parts']);
            }

            return $f;
        }, array_filter($d['fields'], fn ($f) => empty($f['required']) ? ! in_array($f['key'], $hidden, true) : true)));

        return $d;
    }

    /** Daftar job type → [entitlement, upload] untuk StudioController. */
    public static function types(): array
    {
        $out = [];
        foreach (self::definitions() as $k => $d) {
            $out[$d['type']] = ['tool' => $k, 'ent' => $d['entitlement'], 'upload' => $d['upload'] ?? null];
        }

        return $out;
    }

    public static function forType(string $type): ?array
    {
        foreach (self::definitions() as $k => $d) {
            if ($d['type'] === $type) {
                return self::get($k);
            }
        }

        return null;
    }

    /* ---------------- Validasi parameter (server-side) ---------------- */

    /** Field terlihat menurut show_if terhadap nilai lain. */
    public static function visible(array $f, array $vals): bool
    {
        foreach ((array) ($f['show_if'] ?? []) as $k => $want) {
            $v = $vals[$k] ?? null;
            $ok = is_array($want) ? in_array((string) $v, array_map('strval', $want), true) : (is_bool($want) ? (bool) $v === $want : (string) $v === (string) $want);
            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    /**
     * Bersihkan input sesuai skema: opsi hanya dari daftar, angka di-clamp, teks dipotong, field tersembunyi dibuang.
     *
     * @return array{params: array, errors: array<string, string[]>}
     */
    public static function sanitize(array $tool, array $in): array
    {
        $vals = [];
        foreach ($tool['fields'] as $f) {
            $k = $f['key'];
            $raw = $in[$k] ?? ($f['default'] ?? null);
            $allowed = array_column($f['options'] ?? [], 'value');
            $vals[$k] = match ($f['type']) {
                'select', 'segmented' => in_array((string) $raw, $allowed, true) ? (string) $raw : ($f['default'] ?? ($allowed[0] ?? null)),
                'multiselect' => array_values(array_intersect($allowed, array_map('strval', (array) $raw))),
                'number' => max((int) ($f['min'] ?? 0), min((int) ($f['max'] ?? 100000), (int) $raw)),
                'toggle' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
                default => mb_substr(trim(strip_tags((string) $raw)), 0, (int) ($f['max'] ?? 500)),
            };
        }
        $out = [];
        $errors = [];
        foreach ($tool['fields'] as $f) {
            if (! self::visible($f, $vals)) {
                continue;
            }
            $out[$f['key']] = $vals[$f['key']];
            if (! empty($f['required']) && ($vals[$f['key']] === '' || $vals[$f['key']] === null || $vals[$f['key']] === [])) {
                $errors[$f['key']] = [self::tr($f['label']).' wajib diisi.'];
            }
        }

        return ['params' => $out, 'errors' => $errors];
    }

    /** Jumlah unit (part/variasi/batch item) untuk biaya teks. */
    public static function units(array $tool, array $params): int
    {
        if (($tool['mode'] ?? null) === 'list') {
            return max(1, (int) ceil(((int) ($params[$tool['count_field']] ?? 5)) / 10));
        }
        if (($tool['mode'] ?? null) === 'parts') {
            return self::partCount($tool, $params);
        }

        return 1;
    }

    public static function partCount(array $tool, array $params): int
    {
        $field = $tool['parts_field'] ?? 'parts';
        if (($tool['key'] ?? '') === 'content_script' && empty($params['series'])) {
            return 1;
        }

        return max(1, min((int) ($tool['max_parts'] ?? 1), (int) ($params[$field] ?? 1)));
    }

    /* ---------------- Serialisasi untuk klien ---------------- */

    public static function tr(string $s, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $p = explode('|', $s, 2);

        return $locale === 'en' && isset($p[1]) ? $p[1] : $p[0];
    }

    /** Label nilai opsi (untuk prompt AI & tampilan hasil). */
    public static function optionLabel(array $tool, string $key, $value): string
    {
        foreach ($tool['fields'] as $f) {
            if ($f['key'] !== $key) {
                continue;
            }
            $map = array_column($f['options'] ?? [], 'label', 'value');
            if (is_array($value)) {
                return implode(', ', array_map(fn ($v) => self::tr($map[$v] ?? (string) $v, 'id'), $value));
            }
            if (is_bool($value)) {
                return $value ? 'ya' : 'tidak';
            }

            return isset($map[(string) $value]) ? self::tr($map[(string) $value], 'id') : (string) $value;
        }

        return is_array($value) ? implode(', ', $value) : (string) $value;
    }

    public static function present(array $tool, ?string $locale = null): array
    {
        $tr = fn ($s) => is_string($s) ? self::tr($s, $locale) : $s;
        $fields = array_map(function ($f) use ($tr) {
            $f['label'] = $tr($f['label']);
            foreach (['hint', 'placeholder'] as $k) {
                if (isset($f[$k])) {
                    $f[$k] = $tr($f[$k]);
                }
            }
            if (isset($f['options'])) {
                $f['options'] = array_map(fn ($o) => ['value' => $o['value'], 'label' => $tr($o['label'])], $f['options']);
            }
            $f['group'] ??= 'basic';

            return $f;
        }, $tool['fields']);
        $sec = fn ($list) => array_map(fn ($k, $v) => ['key' => $k, 'label' => $tr($v[0])], array_keys($list), array_values($list));

        return [
            'key' => $tool['key'], 'type' => $tool['type'], 'capability' => $tool['capability'], 'entitlement' => $tool['entitlement'], 'group' => $tool['group'],
            'icon' => $tool['icon'] ?? 'sparkles', 'label' => $tr($tool['label']), 'description' => $tr($tool['description'] ?? ''), 'upload' => $tool['upload'] ?? null,
            'mode' => $tool['mode'] ?? 'media', 'prompt_field' => $tool['prompt_field'], 'max_parts' => $tool['max_parts'] ?? null, 'cost_per_unit' => $tool['cost_per_unit'] ?? null,
            'enabled' => $tool['enabled'],
            // Siap = ada provider aktif, atau tool diproses lokal (FFmpeg untuk video, Imagick/GD untuk upscale & enhance)
            'provider_ready' => in_array($tool['key'], ['video_edit', 'video_clipper', 'script_video'], true) ? VideoTools::available() : AiGateway::configured($tool['capability']),
            'local_ops' => $tool['key'] === 'photo_edit' ? ImageTools::LOCAL_OPS : [],
            'fields' => $fields, 'sections' => $sec($tool['sections'] ?? []), 'item_fields' => $sec($tool['item_fields'] ?? []),
        ];
    }
}
