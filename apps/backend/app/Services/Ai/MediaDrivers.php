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
            'prompt' => $prompt, 'negative' => Prompts::render('image.negative', []), 'width' => $round($w), 'height' => $round($h),
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
