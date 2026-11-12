<?php

namespace App\Services;

use App\Models\AiJob;
use App\Models\AiModel;
use App\Models\AiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AI Gateway (PRD 7.1, 26.1): satu pintu untuk semua panggilan AI — routing per kapabilitas,
 * fallback berdasarkan prioritas, pencatatan token/biaya, dan kill switch lewat status provider.
 *
 * Driver yang tersedia: `openai_compatible` (endpoint /chat/completions) untuk teks.
 * Bila belum ada provider aktif untuk kapabilitas tertentu, gateway mengembalikan hasil SIMULASI yang
 * ditandai jelas — tidak pernah berpura-pura sebagai hasil model sungguhan.
 */
class AiGateway
{
    public const CAPABILITY = [
        'photo_generate' => 'image', 'photo_edit' => 'image', 'video_generate' => 'video', 'video_edit' => 'video',
        'video_clipper' => 'video', 'content_brainstorm' => 'text', 'assistant' => 'text',
    ];

    /** @return array{0: ?AiProvider, 1: ?AiModel}[] */
    public static function candidates(string $capability): array
    {
        $out = [];
        $providers = AiProvider::where('status', 'active')->orderBy('priority')->get()
            ->filter(fn ($p) => in_array($capability, $p->capabilities ?? [], true) && ($p->driver !== 'openai_compatible' || $p->api_key) && ($p->driver === 'openai_compatible' || $p->base_url));
        foreach ($providers as $p) {
            if ($p->monthly_token_limit && $p->tokens_used >= $p->monthly_token_limit) {
                continue;
            }
            $model = AiModel::where('provider_id', $p->id)->where('capability', $capability)->where('is_active', true)->orderByDesc('is_fallback')->first();
            if ($model) {
                $out[] = [$p, $model];
            }
        }

        return $out;
    }

    public static function configured(string $capability): bool
    {
        return count(self::candidates($capability)) > 0;
    }

    /** Panggilan teks; mengembalikan null bila semua provider gagal/tidak tersedia. */
    public static function chat(array $messages, int $maxTokens = 700): ?array
    {
        foreach (self::candidates('text') as [$p, $m]) {
            try {
                $res = Http::timeout(45)->withToken($p->api_key)->acceptJson()
                    ->post(rtrim($p->base_url ?: 'https://api.openai.com/v1', '/').'/chat/completions', [
                        'model' => $m->model_id,
                        'messages' => $messages,
                        'max_tokens' => $maxTokens,
                    ]);
                if ($res->successful()) {
                    $tokens = (int) data_get($res->json(), 'usage.total_tokens', 0);
                    // Biaya provider (Rp per 1.000 token di model) → dasar margin AI
                    $cost = (int) ceil($tokens / 1000 * (int) $m->cost_idr);
                    $p->increment('tokens_used', $tokens);
                    $p->update(['health' => 'healthy', 'last_checked_at' => now(), 'cost_month' => (int) $p->cost_month + $cost]);

                    return ['text' => (string) data_get($res->json(), 'choices.0.message.content', ''), 'provider_id' => $p->id, 'cost' => $cost];
                }
                $p->update(['health' => 'degraded', 'last_checked_at' => now()]);
            } catch (\Throwable $e) {
                Log::warning('AI provider gagal', ['provider' => $p->id, 'error' => $e->getMessage()]);
                $p->update(['health' => 'down', 'last_checked_at' => now()]);
            }
        }

        return null;
    }

    public static function process(AiJob $job): void
    {
        if (! in_array($job->status, ['queued', 'processing'], true)) {
            return;
        }
        $job->update(['status' => 'processing', 'progress' => 15, 'started_at' => $job->started_at ?? now()]);
        $cap = self::CAPABILITY[$job->type] ?? 'text';

        if ($cap === 'text') {
            $p = $job->params ?? [];
            $n = (int) ($p['ideas'] ?? 5);
            // Prompt Library (Admin → AI → Prompt): versi aktif tertinggi; fallback ke teks bawaan
            $vars = ['n' => $n, 'platform' => $p['platform'] ?? 'instagram', 'tone' => $p['tone'] ?? 'friendly', 'topic' => $job->prompt];
            $res = self::chat([
                ['role' => 'system', 'content' => Ai\Prompts::render('brainstorm.system', $vars, 'Anda asisten konten pemasaran untuk UMKM Indonesia. Jawab dalam bahasa Indonesia. Berikan daftar bernomor tanpa pembuka.')],
                ['role' => 'user', 'content' => Ai\Prompts::render('brainstorm.default', $vars, 'Buat {{n}} ide konten untuk {{platform}} dengan nada {{tone}} tentang: {{topic}}')],
            ]);
            if ($res) {
                $items = array_values(array_filter(array_map(fn ($l) => trim(preg_replace('/^\s*\d+[\.\)]\s*/', '', $l)), preg_split('/\r?\n/', $res['text']))));
                $job->update(['status' => 'completed', 'progress' => 100, 'provider_id' => $res['provider_id'], 'provider_cost' => $res['cost'] ?? 0, 'completed_at' => now(), 'result' => ['items' => array_slice($items, 0, $n)]]);

                return;
            }
            $job->update(['status' => 'completed', 'progress' => 100, 'completed_at' => now(), 'result' => [
                'items' => self::simulatedIdeas($job->prompt, $p['platform'] ?? 'instagram', $n),
                'note' => 'Simulasi: belum ada provider AI teks yang aktif. Admin dapat menambahkannya di AI Management.',
            ]]);

            return;
        }

        // Gambar/video: provider aktif (ComfyUI / HTTP kustom) dicoba berurutan sesuai prioritas (fallback otomatis).
        $candidates = self::candidates($cap);
        if ($candidates) {
            self::processMedia($job, $cap, $candidates);

            return;
        }

        // Belum ada provider media → pratinjau simulasi deterministik.
        $seed = crc32($job->prompt.$job->id);
        $count = max(1, min(4, (int) (($job->params ?? [])['count'] ?? 1)));
        $palette = [];
        for ($i = 0; $i < $count * 3; $i++) {
            $palette[] = sprintf('#%06X', ($seed * ($i + 7) * 2654435761) & 0xFFFFFF);
        }
        // Gambar sampel deterministik (ilustrasi vektor di frontend/public/assets/img/samples) agar pratinjau terlihat nyata.
        // Foto produk studio resmi (frontend/public/assets/img) — semua file benar-benar ada.
        $samples = ['samples/kopi-susu-meja-kayu.jpg', 'templates/roti-artisan-pagi.jpg', 'templates/batik-modern-flatlay.jpg', 'templates/sepatu-kanvas-lokal.jpg', 'templates/skincare-botol-kaca.jpg', 'templates/sambal-kemasan.jpg'];
        $images = [];
        for ($i = 0; $i < $count; $i++) {
            $images[] = '/assets/img/'.$samples[($seed + $i * 7) % count($samples)];
        }
        $job->update(['status' => 'completed', 'progress' => 100, 'completed_at' => now(), 'result' => [
            'preview_palette' => $palette,
            'preview_images' => $images,
            'note' => 'Pratinjau simulasi: provider AI gambar/video belum dikonfigurasi. Hasil sungguhan muncul setelah Admin mengaktifkan provider (mis. ComfyUI) di AI Management.',
        ]]);
    }

    /**
     * Jalankan job gambar/video di provider nyata. Gagal di satu provider → coba berikutnya; semua gagal →
     * job gagal + kredit dikembalikan sekali (blueprint "Pemrosesan Job AI" langkah 6 & 12).
     *
     * @param  array<int, array{0: AiProvider, 1: AiModel}>  $candidates
     */
    private static function processMedia(AiJob $job, string $cap, array $candidates): void
    {
        $user = $job->user;
        $limits = $user ? Entitlements::grants($user)['limits'] : [];
        $vars = ['prompt' => $job->prompt, 'style' => ($job->params ?? [])['style'] ?? 'product', 'operation' => ($job->params ?? [])['operation'] ?? 'inpaint', 'ratio' => ($job->params ?? [])['ratio'] ?? '1:1', 'duration' => ($job->params ?? [])['duration'] ?? 5];
        $promptKey = ($cap === 'video' ? 'video.' : 'image.').$job->type;
        $prompt = Ai\Prompts::render($promptKey, $vars, '{{prompt}}');
        $errors = [];
        foreach ($candidates as $i => [$p, $m]) {
            try {
                $job->update(['progress' => min(90, 30 + $i * 20), 'provider_id' => $p->id]);
                $files = Ai\MediaDrivers::generate($p, $m, $job, Ai\MediaDrivers::vars($job, $prompt, (int) ($limits['photo_max_px'] ?? 4096)));
                $stored = Ai\Outputs::store($job, $files, ! empty($limits['watermark']));
                $cost = (int) $m->cost_idr * count($files);
                $p->update(['health' => 'healthy', 'last_checked_at' => now(), 'cost_month' => (int) $p->cost_month + $cost]);
                $job->update(['status' => 'completed', 'progress' => 100, 'completed_at' => now(), 'provider_cost' => $cost, 'error' => null,
                    'result' => ['media' => $stored['media'], 'preview_images' => $stored['preview_images'], 'files' => $stored['files'], 'fallback' => $i > 0]]);

                return;
            } catch (\Throwable $e) {
                $errors[] = $p->name.': '.$e->getMessage();
                Log::warning('Provider media gagal', ['provider' => $p->id, 'job' => $job->id, 'error' => $e->getMessage()]);
                $p->update(['health' => 'down', 'last_checked_at' => now()]);
            }
        }
        $job->update(['status' => 'failed', 'progress' => 100, 'completed_at' => now(), 'error' => 'Semua penyedia AI gagal memproses. Kredit sudah dikembalikan.']);
        \App\Http\Controllers\Api\StudioController::refundJob($job, 'Job #'.$job->id.' gagal di semua penyedia AI');
        Audit::log('ai.job_failed_all_providers', $job, ['errors' => array_map(fn ($e) => mb_substr($e, 0, 200), $errors)], null);
    }

    private static function simulatedIdeas(string $topic, string $platform, int $n): array
    {
        $patterns = [
            'Cerita di balik proses pembuatan %s — tunjukkan tangan dan bahan, bukan hanya hasil akhir.',
            'Perbandingan sebelum dan sesudah memakai %s, dengan satu angka yang bisa diverifikasi pelanggan.',
            'Tiga kesalahan umum saat memilih %s dan cara menghindarinya.',
            'Satu hari bersama tim yang mengerjakan %s, format video pendek vertikal.',
            'Tanya jawab singkat: pertanyaan yang paling sering diajukan pelanggan tentang %s.',
            'Panduan pemakaian %s dalam 30 detik untuk pengguna baru.',
            'Ajakan ulasan: minta pelanggan membagikan foto %s mereka dengan tagar khusus.',
            'Promo terbatas %s dengan tenggat jelas dan syarat yang ditulis lengkap.',
        ];
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = sprintf($patterns[$i % count($patterns)], $topic)." ({$platform})";
        }

        return $out;
    }
}
