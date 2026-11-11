<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Media;
use App\Models\Template;
use App\Models\Withdrawal;
use App\Services\Audit;
use App\Services\Settings;
use App\Services\Wallet;
use App\Support\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Konten visual dashboard: Hero Showcase (slide sorotan user & admin), media publik
 * (gambar hero dan cover template), urutan/duplikat template, dan data kartu dompet.
 */
class ShowcaseController extends Controller
{
    public const AUDIENCES = ['user', 'admin'];

    /** Slide bawaan memakai ilustrasi di /assets/img agar dashboard tidak kosong sebelum Admin mengisi. */
    public static function defaults(string $audience): array
    {
        if ($audience === 'admin') {
            return [
                ['id' => 'a1', 'enabled' => true, 'badge' => 'Inbox', 'title' => 'Balas pelanggan tanpa pindah halaman', 'body' => 'Pesan baru muncul sebagai notifikasi dengan kolom balas cepat di seluruh panel admin.', 'cta_label' => 'Buka Inbox', 'cta_url' => '/admin/support/inbox', 'image' => '/assets/img/pages/contact.svg', 'alt' => 'Ilustrasi percakapan dukungan pelanggan'],
                ['id' => 'a2', 'enabled' => true, 'badge' => 'Template', 'title' => 'Kurasi template dengan pratinjau langsung', 'body' => 'Ubah judul, harga akses, dan cover — kartu di homepage ikut berubah saat Anda mengetik.', 'cta_label' => 'Buka Template Studio', 'cta_url' => '/admin/templates/studio', 'image' => '/assets/img/features/templates.svg', 'alt' => 'Ilustrasi kumpulan template'],
                ['id' => 'a3', 'enabled' => true, 'badge' => 'Keuangan', 'title' => 'Pantau kewajiban saldo pengguna', 'body' => 'Saldo top-up, komisi, dan withdrawal dalam proses selalu terhitung dari ledger.', 'cta_label' => 'Laporan keuangan', 'cta_url' => '/admin/finance/reports', 'image' => '/assets/img/auth/b.svg', 'alt' => 'Ilustrasi dua kartu saldo'],
            ];
        }

        return [
            ['id' => 'u1', 'enabled' => true, 'badge' => 'Baru', 'title' => 'Foto produk siap jual dari satu prompt', 'body' => 'Tulis deskripsi produk Anda, pilih rasio, dan lihat biaya kredit sebelum Generate.', 'cta_label' => 'Buat foto', 'cta_url' => '/app/create/photo', 'image' => '/assets/img/samples/product-kopi.svg', 'alt' => 'Kopi susu gula aren di gelas kaca'],
            ['id' => 'u2', 'enabled' => true, 'badge' => '24 template', 'title' => 'Mulai lebih cepat dengan template', 'body' => 'Feed Instagram, menu digital, sampai company profile — tinggal ganti teks dan gambar.', 'cta_label' => 'Lihat template', 'cta_url' => '/app/templates', 'image' => '/assets/img/templates/menu-digital.svg', 'alt' => 'Template menu digital'],
            ['id' => 'u3', 'enabled' => true, 'badge' => 'Website', 'title' => 'Website bisnis tanpa ngoding', 'body' => 'Isi formulir singkat, sunting blok, lalu terbitkan. Validasi SEO dasar dijalankan sebelum terbit.', 'cta_label' => 'Buat website', 'cta_url' => '/app/websites', 'image' => '/assets/img/samples/site-cafe.svg', 'alt' => 'Mock website kafe'],
        ];
    }

    private static function slides(string $audience): array
    {
        $v = Settings::get("dashboard.hero.{$audience}");

        return is_array($v) && count($v) ? array_values($v) : self::defaults($audience);
    }

    public function hero(Request $r)
    {
        $aud = $r->user()->isAdmin() && $r->query('audience') !== 'user' ? 'admin' : 'user';

        return ['data' => [
            'slides' => array_values(array_filter(self::slides($aud), fn ($s) => $s['enabled'] ?? true)),
            'config' => Settings::get("dashboard.hero_config.{$aud}", []),
        ]];
    }

    public function heroAdmin(Request $r)
    {
        $aud = in_array($r->query('audience'), self::AUDIENCES, true) ? $r->query('audience') : 'user';

        return ['data' => ['audience' => $aud, 'slides' => self::slides($aud), 'config' => Settings::get("dashboard.hero_config.{$aud}", []), 'defaults' => self::defaults($aud)]];
    }

    public function saveHero(Request $r)
    {
        $d = $r->validate([
            'audience' => 'required|in:user,admin',
            'slides' => 'present|array|max:8',
            'slides.*.id' => 'required|string|max:40',
            'slides.*.enabled' => 'boolean',
            'slides.*.badge' => 'nullable|string|max:24',
            'slides.*.title' => 'required|string|max:90',
            'slides.*.body' => 'nullable|string|max:220',
            'slides.*.cta_label' => 'nullable|string|max:32',
            'slides.*.cta_url' => ['nullable', 'string', 'max:255', 'regex:/^(\/[^\s]*|https:\/\/[^\s]+)$/'],
            'slides.*.image' => 'nullable|string|max:255',
            'slides.*.alt' => 'nullable|string|max:140',
            'config' => 'nullable|array',
            'config.imageEffect' => 'nullable|in:none,zoom,parallax,tilt',
            'config.autoPlay' => 'nullable|boolean',
            'config.interval' => 'nullable|integer|min:3000|max:20000',
            'config.float' => 'nullable|boolean',
            'config.showDots' => 'nullable|boolean',
            'config.showArrows' => 'nullable|boolean',
            'config.lightbox' => 'nullable|boolean',
            'config.animationSpeed' => 'nullable|numeric|min:0|max:2',
        ]);
        Settings::set("dashboard.hero.{$d['audience']}", $d['slides']);
        Settings::set("dashboard.hero_config.{$d['audience']}", $d['config'] ?? []);
        Audit::log('admin.hero.updated', null, ['audience' => $d['audience'], 'slides' => count($d['slides'])]);

        return $this->heroAdmin(new Request(['audience' => $d['audience']]));
    }

    /* ---------------- Media publik ---------------- */

    public function upload(Request $r)
    {
        $r->validate(['file' => 'required|file|max:6144|mimetypes:image/jpeg,image/png,image/webp,image/gif']);
        $f = $r->file('file');
        $path = $f->store('media/'.now()->format('Y/m'), 'local');
        [$w, $h] = @getimagesize($f->getRealPath()) ?: [null, null];
        $m = Media::create(['user_id' => $r->user()->id, 'path' => $path, 'original_name' => mb_substr($f->getClientOriginalName(), 0, 200), 'mime' => $f->getMimeType(), 'size' => $f->getSize(), 'width' => $w, 'height' => $h]);
        if ($r->user()->isAdmin()) {
            Audit::log('admin.media.uploaded', $m);
        }

        return response()->json(['data' => ['id' => $m->id, 'url' => $m->url(), 'width' => $w, 'height' => $h, 'name' => $m->original_name]], 201);
    }

    /** Disajikan lewat /api agar ikut proxy dev & tunnel; cache panjang karena path berisi id unik. */
    public function serve(Media $media, ?string $name = null)
    {
        // Nama berkas acak (40 karakter) wajib cocok: id berurutan saja tidak cukup untuk membuka media
        abort_unless($name !== null && hash_equals(basename($media->path), rawurldecode($name)) && Storage::disk('local')->exists($media->path), 404);

        return response()->file(Storage::disk('local')->path($media->path), ['Content-Type' => $media->mime, 'Cache-Control' => 'public, max-age=31536000, immutable', 'X-Content-Type-Options' => 'nosniff']);
    }

    /* ---------------- Template studio ---------------- */

    public function reorderTemplates(Request $r)
    {
        $ids = $r->validate(['ids' => 'required|array|min:1|max:500', 'ids.*' => 'integer'])['ids'];
        DB::transaction(function () use ($ids) {
            foreach (array_values($ids) as $i => $id) {
                Template::where('id', $id)->update(['sort' => $i + 1]);
            }
        });
        Audit::log('admin.templates.reordered', null, ['count' => count($ids)]);

        return response()->noContent();
    }

    public function duplicateTemplate(Request $r, Template $template)
    {
        $copy = $template->replicate(['usage_count']);
        $copy->title_id = Str::limit($template->title_id.' (salinan)', 160, '');
        $copy->title_en = $template->title_en ? Str::limit($template->title_en.' (copy)', 160, '') : null;
        $copy->status = 'draft';
        $copy->is_featured = false;
        $copy->usage_count = 0;
        $copy->sort = $template->sort;
        $copy->created_by = $r->user()->id;
        $copy->save();
        Template::where('sort', '>=', $template->sort)->where('id', '!=', $copy->id)->where('id', '!=', $template->id)->increment('sort');
        Audit::log('admin.templates.duplicated', $copy, ['from' => $template->id]);

        return response()->json(['data' => $copy->fresh()->toArray()], 201);
    }

    /* ---------------- Kartu dompet ---------------- */

    public function walletCards(Request $r)
    {
        $u = $r->user();
        if ($u->isAdmin()) {
            $latest = fn (string $w) => (int) DB::table('wallet_ledger as l')->whereRaw('l.id = (select max(id) from wallet_ledger x where x.user_id = l.user_id and x.wallet = ?)', [$w])->sum('balance_after');

            return ['data' => [
                ['key' => 'topup', 'label' => 'Kewajiban saldo top-up', 'amount' => $latest('topup'), 'holder' => 'Seluruh pengguna', 'ref' => 'LEDGER-TOPUP', 'tone' => 'topup', 'meta' => ['Top-up hari ini' => (int) DB::table('payments')->where('status', 'paid')->where('purpose', 'topup')->where('paid_at', '>=', now()->startOfDay())->sum('amount')]],
                ['key' => 'commission', 'label' => 'Kewajiban saldo komisi', 'amount' => $latest('commission'), 'holder' => 'Affiliate', 'ref' => 'LEDGER-KOMISI', 'tone' => 'commission', 'meta' => ['Komisi bulan ini' => (int) Commission::whereNotIn('status', ['reversed'])->where('created_at', '>=', now()->startOfMonth())->sum('amount')]],
                ['key' => 'withdraw', 'label' => 'Withdrawal dalam proses', 'amount' => (int) Withdrawal::whereIn('status', ['requested', 'under_review', 'approved', 'processing'])->sum('amount'), 'holder' => 'Antrean penarikan', 'ref' => 'WD-QUEUE', 'tone' => 'warning', 'meta' => ['Jumlah pengajuan' => Withdrawal::whereIn('status', ['requested', 'under_review', 'approved', 'processing'])->count().' pengajuan']],
            ]];
        }

        $code = str_pad((string) $u->id, 8, '0', STR_PAD_LEFT);
        $ref = 'KRF '.substr($code, 0, 4).' '.substr($code, 4, 4);

        return ['data' => [
            ['key' => 'topup', 'label' => 'Saldo top-up', 'amount' => Wallet::balance($u->id, 'topup'), 'holder' => $u->name, 'ref' => $ref, 'tone' => 'topup', 'meta' => ['Dapat ditarik' => 'Tidak', 'Dipakai untuk' => 'Paket & add-on']],
            ['key' => 'commission', 'label' => 'Saldo komisi', 'amount' => Wallet::balance($u->id, 'commission'), 'holder' => $u->name, 'ref' => $u->referral_code ? 'REF '.$u->referral_code : $ref, 'tone' => 'commission', 'meta' => ['Dalam proses tarik' => Wallet::reserved($u->id), 'Minimal tarik' => (int) Settings::get('wallet.withdraw_min', 100000)]],
        ]];
    }

    public function ensureAdmin(Request $r): void
    {
        if (! $r->user()->isAdmin()) {
            throw new ApiException('forbidden', 'Khusus admin.', 403);
        }
    }
}
