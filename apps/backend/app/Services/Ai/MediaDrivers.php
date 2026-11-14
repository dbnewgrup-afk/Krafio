<?php

namespace App\Services\Ai;

use App\Models\AiJob;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Upload;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Driver AI gambar/video. Setiap driver mengembalikan daftar hasil mentah
 * `[['bytes' => string, 'mime' => string], ...]`; menyimpan & watermark dilakukan Outputs.
 * Kegagalan → exception, sehingga AI Gateway mencoba provider berikutnya (fallback) lalu refund bila semua gagal.
 */
class MediaDrivers
{
    public const RATIO = ['1:1' => [1024, 1024], '4:5' => [896, 1120], '9:16' => [768, 1344], '16:9' => [1344, 768]];

    /** @return array<int, array{bytes:string, mime:string}> */
    public static function generate(AiProvider $p, AiModel $m, AiJob $job, array $vars): array
    {
        return match ($p->driver) {
            'comfyui' => self::comfy($p, $m, $job, $vars),
            'custom_http' => self::http($p, $m, $job, $vars),
            'openai_images' => self::openaiImages($p, $m, $job, $vars),
            'fal' => self::fal($p, $m, $job, $vars),
            'replicate' => self::replicate($p, $m, $job, $vars),
            default => throw new \RuntimeException("Driver {$p->driver} tidak mendukung gambar/video."),
        };
    }

    /** Variabel prompt & dimensi dari parameter job (dibatasi photo_max_px paket). */
    public static function vars(AiJob $job, string $prompt, int $maxPx): array
    {
        $p = $job->params ?? [];
        [$w, $h] = self::RATIO[$p['ratio'] ?? '1:1'] ?? self::RATIO['1:1'];
        $scale = min(1, $maxPx / max($w, $h));
        $round = fn ($v) => max(256, (int) (floor($v * $scale / 64) * 64));

        return [
            'prompt' => $prompt, 'negative' => trim(Prompts::render('image.negative', []).(! empty($p['negative']) ? ', '.$p['negative'] : ''), ', '), 'width' => $round($w), 'height' => $round($h),
            'seed' => crc32($job->id.'|'.$job->prompt) % 2147483647, 'count' => (int) ($p['count'] ?? 1), 'duration' => (int) ($p['duration'] ?? 5),
            'ratio' => $p['ratio'] ?? '1:1', 'style' => $p['style'] ?? 'product', 'operation' => $p['operation'] ?? 'inpaint', 'model' => '',
        ];
    }

    private static function client(AiProvider $p): PendingRequest
    {
        $c = Http::timeout(60)->acceptJson();

        return $p->api_key ? $c->withToken($p->api_key) : $c;
    }

    private static function base(AiProvider $p): string
    {
        $base = rtrim((string) $p->base_url, '/');
        if ($base === '') {
            throw new \RuntimeException('Base URL provider belum diisi.');
        }

        return $base;
    }

    /* ---------------- ComfyUI (self-host / hosted) ---------------- */
    private static function comfy(AiProvider $p, AiModel $m, AiJob $job, array $vars): array
    {
        $tpl = Prompts::template('comfyui.'.$job->type);
        if (! $tpl) {
            throw new \RuntimeException('Workflow ComfyUI untuk '.$job->type.' belum diisi di Prompt Library (key comfyui.'.$job->type.').');
        }
        $base = self::base($p);
        $vars['model'] = $m->model_id;
        if ($job->upload_id && ($up = Upload::find($job->upload_id))) {
            $res = self::client($p)->attach('image', Storage::disk($up->disk ?: 'local')->get($up->path), basename($up->path))->post($base.'/upload/image', ['overwrite' => 'true']);
            if (! $res->successful()) {
                throw new \RuntimeException('Unggah berkas sumber ke ComfyUI gagal (HTTP '.$res->status().').');
            }
            $vars['source_image'] = (string) $res->json('name');
        }
        $res = self::client($p)->post($base.'/prompt', ['prompt' => Prompts::workflow($tpl, $vars), 'client_id' => (string) Str::uuid()]);
        $pid = (string) $res->json('prompt_id');
        if (! $res->successful() || $pid === '') {
            throw new \RuntimeException('ComfyUI menolak workflow: '.Str::limit((string) ($res->json('error.message') ?? $res->body()), 200));
        }
        $deadline = microtime(true) + (int) config('krafio.ai_media_timeout', 100);
        $outputs = null;
        while (microtime(true) < $deadline) {
            $h = self::client($p)->get($base.'/history/'.$pid);
            $outputs = $h->json($pid.'.outputs');
            if (is_array($outputs) && $outputs) {
                break;
            }
            if ($h->json($pid.'.status.status_str') === 'error') {
                throw new \RuntimeException('ComfyUI gagal memproses workflow.');
            }
            usleep((int) config('krafio.ai_poll_ms', 2000) * 1000);
        }
        if (! is_array($outputs) || ! $outputs) {
            throw new \RuntimeException('ComfyUI tidak selesai dalam batas waktu.');
        }
        $files = [];
        foreach ($outputs as $node) {
            foreach (['images', 'gifs', 'videos'] as $kind) {
                foreach ((array) ($node[$kind] ?? []) as $f) {
                    if (($f['type'] ?? 'output') === 'temp') {
                        continue; // pratinjau sementara, bukan hasil akhir
                    }
                    $v = self::client($p)->get($base.'/view', ['filename' => $f['filename'] ?? '', 'subfolder' => $f['subfolder'] ?? '', 'type' => $f['type'] ?? 'output']);
                    if ($v->successful() && $v->body() !== '') {
                        $files[] = ['bytes' => $v->body(), 'mime' => self::mime($v->header('Content-Type'), (string) ($f['filename'] ?? ''))];
                    }
                }
            }
        }
        if (! $files) {
            throw new \RuntimeException('ComfyUI tidak menghasilkan berkas.');
        }

        return $files;
    }

    /* ---------------- HTTP kustom (kontrak JSON sederhana) ----------------
     * POST base_url  {job_id, type, model, prompt, params, width, height, seed, source: {mime, base64}|null}
     * → 200 {outputs: [{url} | {base64, mime}]}
     */
    private static function http(AiProvider $p, AiModel $m, AiJob $job, array $vars): array
    {
        $source = null;
        if ($job->upload_id && ($up = Upload::find($job->upload_id)) && $up->size <= 20 * 1024 * 1024) {
            $source = ['mime' => $up->mime, 'base64' => base64_encode(Storage::disk($up->disk ?: 'local')->get($up->path))];
        }
        $res = self::client($p)->timeout((int) config('krafio.ai_media_timeout', 100))->post(self::base($p), [
            'job_id' => $job->id, 'type' => $job->type, 'model' => $m->model_id, 'prompt' => $vars['prompt'], 'negative' => $vars['negative'],
            'params' => $job->params ?? [], 'width' => $vars['width'], 'height' => $vars['height'], 'seed' => $vars['seed'], 'source' => $source,
        ]);
        if (! $res->successful()) {
            throw new \RuntimeException('Provider HTTP gagal (HTTP '.$res->status().').');
        }
        $files = [];
        foreach ((array) $res->json('outputs') as $o) {
            if (! empty($o['base64'])) {
                $files[] = ['bytes' => (string) base64_decode((string) $o['base64'], true), 'mime' => (string) ($o['mime'] ?? 'image/png')];
            } elseif (! empty($o['url']) && preg_match('#^https?://#i', (string) $o['url'])) {
                $d = Http::timeout(60)->get((string) $o['url']);
                if ($d->successful()) {
                    $files[] = ['bytes' => $d->body(), 'mime' => self::mime($d->header('Content-Type'), (string) $o['url'])];
                }
            }
        }
        $files = array_values(array_filter($files, fn ($f) => $f['bytes'] !== ''));
        if (! $files) {
            throw new \RuntimeException('Provider HTTP tidak mengembalikan hasil.');
        }

        return $files;
    }

    /* ---------------- OpenAI Images (gpt-image-1 / dall-e-3) ----------------
     * Generate: POST {base}/images/generations. Edit (inpaint/ganti latar): POST {base}/images/edits (multipart).
     */
    private static function openaiImages(AiProvider $p, AiModel $m, AiJob $job, array $vars): array
    {
        $base = rtrim((string) ($p->base_url ?: 'https://api.openai.com/v1'), '/');
        $w = $vars['width'];
        $h = $vars['height'];
        $size = $w === $h ? '1024x1024' : ($h > $w ? '1024x1536' : '1536x1024');
        if (str_starts_with($m->model_id, 'dall-e-3')) {
            $size = $w === $h ? '1024x1024' : ($h > $w ? '1024x1792' : '1792x1024');
        }
        $client = Http::timeout((int) config('krafio.ai_media_timeout', 100) * 2)->withToken((string) $p->api_key)->acceptJson();
        $up = $job->upload_id ? Upload::find($job->upload_id) : null;
        if ($up && $job->type === 'photo_edit') {
            $res = $client->attach('image', Storage::disk($up->disk ?: 'local')->get($up->path), basename($up->path), ['Content-Type' => $up->mime])
                ->post($base.'/images/edits', ['model' => $m->model_id, 'prompt' => $vars['prompt'] ?: 'Perbaiki foto ini', 'size' => $size]);
        } else {
            $body = ['model' => $m->model_id, 'prompt' => $vars['prompt'], 'n' => str_starts_with($m->model_id, 'dall-e-3') ? 1 : max(1, min(4, $vars['count'])), 'size' => $size];
            if (str_starts_with($m->model_id, 'dall-e')) {
                $body['response_format'] = 'b64_json';
            }
            $res = $client->post($base.'/images/generations', $body);
        }
        if (! $res->successful()) {
            throw new \RuntimeException('OpenAI Images menolak (HTTP '.$res->status().'): '.Str::limit((string) $res->json('error.message'), 160));
        }
        $files = [];
        foreach ((array) $res->json('data') as $d) {
            if (! empty($d['b64_json'])) {
                $files[] = ['bytes' => (string) base64_decode($d['b64_json'], true), 'mime' => 'image/png'];
            } elseif (! empty($d['url'])) {
                $files[] = self::download((string) $d['url']);
            }
        }

        return self::nonEmpty($files, 'OpenAI Images');
    }

    /* ---------------- fal.ai (endpoint sinkron https://fal.run/{model_id}) ----------------
     * Model gambar (mis. fal-ai/flux/schnell), video (mis. fal-ai/kling-video/v1/standard/text-to-video),
     * hapus latar (fal-ai/birefnet). Berkas sumber dikirim sebagai data URI.
     */
    private static function fal(AiProvider $p, AiModel $m, AiJob $job, array $vars): array
    {
        $base = rtrim((string) ($p->base_url ?: 'https://fal.run'), '/');
        $input = ['prompt' => $vars['prompt'], 'negative_prompt' => $vars['negative'], 'image_size' => ['width' => $vars['width'], 'height' => $vars['height']],
            'num_images' => max(1, min(4, $vars['count'])), 'seed' => $vars['seed'], 'aspect_ratio' => $vars['ratio'], 'duration' => (string) $vars['duration']];
        if ($src = self::dataUri($job)) {
            $input['image_url'] = $src;
            $input['video_url'] = $src;
        }
        $res = Http::timeout((int) config('krafio.ai_media_timeout', 100) * 3)->withHeaders(['Authorization' => 'Key '.$p->api_key])->acceptJson()
            ->post($base.'/'.ltrim($m->model_id, '/'), array_filter($input, fn ($v) => $v !== '' && $v !== null));
        if (! $res->successful()) {
            throw new \RuntimeException('fal.ai menolak (HTTP '.$res->status().'): '.Str::limit((string) ($res->json('detail.0.msg') ?? $res->json('detail') ?? $res->body()), 160));
        }
        $urls = array_merge(array_column((array) $res->json('images'), 'url'), array_filter([$res->json('image.url'), $res->json('video.url')]));

        return self::nonEmpty(array_map(fn ($u) => self::download((string) $u), $urls), 'fal.ai');
    }

    /* ---------------- Replicate (POST /v1/models/{owner}/{name}/predictions, header Prefer: wait) ---------------- */
    private static function replicate(AiProvider $p, AiModel $m, AiJob $job, array $vars): array
    {
        $base = rtrim((string) ($p->base_url ?: 'https://api.replicate.com/v1'), '/');
        $client = Http::timeout(70)->withToken((string) $p->api_key)->acceptJson();
        $input = ['prompt' => $vars['prompt'], 'negative_prompt' => $vars['negative'], 'aspect_ratio' => $vars['ratio'], 'num_outputs' => max(1, min(4, $vars['count'])), 'seed' => $vars['seed']];
        if ($src = self::dataUri($job)) {
            $input['image'] = $src;
        }
        $res = $client->withHeaders(['Prefer' => 'wait=60'])->post($base.'/models/'.trim($m->model_id, '/').'/predictions', ['input' => array_filter($input, fn ($v) => $v !== '' && $v !== null)]);
        if (! $res->successful()) {
            throw new \RuntimeException('Replicate menolak (HTTP '.$res->status().'): '.Str::limit((string) ($res->json('detail') ?? $res->body()), 160));
        }
        $pred = $res->json();
        $deadline = microtime(true) + (int) config('krafio.ai_media_timeout', 100) * 3;
        while (! in_array($pred['status'] ?? '', ['succeeded', 'failed', 'canceled'], true) && microtime(true) < $deadline && ! empty($pred['urls']['get'])) {
            usleep((int) config('krafio.ai_poll_ms', 2000) * 1000);
            $pred = $client->get($pred['urls']['get'])->json();
        }
        if (($pred['status'] ?? '') !== 'succeeded') {
            throw new \RuntimeException('Replicate gagal: '.Str::limit((string) ($pred['error'] ?? $pred['status'] ?? 'timeout'), 160));
        }
        $out = is_array($pred['output']) ? $pred['output'] : [$pred['output']];

        return self::nonEmpty(array_map(fn ($u) => self::download((string) $u), array_filter($out, 'is_string')), 'Replicate');
    }

    private static function dataUri(AiJob $job): ?string
    {
        $up = $job->upload_id ? Upload::find($job->upload_id) : null;
        if (! $up || $up->size > 20 * 1024 * 1024) {
            return null;
        }

        return 'data:'.$up->mime.';base64,'.base64_encode(Storage::disk($up->disk ?: 'local')->get($up->path));
    }

    /** Unduh hasil dari URL https penyedia (bukan URL pengguna). */
    private static function download(string $url): array
    {
        if (! preg_match('#^https://#i', $url)) {
            return ['bytes' => '', 'mime' => 'image/png'];
        }
        $d = Http::timeout(120)->get($url);

        return ['bytes' => $d->successful() ? $d->body() : '', 'mime' => self::mime($d->header('Content-Type'), $url)];
    }

    private static function nonEmpty(array $files, string $name): array
    {
        $files = array_values(array_filter($files, fn ($f) => ($f['bytes'] ?? '') !== ''));
        if (! $files) {
            throw new \RuntimeException($name.' tidak mengembalikan hasil.');
        }

        return $files;
    }

    private static function mime(?string $header, string $name): string
    {
        $h = strtolower(trim(explode(';', (string) $header)[0]));
        if (preg_match('#^(image|video)/#', $h)) {
            return $h;
        }

        return match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif', 'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime',
            default => 'image/png',
        };
    }
}
