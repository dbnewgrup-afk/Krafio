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
 * Driver Google Gemini API (API key resmi dari Google AI Studio, diisi Admin).
 * - Gambar (Nano Banana, mis. `gemini-nano-banana-2.1`) & video (Gemini Omni, mis. `gemini-omni-1.2-flash`)
 *   lewat Interactions API: POST {base}/interactions — hasil ada di steps[type=model_output].content[type=image|video].
 * - Edit foto (hapus/ganti latar, inpaint): foto sumber dikirim sebagai input image (base64) + instruksi.
 * - Teks: endpoint OpenAI-compatible {base}/openai/chat/completions (ditangani AiGateway::chat).
 * Gagal → exception, sehingga AI Gateway mencoba provider berikutnya lalu refund bila semua gagal.
 */
class GeminiDriver
{
    public const BASE = 'https://generativelanguage.googleapis.com/v1beta';

    public const API_REVISION = '2026-05-20';

    public static function base(AiProvider $p): string
    {
        $b = rtrim((string) ($p->base_url ?: self::BASE), '/');

        // Admin kadang mengisi URL OpenAI-compatible; Interactions ada di /v1beta
        return preg_replace('#/openai$#', '', $b) ?? $b;
    }

    /** URL chat OpenAI-compatible untuk teks. */
    public static function chatUrl(AiProvider $p): string
    {
        return self::base($p).'/openai/chat/completions';
    }

    private static function client(AiProvider $p, int $timeout): PendingRequest
    {
        return Http::timeout($timeout)->connectTimeout(10)->acceptJson()
            ->withHeaders(['x-goog-api-key' => (string) $p->api_key, 'Api-Revision' => self::API_REVISION]);
    }

    /** @return array<int, array{bytes:string, mime:string}> */
    public static function generate(AiProvider $p, AiModel $m, AiJob $job, array $vars): array
    {
        $isVideo = $m->capability === 'video' || $job->type === 'video_generate';
        $input = [['type' => 'text', 'text' => $isVideo ? $vars['prompt'] : self::imagePrompt($job, $vars)]];
        $up = $job->upload_id ? Upload::find($job->upload_id) : null;
        if ($up && str_starts_with((string) $up->mime, 'image/') && $up->size <= 20 * 1024 * 1024) {
            $input[] = ['type' => 'image', 'mime_type' => $up->mime, 'data' => base64_encode(Storage::disk($up->disk ?: 'local')->get($up->path))];
        }
        $body = ['model' => $m->model_id, 'input' => $input, 'store' => false];
        if ($isVideo) {
            $ratio = in_array($vars['ratio'], ['9:16', '4:5'], true) ? '9:16' : '16:9';
            $body['response_format'] = ['type' => 'video', 'aspect_ratio' => $ratio, 'resolution' => '720p', 'duration' => max(3, min(15, (int) $vars['duration'])).'s', 'delivery' => 'uri'];
        } else {
            $body['response_format'] = ['type' => 'image', 'aspect_ratio' => $vars['ratio'] ?: '1:1', 'image_size' => max($vars['width'], $vars['height']) > 1100 ? '2K' : '1K'];
        }

        $count = $isVideo ? 1 : max(1, min(4, (int) $vars['count']));
        $files = [];
        for ($i = 0; $i < $count; $i++) {
            $timeout = (int) config('krafio.ai_media_timeout', 100) * ($isVideo ? 6 : 2);
            $res = self::client($p, $timeout)->post(self::base($p).'/interactions', $body);
            if (! $res->successful()) {
                throw new \RuntimeException('Gemini menolak (HTTP '.$res->status().'): '.Str::limit((string) ($res->json('error.message') ?? $res->body()), 160));
            }
            $files = array_merge($files, self::extract($p, (array) $res->json()));
        }
        $files = array_values(array_filter($files, fn ($f) => $f['bytes'] !== ''));
        if (! $files) {
            throw new \RuntimeException('Gemini tidak mengembalikan '.($isVideo ? 'video' : 'gambar').'.');
        }

        return $files;
    }

    /** Instruksi untuk edit foto (Gemini mengedit berdasarkan instruksi teks + foto sumber). */
    private static function imagePrompt(AiJob $job, array $vars): string
    {
        if ($job->type !== 'photo_edit') {
            return $vars['prompt'];
        }
        $instr = trim((string) (($job->params ?? [])['instruction'] ?? ''));

        return match ($vars['operation']) {
            'remove_bg' => 'Hapus seluruh latar belakang foto ini. Pertahankan objek utama persis seperti aslinya (bentuk, warna, detail). Latar polos putih bersih.',
            'replace_bg' => 'Ganti latar belakang foto ini: '.($instr ?: 'latar studio netral yang bersih').'. Pertahankan objek utama persis seperti aslinya, sesuaikan pencahayaan dan bayangan agar menyatu.',
            'inpaint' => 'Edit foto ini: '.($instr ?: $vars['prompt']).'. Bagian lain foto tetap sama persis.',
            default => $vars['prompt'],
        };
    }

    /**
     * Ambil gambar/video dari respons Interaction: steps[].content[] (type image/video, data base64 atau uri).
     * Bentuk cadangan (outputs[] / output_image / output_video) juga dibaca.
     *
     * @return array<int, array{bytes:string, mime:string}>
     */
    public static function extract(AiProvider $p, array $json): array
    {
        if (in_array($json['status'] ?? null, ['failed', 'cancelled'], true)) {
            throw new \RuntimeException('Gemini gagal: '.Str::limit((string) data_get($json, 'error.message', $json['status']), 160));
        }
        $blocks = [];
        foreach ((array) ($json['steps'] ?? []) as $step) {
            if (($step['type'] ?? 'model_output') === 'model_output') {
                $blocks = array_merge($blocks, (array) ($step['content'] ?? []));
            }
        }
        $blocks = array_merge($blocks, (array) ($json['outputs'] ?? []));
        foreach (['output_image', 'output_video'] as $k) {
            if (! empty($json[$k])) {
                $blocks[] = $json[$k] + ['type' => $k === 'output_video' ? 'video' : 'image'];
            }
        }
        $files = [];
        foreach ($blocks as $b) {
            if (! in_array($b['type'] ?? '', ['image', 'video'], true)) {
                continue;
            }
            $mime = (string) ($b['mime_type'] ?? ($b['type'] === 'video' ? 'video/mp4' : 'image/png'));
            if (! empty($b['data'])) {
                $files[] = ['bytes' => (string) base64_decode((string) $b['data'], true), 'mime' => $mime];
            } elseif (! empty($b['uri'])) {
                $files[] = ['bytes' => self::download($p, (string) $b['uri']), 'mime' => $mime];
            }
        }

        return $files;
    }

    /** Unduh berkas hasil dari Files API Gemini (menunggu status ACTIVE untuk video). */
    private static function download(AiProvider $p, string $uri): string
    {
        $id = preg_match('#files/([A-Za-z0-9_-]+)#', $uri, $mm) ? $mm[1] : null;
        if (! $id) {
            return '';
        }
        $base = self::base($p);
        for ($i = 0; $i < 60; $i++) {
            $state = (string) self::client($p, 30)->get($base.'/files/'.$id)->json('state', 'ACTIVE');
            if ($state === 'FAILED') {
                throw new \RuntimeException('Gemini gagal memproses berkas hasil.');
            }
            if ($state === 'ACTIVE') {
                break;
            }
            sleep(app()->runningUnitTests() ? 0 : 5);
        }
        $res = self::client($p, 300)->withOptions(['allow_redirects' => true])->get($base.'/files/'.$id.':download', ['alt' => 'media']);

        return $res->successful() ? $res->body() : '';
    }
}
