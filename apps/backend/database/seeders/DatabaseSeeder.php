<?php

namespace Database\Seeders;

use App\Http\Controllers\Api\AuthController;
use App\Models\Addon;
use App\Models\EmailTemplate;
use App\Models\Faq;
use App\Models\FeatureFlag;
use App\Models\LegalDocument;
use App\Models\Plan;
use App\Models\SeoRule;
use App\Models\Template;
use App\Models\TemplateCategory;
use App\Models\User;
use App\Models\AiPrompt;
use App\Services\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Data awal Krafio. CATATAN: harga paket/add-on adalah NILAI CONTOH untuk pengembangan —
 * PRD 4.2 menetapkan harga diatur Admin dari panel, bukan di kode. Teks hukum adalah DRAF
 * yang wajib ditinjau penasihat hukum sebelum rilis (Design Spec §11.1).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->admin();
        $this->catalog();
        $this->templates();
        $this->faqs();
        $this->legal();
        $this->seo();
        $this->misc();
        if (app()->environment('local')) {
            $this->demoUser();
        }
    }

    private function admin(): void
    {
        $email = config('krafio.admin_email');
        if (User::where('email', $email)->exists()) {
            return;
        }
        $password = env('KRAFIO_ADMIN_PASSWORD') ?: Str::password(16, symbols: false);
        $u = new User(['name' => 'Super Admin', 'email' => $email, 'password' => $password]);
        $u->forceFill(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now(), 'referral_code' => 'ADMIN'.strtoupper(Str::random(3))])->save();
        @mkdir(storage_path('app/private'), 0700, true);
        file_put_contents(storage_path('app/private/admin-credentials.txt'), "Admin Krafio (lokal)\nEmail: {$email}\nPassword: {$password}\nMFA TOTP wajib disiapkan saat login pertama.\n");
        $this->command?->warn("Admin dibuat: {$email} / {$password} (juga tersimpan di storage/app/private/admin-credentials.txt)");
    }

    /**
     * Paket & add-on sesuai PRD Bab 55.2–55.3 (status: usulan/draft — nilai awal konfigurasi yang wajib
     * dihitung ulang setelah AI provider final ditetapkan; keputusan P-01..P-10 masih terbuka).
     * quotas.credits = kredit/bulan; chatbot = pesan/hari; brainstorm & SEO = request/bulan.
     */
    private function catalog(): void
    {
        $media = ['photo.generate', 'photo.edit', 'video.generate', 'video.edit', 'video.clipper'];
        $plans = [
            ['free', 'Gratis', 'Free', 'Coba fitur dasar untuk mengenal Krafio.', 'Try the basics and get to know Krafio.', 0, 0,
                ['photo.generate', 'photo.edit', 'content.brainstorm', 'website.builder', 'seo.basic', 'chatbot.basic', 'exports.download'],
                ['credits' => 50, 'chatbot.basic' => 10, 'content.brainstorm' => 5, 'seo.basic' => 3],
                ['projects' => 1, 'websites' => 1, 'website_preview_only' => true, 'storage_mb' => 500, 'social_accounts' => 0, 'parallel_jobs' => 1, 'priority' => 'low', 'photo_max_px' => 1024, 'video_max_seconds' => 0, 'clipper_max_minutes' => 0, 'watermark' => true, 'templates' => 'free', 'ai_agent' => false], false, 1],
            ['starter', 'Starter', 'Starter', 'Untuk kreator yang mulai rutin membuat konten.', 'For creators starting a regular content routine.', 59000, 590000,
                array_merge($media, ['content.brainstorm', 'templates.premium', 'website.builder', 'seo.basic', 'social.integrations', 'chatbot.basic', 'exports.download']),
                ['credits' => 300, 'chatbot.basic' => 30, 'content.brainstorm' => 30, 'seo.basic' => 20],
                ['projects' => 3, 'websites' => 1, 'storage_mb' => 5120, 'social_accounts' => 2, 'parallel_jobs' => 2, 'priority' => 'normal', 'photo_max_px' => 2048, 'video_resolution' => '720p', 'video_max_seconds' => 5, 'clipper_max_minutes' => 15, 'watermark' => false, 'templates' => 'partial', 'ai_agent' => false], false, 2],
            ['pro', 'Pro', 'Pro', 'Foto, video, dan website untuk bisnis yang tumbuh.', 'Photo, video, and website for growing businesses.', 179000, 1790000,
                array_merge($media, ['content.brainstorm', 'templates.premium', 'website.builder', 'seo.basic', 'seo.advanced', 'social.integrations', 'chatbot.basic', 'chatbot.premium', 'exports.download']),
                ['credits' => 1000, 'chatbot.basic' => 80, 'chatbot.premium' => 20, 'content.brainstorm' => 150, 'seo.basic' => 100, 'seo.advanced' => 100],
                ['projects' => 15, 'websites' => 3, 'custom_domains' => 1, 'storage_mb' => 25600, 'social_accounts' => 5, 'scheduling' => true, 'parallel_jobs' => 3, 'priority' => 'high', 'photo_max_px' => 4096, 'video_resolution' => '1080p', 'video_max_seconds' => 10, 'clipper_max_minutes' => 60, 'watermark' => false, 'templates' => 'all', 'ai_agent' => 'limited'], true, 3],
            ['business', 'Business', 'Business', 'Kapasitas besar dan fitur lanjutan untuk agensi.', 'High capacity and advanced features for agencies.', 449000, 4490000,
                array_merge($media, ['content.brainstorm', 'templates.premium', 'website.builder', 'seo.basic', 'seo.advanced', 'social.integrations', 'chatbot.basic', 'chatbot.premium', 'exports.download']),
                ['credits' => 2500, 'chatbot.basic' => 200, 'chatbot.premium' => 60, 'content.brainstorm' => 500, 'seo.basic' => 300, 'seo.advanced' => 300],
                ['projects' => 50, 'websites' => 10, 'custom_domains' => 10, 'website_export' => true, 'storage_mb' => 102400, 'social_accounts' => 15, 'whatsapp_business' => true, 'scheduling' => true, 'parallel_jobs' => 5, 'priority' => 'highest', 'photo_max_px' => 4096, 'video_resolution' => '1080p', 'video_max_seconds' => 15, 'clipper_max_minutes' => 120, 'seo_crawl_pages' => 50, 'watermark' => false, 'templates' => 'all', 'ai_agent' => true], false, 4],
            ['enterprise', 'Enterprise', 'Enterprise', 'Kebutuhan khusus — harga dan kapasitas melalui pengajuan Custom Package.', 'Custom needs — price and capacity via a Custom Package request.', 0, 0,
                [], [], ['custom' => true, 'priority' => 'dedicated'], false, 5],
        ];
        foreach ($plans as [$code, $id, $en, $did, $den, $m, $y, $features, $quotas, $limits, $rec, $sort]) {
            Plan::updateOrCreate(['code' => $code], ['name_id' => $id, 'name_en' => $en, 'description_id' => $did, 'description_en' => $den, 'price_monthly' => $m, 'price_yearly' => $y, 'features' => $features, 'quotas' => $quotas, 'limits' => $limits, 'is_recommended' => $rec, 'is_active' => true, 'sort' => $sort]);
        }
        $addons = [
            ['credits-200', 'Kredit 200', '200 credits', 'Kredit tambahan, berlaku 90 hari. Dipakai setelah kredit paket.', 'Extra credits, valid 90 days. Used after plan credits.', 49000, 'one_time', 90, ['credits' => 200], null],
            ['credits-500', 'Kredit 500', '500 credits', 'Kredit tambahan, berlaku 90 hari. Dipakai setelah kredit paket.', 'Extra credits, valid 90 days. Used after plan credits.', 109000, 'one_time', 90, ['credits' => 500], null],
            ['credits-1500', 'Kredit 1.500', '1,500 credits', 'Kredit tambahan, berlaku 90 hari. Dipakai setelah kredit paket.', 'Extra credits, valid 90 days. Used after plan credits.', 299000, 'one_time', 90, ['credits' => 1500], null],
            ['storage-20gb', 'Storage +20 GB', 'Storage +20 GB', 'Tambahan ruang penyimpanan, ikut masa langganan.', 'Extra storage, follows your subscription.', 25000, 'follow_subscription', 30, ['limits.storage_mb' => 20480], null],
            ['social-5', 'Akun sosial +5', '+5 social accounts', 'Tambahan 5 akun media sosial. Minimal Starter.', '5 extra social accounts. Starter or above.', 35000, 'recurring', 30, ['limits.social_accounts' => 5], ['starter', 'pro', 'business']],
            ['website-extra', 'Website tambahan', 'Extra website', 'Satu website tambahan per bulan. Minimal Starter.', 'One extra website per month. Starter or above.', 29000, 'recurring', 30, ['limits.websites' => 1], ['starter', 'pro', 'business']],
            ['seo-advanced', 'SEO Advanced', 'SEO Advanced', 'Aturan SEO lanjutan. Khusus Starter.', 'Advanced SEO rules. Starter only.', 49000, 'recurring', 30, ['seo.advanced' => 50], ['starter']],
            ['premium-templates', 'Pack template premium', 'Premium template pack', 'Seluruh template premium. Khusus Starter.', 'All premium templates. Starter only.', 29000, 'recurring', 30, ['templates.premium' => true], ['starter']],
        ];
        foreach ($addons as [$code, $id, $en, $did, $den, $price, $billing, $days, $grants, $planCodes]) {
            Addon::updateOrCreate(['code' => $code], ['name_id' => $id, 'name_en' => $en, 'description_id' => $did, 'description_en' => $den, 'price' => $price, 'billing' => $billing, 'duration_days' => $days, 'grants' => $grants, 'plan_codes' => $planCodes, 'is_active' => true]);
        }
        // Contoh promo (55.4.1). Nonaktif kecuali referral — Admin mengaktifkan sesuai kampanye.
        \App\Models\Coupon::updateOrCreate(['code' => 'REFERRAL10'], ['name' => 'Diskon referral bulan pertama', 'type' => 'percent', 'value' => 10, 'plan_codes' => ['starter', 'pro', 'business'], 'cycles' => ['monthly'], 'first_purchase_only' => true, 'auto_apply' => true, 'campaign' => 'referral', 'duration' => 'once', 'is_active' => true]);
        \App\Models\Coupon::updateOrCreate(['code' => 'UPGRADE20'], ['name' => 'Upgrade Starter ke Pro', 'type' => 'percent', 'value' => 20, 'plan_codes' => ['pro'], 'cycles' => ['monthly'], 'auto_apply' => true, 'campaign' => 'upgrade', 'duration' => 'once', 'is_active' => true]);
        \App\Models\Coupon::updateOrCreate(['code' => 'WINBACK15'], ['name' => 'Kembali lagi', 'type' => 'percent', 'value' => 15, 'max_discount' => 50000, 'auto_apply' => true, 'campaign' => 'winback', 'duration' => 'once', 'is_active' => false]);
        \App\Models\Coupon::updateOrCreate(['code' => 'EARLYBIRD'], ['name' => 'Early bird peluncuran', 'type' => 'percent', 'value' => 30, 'max_discount' => 100000, 'plan_codes' => ['starter', 'pro'], 'duration' => 'first_n_months', 'duration_months' => 3, 'max_uses' => 500, 'max_uses_per_user' => 1, 'is_active' => false]);
        \App\Models\Coupon::updateOrCreate(['code' => 'BONUS100'], ['name' => 'Bonus 100 kredit', 'type' => 'bonus_credit', 'value' => 100, 'min_purchase' => 59000, 'duration' => 'once', 'max_uses_per_user' => 1, 'is_active' => true] + \App\Support\PromoShowcase::SEED['BONUS100']);
        // Promo publik contoh (tampil di /promo & beranda)
        foreach (\App\Support\PromoShowcase::extraCoupons() as $code => $attrs) {
            \App\Models\Coupon::firstOrCreate(['code' => $code], $attrs);
        }
    }

    private function templates(): void
    {
        $cats = [['produk-fnb', 'Produk F&B', 'F&B products', 'photo'], ['fashion', 'Fashion', 'Fashion', 'photo'], ['promo-reels', 'Promo Reels', 'Promo reels', 'video'], ['company-profile', 'Company Profile', 'Company profile', 'website'], ['feed-instagram', 'Feed Instagram', 'Instagram feed', 'social'], ['dokumen-bisnis', 'Dokumen Bisnis', 'Business documents', 'business']];
        foreach ($cats as $i => [$slug, $id, $en, $type]) {
            TemplateCategory::updateOrCreate(['slug' => $slug], ['name_id' => $id, 'name_en' => $en, 'type' => $type, 'sort' => $i]);
        }
        $palettes = [['#F4E7D3', '#C7A27C', '#3E2C23'], ['#E3EEF5', '#7FA7C2', '#1F3A4D'], ['#F7E1E1', '#E08A8A', '#5A1F2B'], ['#E8F3E8', '#7DB38A', '#1E4A2E'], ['#FFF4D6', '#F2B84B', '#4A3200'], ['#ECE8F7', '#9C8FD1', '#2D2557'], ['#F1F1EF', '#A3A39B', '#262624'], ['#DDF2F0', '#5FB3A9', '#0F3D38']];
        $items = [
            ['Kopi susu gula aren', 'Iced palm sugar latte', 'photo', 'produk-fnb', '1:1'], ['Roti artisan pagi', 'Morning artisan bread', 'photo', 'produk-fnb', '4:5'], ['Batik modern flatlay', 'Modern batik flat lay', 'photo', 'fashion', '16:9'],
            ['Sepatu kanvas lokal', 'Local canvas sneakers', 'photo', 'fashion', '1:1'], ['Skincare botol kaca', 'Glass bottle skincare', 'photo', 'produk-fnb', '9:16'], ['Sambal kemasan', 'Packaged chili sauce', 'photo', 'produk-fnb', '1:1'],
            ['Promo flash sale 15 detik', '15-second flash sale promo', 'video', 'promo-reels', '9:16'], ['Unboxing produk', 'Product unboxing', 'video', 'promo-reels', '9:16'], ['Testimoni pelanggan', 'Customer testimonial', 'video', 'promo-reels', '1:1'],
            ['Tutorial 30 detik', '30-second tutorial', 'video', 'promo-reels', '9:16'], ['Profil kafe & resto', 'Cafe & restaurant profile', 'website', 'company-profile', '16:9'], ['Profil jasa konsultan', 'Consulting firm profile', 'website', 'company-profile', '16:9'],
            ['Profil bengkel & otomotif', 'Workshop & automotive profile', 'website', 'company-profile', '16:9'], ['Profil klinik kecantikan', 'Beauty clinic profile', 'website', 'company-profile', '16:9'], ['Carousel tips 5 slide', '5-slide tips carousel', 'social', 'feed-instagram', '4:5'],
            ['Pengumuman jam buka', 'Opening hours announcement', 'social', 'feed-instagram', '1:1'], ['Quote harian', 'Daily quote', 'social', 'feed-instagram', '1:1'], ['Story giveaway', 'Giveaway story', 'social', 'feed-instagram', '9:16'],
            ['Kartu nama minimalis', 'Minimal business card', 'business', 'dokumen-bisnis', '16:9'], ['Invoice sederhana', 'Simple invoice', 'business', 'dokumen-bisnis', '4:3'], ['Katalog produk A4', 'A4 product catalog', 'business', 'dokumen-bisnis', '4:3'],
            ['Menu digital', 'Digital menu', 'business', 'dokumen-bisnis', '9:16'], ['Banner marketplace', 'Marketplace banner', 'photo', 'fashion', '16:9'], ['Poster event komunitas', 'Community event poster', 'social', 'feed-instagram', '4:5'],
        ];
        foreach ($items as $i => [$id, $en, $type, $cat, $ratio]) {
            Template::updateOrCreate(['title_id' => $id], [
                'title_en' => $en, 'type' => $type, 'category' => $cat, 'ratio' => $ratio, 'palette' => $palettes[$i % count($palettes)],
                'description_id' => 'Template '.mb_strtolower($id).' yang dapat diubah teks, warna, dan gambarnya.', 'description_en' => 'Editable '.mb_strtolower($en).' template: text, colors, and images.',
                'is_premium' => $i % 3 === 2, 'is_featured' => $i % 2 === 0, 'status' => 'active', 'usage_count' => 0,
            ]);
        }
    }

    private function faqs(): void
    {
        $f = [
            ['general', 'Apa itu Krafio?', 'Krafio adalah platform web untuk membuat foto, video, konten, website company profile, dan audit SEO dengan bantuan AI dalam satu akun.', 'What is Krafio?', 'Krafio is a web platform to create photos, videos, content, company profile websites, and SEO audits with AI in one account.'],
            ['billing', 'Apa beda saldo top-up dan saldo komisi?', 'Saldo top-up dipakai untuk membayar paket dan add-on serta tidak dapat ditarik. Saldo komisi berasal dari program affiliate dan dapat ditarik mulai Rp100.000.', 'What is the difference between top-up and commission balance?', 'Top-up balance pays for plans and add-ons and cannot be withdrawn. Commission balance comes from the affiliate program and can be withdrawn from Rp100,000.'],
            ['billing', 'Berapa minimal dan maksimal top-up?', 'Minimal Rp50.000 dan maksimal Rp1.000.000 per transaksi. Saldo bertambah setelah pembayaran dikonfirmasi oleh payment gateway.', 'What are the top-up limits?', 'Minimum Rp50,000 and maximum Rp1,000,000 per transaction. Balance is added after the payment gateway confirms payment.'],
            ['billing', 'Berapa lama proses withdrawal?', 'Withdrawal diproses 1–3 hari kerja setelah pengajuan dinyatakan valid oleh tim kami.', 'How long does a withdrawal take?', 'Withdrawals are processed within 1–3 business days after our team validates the request.'],
            ['ai', 'Apakah kuota terpotong bila job AI gagal?', 'Tidak. Bila job gagal di sisi sistem atau penyedia AI, kuota yang dipakai dikembalikan otomatis.', 'Is quota deducted when an AI job fails?', 'No. If a job fails on our side or the AI provider\'s side, the quota is returned automatically.'],
            ['ai', 'Apakah saya bisa melihat biaya sebelum generate?', 'Ya. Estimasi kredit dan sisa kuota selalu ditampilkan sebelum Anda menekan tombol Generate.', 'Can I see the cost before generating?', 'Yes. The credit estimate and remaining quota are always shown before you press Generate.'],
            ['account', 'Bagaimana jika fitur terkunci?', 'Fitur di luar paket tetap terlihat dengan ikon gembok. Klik untuk melihat paket atau add-on yang membukanya.', 'What if a feature is locked?', 'Features outside your plan stay visible with a lock icon. Click to see the plan or add-on that unlocks it.'],
            ['account', 'Apakah data saya hilang saat langganan berakhir?', 'Tidak. Proyek dan hasil tetap tersimpan; fitur berbayar dikunci sampai Anda memperpanjang.', 'Is my data deleted when my subscription ends?', 'No. Projects and results stay saved; paid features are locked until you renew.'],
            ['affiliate', 'Kapan komisi affiliate dapat ditarik?', 'Komisi berstatus menunggu selama masa validasi, lalu menjadi tersedia dan dapat diajukan withdrawal.', 'When can affiliate commission be withdrawn?', 'Commission stays pending during the validation period, then becomes available for withdrawal.'],
            ['affiliate', 'Apakah top-up menghasilkan komisi?', 'Tidak. Komisi dihitung dari pembelian paket dan add-on yang valid.', 'Do top-ups earn commission?', 'No. Commission is calculated from valid plan and add-on purchases.'],
            ['general', 'Apakah Krafio bisa dipakai di ponsel?', 'Bisa. Krafio adalah aplikasi web progresif yang dapat dipasang di layar utama ponsel.', 'Can I use Krafio on my phone?', 'Yes. Krafio is a progressive web app you can install on your home screen.'],
            ['general', 'Bagaimana menghubungi support?', 'Gunakan tombol bantuan di kanan bawah untuk membuat tiket atau membaca pusat bantuan.', 'How do I contact support?', 'Use the help button at the bottom right to open a ticket or read the help center.'],
        ];
        foreach ($f as $i => [$cat, $qid, $aid, $qen, $aen]) {
            Faq::updateOrCreate(['question_id' => $qid], ['category' => $cat, 'answer_id' => $aid, 'question_en' => $qen, 'answer_en' => $aen, 'sort' => $i, 'is_active' => true]);
        }
    }

    private function legal(): void
    {
        $note = 'DRAF untuk pengembangan — wajib ditinjau dan difinalkan oleh penasihat hukum sebelum rilis.';
        $docs = [
            'terms' => ['Syarat & Ketentuan', 'Terms & Conditions', [['definisi', 'Definisi', 'Krafio adalah layanan SaaS untuk produksi dan pemasaran konten. "Pengguna" adalah pemilik akun terdaftar.'], ['akun', 'Akun', "Pengguna bertanggung jawab menjaga kerahasiaan kata sandi.\nSatu akun digunakan oleh satu pemilik kecuali paket mengizinkan lain."], ['langganan', 'Langganan & pembayaran', 'Paket aktif setelah pembayaran terkonfirmasi. Harga dan isi paket mengikuti halaman harga pada saat pembelian.'], ['konten', 'Konten & penggunaan AI', 'Pengguna bertanggung jawab atas prompt, berkas, dan publikasi hasil. Dilarang membuat konten yang melanggar hukum atau hak pihak lain.'], ['penghentian', 'Penangguhan & penghentian', 'Krafio dapat menangguhkan akun yang melanggar ketentuan setelah peninjauan.'], ['catatan', 'Status dokumen', $note]]],
            'privacy' => ['Kebijakan Privasi', 'Privacy Policy', [['data', 'Data yang dikumpulkan', 'Nama, email, nomor WhatsApp (opsional), data transaksi, dan log aktivitas untuk keamanan.'], ['ai', 'Pemrosesan oleh penyedia AI', 'Prompt dan berkas yang Anda kirim ke fitur AI diproses oleh penyedia AI pihak ketiga setelah Anda menyetujuinya.'], ['simpan', 'Penyimpanan & retensi', 'Data disimpan selama akun aktif dan sesuai kewajiban hukum yang berlaku.'], ['hak', 'Hak pengguna', 'Anda dapat meminta salinan, koreksi, atau penghapusan data melalui support.'], ['catatan', 'Status dokumen', $note]]],
            'refund' => ['Kebijakan Refund', 'Refund Policy', [['umum', 'Ketentuan umum', 'Saldo top-up pada prinsipnya tidak dapat ditarik kecuali refund disetujui sesuai kebijakan ini.'], ['proses', 'Proses refund', 'Ajukan melalui tiket support dengan bukti transaksi. Keputusan dikirim melalui email dan notifikasi.'], ['catatan', 'Status dokumen', $note]]],
            'affiliate_terms' => ['Ketentuan Affiliate', 'Affiliate Terms', [['komisi', 'Perhitungan komisi', 'Komisi dihitung dari pembelian paket atau add-on yang valid oleh pengguna yang Anda rujuk, bukan dari top-up.'], ['status', 'Status komisi', 'Menunggu → disetujui → tersedia → dibayar. Komisi dapat dibatalkan bila terjadi refund, chargeback, atau kecurangan.'], ['withdrawal', 'Withdrawal', 'Minimal Rp100.000, diproses 1–3 hari kerja setelah valid. Biaya transfer atau pajak, bila berlaku, diinformasikan sebelum pengajuan.'], ['larangan', 'Larangan', 'Dilarang merujuk akun sendiri atau memakai iklan yang menyesatkan.'], ['catatan', 'Status dokumen', $note]]],
            'cookies' => ['Kebijakan Cookie', 'Cookie Policy', [['esensial', 'Cookie esensial', 'Diperlukan untuk login, keamanan (CSRF), dan preferensi tampilan. Tidak dapat dimatikan.'], ['pilihan', 'Cookie pilihan', 'Analitik, fungsional, dan marketing hanya dimuat setelah Anda menyetujuinya dan dapat diubah kapan saja dari footer.'], ['catatan', 'Status dokumen', $note]]],
        ];
        foreach ($docs as $type => [$tid, $ten, $sections]) {
            LegalDocument::updateOrCreate(['type' => $type, 'version' => '1.0'], [
                'effective_at' => now()->toDateString(), 'title_id' => $tid, 'title_en' => $ten,
                'sections_id' => array_map(fn ($s) => ['id' => $s[0], 'heading' => $s[1], 'body' => $s[2]], $sections), 'sections_en' => null,
                'summary_id' => null, 'requires_reconsent' => false, 'is_active' => true,
            ]);
        }
    }

    private function seo(): void
    {
        $rules = [['title_present', 'Judul halaman ada', 'critical', 10], ['title_length', 'Panjang judul ideal', 'warning', 5], ['meta_description', 'Meta description ada', 'critical', 8], ['single_h1', 'Tepat satu H1', 'warning', 6], ['img_alt', 'Gambar memiliki alt', 'warning', 6], ['html_lang', 'Atribut bahasa', 'info', 3], ['viewport', 'Meta viewport', 'critical', 8], ['canonical', 'Tag canonical', 'info', 4], ['https', 'Memakai HTTPS', 'critical', 10], ['open_graph', 'Open Graph', 'info', 3]];
        foreach ($rules as [$code, $name, $sev, $w]) {
            SeoRule::updateOrCreate(['code' => $code], ['name' => $name, 'severity' => $sev, 'weight' => $w, 'is_active' => true, 'ruleset_version' => '1.0']);
        }
    }

    private function misc(): void
    {
        foreach ([['registration', 'Pendaftaran akun baru'], ['ai_generation', 'Job generate AI'], ['withdrawal', 'Pengajuan withdrawal'], ['website_publish', 'Publikasi website'], ['affiliate', 'Program affiliate']] as [$k, $d]) {
            FeatureFlag::updateOrCreate(['key' => $k], ['description' => $d, 'enabled' => true]);
        }
        // Template email per event (dapat diedit Admin → Communication → Template email). Variabel: {{nama}}.
        $mails = [
            'otp' => ['Kode verifikasi Krafio', "Halo {{name}},\n\nKode verifikasi Anda: {{code}}\nBerlaku {{minutes}} menit. Jangan bagikan kode ini kepada siapa pun, termasuk tim Krafio.", 'Your Krafio verification code', "Hi {{name}},\n\nYour verification code: {{code}}\nValid for {{minutes}} minutes. Never share this code with anyone."],
            'welcome' => ['Selamat datang di Krafio', "Halo {{name}},\n\nAkun Anda sudah aktif dengan paket {{plan}}. Mulai buat konten pertama Anda di dashboard.", 'Welcome to Krafio', "Hi {{name}},\n\nYour account is active on the {{plan}} plan. Start creating from your dashboard."],
            'password_changed' => ['Kata sandi Krafio diubah', "Halo {{name}},\n\nKata sandi akun Anda diubah pada {{time}}. Jika bukan Anda, segera hubungi support.", 'Your Krafio password was changed', "Hi {{name}},\n\nYour password was changed at {{time}}. If this wasn't you, contact support right away."],
            'topup_success' => ['Top-up berhasil', "Halo {{name}},\n\nTop-up {{amount}} berhasil (ref {{reference}}). Saldo top-up Anda sudah bertambah.", 'Top-up successful', "Hi {{name}},\n\nYour {{amount}} top-up succeeded (ref {{reference}})."],
            'subscription_active' => ['Paket {{plan}} aktif', "Halo {{name}},\n\nPaket {{plan}} aktif sampai {{ends_at}}. Terima kasih telah berlangganan.", 'Your {{plan}} plan is active', "Hi {{name}},\n\nYour {{plan}} plan is active until {{ends_at}}."],
            'subscription_expiring' => ['Paket segera berakhir', "Halo {{name}},\n\nPaket {{plan}} berakhir pada {{ends_at}}. Perpanjang agar fitur tetap terbuka.", 'Your plan ends soon', "Hi {{name}},\n\nYour {{plan}} plan ends on {{ends_at}}."],
            'withdrawal_requested' => ['Pengajuan withdrawal diterima', "Halo {{name}},\n\nPengajuan withdrawal {{amount}} kami terima dan akan diproses 1–3 hari kerja setelah valid.", 'Withdrawal request received', "Hi {{name}},\n\nWe received your {{amount}} withdrawal request."],
            'withdrawal_paid' => ['Withdrawal telah dibayar', "Halo {{name}},\n\nWithdrawal {{amount}} telah ditransfer. Referensi: {{reference}}.", 'Withdrawal paid', "Hi {{name}},\n\nYour {{amount}} withdrawal was transferred. Reference: {{reference}}."],
            'withdrawal_rejected' => ['Withdrawal ditolak', "Halo {{name}},\n\nWithdrawal {{amount}} ditolak: {{reason}}. Dana dikembalikan ke saldo komisi Anda.", 'Withdrawal rejected', "Hi {{name}},\n\nYour {{amount}} withdrawal was rejected: {{reason}}. Funds were returned to your commission balance."],
            'test' => ['Email uji Krafio', "Halo {{name}},\n\nIni email uji dari pengaturan SMTP Krafio ({{time}}). Jika Anda menerima email ini, SMTP berfungsi.", 'Krafio test email', "Hi {{name}},\n\nThis is a test email from Krafio SMTP settings ({{time}})."],
        ];
        // Siklus langganan & affiliate (blueprint) — menggantikan teks pengingat lama
        $mails = \App\Support\LifecycleMail::TEMPLATES + $mails;
        foreach ($mails as $k => [$sid, $bid, $sen, $ben]) {
            EmailTemplate::updateOrCreate(['key' => $k], ['subject_id' => $sid, 'body_id' => $bid, 'subject_en' => $sen, 'body_en' => $ben, 'is_active' => true]);
        }
        // Pemetaan event → template WhatsApp yang disetujui Meta. Nama meta_name WAJIB sama dengan template di WhatsApp Manager.
        $wa = [
            'otp' => ['krafio_otp', 'Kode verifikasi Krafio Anda: {{1}}. Jangan bagikan kode ini.', ['code']],
            'topup_success' => ['krafio_topup_success', 'Halo {{1}}, top-up {{2}} berhasil. Ref: {{3}}.', ['name', 'amount', 'reference']],
            'subscription_active' => ['krafio_subscription_active', 'Halo {{1}}, paket {{2}} aktif sampai {{3}}.', ['name', 'plan', 'ends_at']],
            'withdrawal_paid' => ['krafio_withdrawal_paid', 'Halo {{1}}, withdrawal {{2}} telah ditransfer. Ref: {{3}}.', ['name', 'amount', 'reference']],
        ];
        foreach ($wa as $event => [$name, $body, $vars]) {
            \App\Models\WaTemplate::updateOrCreate(['event' => $event], ['meta_name' => $name, 'language' => 'id', 'body_preview' => $body, 'variables' => $vars, 'is_active' => true]);
        }
        // Siklus langganan, affiliate, refund: NONAKTIF sampai template disetujui Meta (Admin → Template WA → aktifkan)
        foreach (array_diff_key(\App\Support\LifecycleMail::WA, $wa) as $event => [$name, $body, $vars]) {
            \App\Models\WaTemplate::firstOrCreate(['event' => $event], ['meta_name' => $name, 'language' => 'id', 'body_preview' => $body, 'variables' => $vars, 'is_active' => false]);
        }
        // Prompt Library bawaan (dibaca AI Gateway; admin bisa membuat versi baru tanpa mengubah kode)
        foreach (\App\Support\AiPromptDefaults::PROMPTS as $key => [$name, $template]) {
            AiPrompt::firstOrCreate(['key' => $key], ['name' => $name, 'template' => $template, 'version' => '1.0', 'status' => 'active']);
        }
    }

    /** Akun demo khusus lokal untuk menguji User Panel; saldo awal dicatat sebagai entri ledger. */
    private function demoUser(): void
    {
        if (User::where('email', 'demo@krafio.local')->exists()) {
            return;
        }
        $u = new User(['name' => 'Demo Kreator', 'email' => 'demo@krafio.local', 'password' => 'DemoKrafio2026', 'phone' => '+6281200000001']);
        $u->forceFill(['role' => 'user', 'status' => 'active', 'email_verified_at' => now(), 'referral_code' => 'DEMO2026', 'ai_consent_at' => now()])->save();
        AuthController::grantFreePlan($u);
        Wallet::post($u->id, 'topup', 'credit', 250000, 'Saldo awal demo (seeder lokal)', ['seed', null]);
        Wallet::post($u->id, 'commission', 'credit', 150000, 'Komisi demo (seeder lokal)', ['seed', null]);
        $this->command?->info('Akun demo: demo@krafio.local / DemoKrafio2026');
    }
}
