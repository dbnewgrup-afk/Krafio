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
            ->filter(fn ($p) => in_array($capability, $p->capabilities ?? [], true) && (in_array($p->driver, ['comfyui', 'custom_http'], true) ? (bool) $p->base_url : (bool) $p->api_key));
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

    /**
     * Panggilan teks (OpenAI-compatible /chat/completions); mengembalikan null bila semua provider gagal/tidak tersedia.
     * $opts: json (minta objek JSON), timeout (detik), temperature.
     */
    public static function chat(array $messages, int $maxTokens = 700, array $opts = []): ?array
    {
        foreach (self::candidates('text') as [$p, $m]) {
            $url = rtrim($p->base_url ?: 'https://api.openai.com/v1', '/').'/chat/completions';
            $body = ['model' => $m->model_id, 'messages' => $messages, 'max_tokens' => $maxTokens, 'temperature' => $opts['temperature'] ?? 0.8];
            if (isset($opts['frequency_penalty'])) {
                $body['frequency_penalty'] = (float) $opts['frequency_penalty']; // cegah model kecil mengulang baris yang sama
            }
            if (! empty($opts['json'])) {
                $body['response_format'] = ['type' => 'json_object'];
            }
            // Model lokal (Ollama/Jan) di CPU jauh lebih lambat daripada API cloud
            $timeout = (int) ($opts['timeout'] ?? (self::isLocal($p) ? config('krafio.ai_local_timeout', 600) : config('krafio.ai_text_timeout', 120)));
            try {
                $req = fn (array $b) => Http::timeout($timeout)->connectTimeout(10)->withToken((string) $p->api_key)->acceptJson()->post($url, $b);
                $res = $req($body);
                if ($res->status() === 400 && isset($body['response_format'])) {
                    unset($body['response_format']); // provider tidak mendukung mode JSON → instruksi prompt saja
                    $res = $req($body);
                }
                if ($res->successful()) {
                    $tokens = (int) data_get($res->json(), 'usage.total_tokens', 0);
                    // Biaya provider (Rp per 1.000 token di model) → dasar margin AI
                    $cost = (int) ceil($tokens / 1000 * (int) $m->cost_idr);
                    $p->increment('tokens_used', $tokens);
                    $p->update(['health' => 'healthy', 'last_checked_at' => now(), 'cost_month' => (int) $p->cost_month + $cost]);

                    return ['text' => (string) data_get($res->json(), 'choices.0.message.content', ''), 'provider_id' => $p->id, 'model' => $m->model_id, 'tokens' => $tokens, 'cost' => $cost];
                }
                Log::warning('AI provider menolak', ['provider' => $p->id, 'status' => $res->status(), 'body' => mb_substr($res->body(), 0, 300)]);
                $p->update(['health' => 'degraded', 'last_checked_at' => now()]);
            } catch (\Throwable $e) {
                Log::warning('AI provider gagal', ['provider' => $p->id, 'error' => $e->getMessage()]);
                $p->update(['health' => 'down', 'last_checked_at' => now()]);
            }
        }

        return null;
    }

    /** Provider berjalan di mesin sendiri (localhost/LAN) — dipakai untuk batas waktu yang lebih longgar. */
    public static function isLocal(AiProvider $p): bool
    {
        $host = (string) parse_url((string) $p->base_url, PHP_URL_HOST);

        return in_array($host, ['localhost', '127.0.0.1', '::1', 'host.docker.internal'], true) || str_ends_with($host, '.local');
    }

    public static function process(AiJob $job): void
    {
        if (! in_array($job->status, ['queued', 'processing'], true)) {
            return;
        }
        $job->update(['status' => 'processing', 'progress' => 15, 'started_at' => $job->started_at ?? now()]);
        $tool = \App\Support\AiTools::forType($job->type);
        // Kapabilitas dari registry AI Tools (sumber tunggal); peta lama hanya cadangan
        $cap = $tool['capability'] ?? self::CAPABILITY[$job->type] ?? 'text';
        if ($cap === 'text') {
            Ai\TextWriter::run($job, $tool ?? \App\Support\AiTools::get('content_brainstorm'));

            return;
        }

        if ($job->type === 'script_video') {
            Ai\VideoTools::scriptVideo($job);

            return;
        }
        // Edit video & clipper diproses lokal dengan FFmpeg (tanpa provider AI) bila tersedia
        if (in_array($job->type, ['video_edit', 'video_clipper'], true) && Ai\VideoTools::available()) {
            Ai\VideoTools::process($job);

            return;
        }
        // Edit foto dasar (upscale, enhance) bisa dikerjakan lokal; operasi AI (hapus latar, inpaint) butuh provider
        if ($job->type === 'photo_edit' && in_array(($job->params ?? [])['operation'] ?? '', Ai\ImageTools::LOCAL_OPS, true) && ! self::configured('image')) {
            Ai\ImageTools::process($job);

            return;
        }

        // Gambar/video: provider aktif (ComfyUI / HTTP kustom) dicoba berurutan sesuai prioritas (fallback otomatis).
        $candidates = self::candidates($cap);
        if ($candidates) {
            self::processMedia($job, $cap, $candidates);

            return;
        }

        // Tidak ada penyedia aktif: JANGAN membuat hasil contoh. Job gagal dan kredit dikembalikan.
        $job->update(['status' => 'failed', 'progress' => 100, 'completed_at' => now(), 'error' => 'Penyedia AI '.($cap === 'video' ? 'video' : 'gambar').' belum aktif. Kredit sudah dikembalikan.']);
        \App\Http\Controllers\Api\StudioController::refundJob($job, 'Job #'.$job->id.' tidak dapat diproses: penyedia AI belum aktif');
    }

    /** Tool dapat dijalankan sekarang? (ada penyedia aktif, atau diproses lokal: FFmpeg / upscale & enhance foto). */
    public static function canRun(array $tool, array $params): bool
    {
        if (in_array($tool['key'], ['video_edit', 'video_clipper', 'script_video'], true)) {
            return Ai\VideoTools::available();
        }
        if ($tool['key'] === 'photo_edit' && in_array($params['operation'] ?? '', Ai\ImageTools::LOCAL_OPS, true)) {
            return true;
        }

        return self::configured($tool['capability']);
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
        $tool = \App\Support\AiTools::forType($job->type);
        // Prompt media dirakit dari field form (label opsi, bukan kode) → bisa diubah admin di Prompt Library
        $vars = ['prompt' => $job->prompt] + ($tool ? Ai\TextWriter::vars($tool, $job->params ?? []) : []);
        $vars = array_map(fn ($v) => $v === '-' ? '' : $v, $vars);
        $promptKey = ($cap === 'video' ? 'video.' : 'image.').$job->type;
        $prompt = Ai\Prompts::render($promptKey, $vars, $tool['prompt_template'] ?? '{{prompt}}');
        $prompt = trim(preg_replace('/\s*[A-Za-z ]+:\s*\./u', '', $prompt) ?? $prompt); // buang "Label: ." yang kosong
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
}
