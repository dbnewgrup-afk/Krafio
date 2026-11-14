<?php

namespace App\Services\Ai;

use App\Http\Controllers\Api\StudioController;
use App\Models\AiJob;
use App\Models\Upload;
use App\Services\Entitlements;
use Illuminate\Support\Facades\Storage;

/**
 * Edit foto lokal tanpa provider AI: upscale 2× (Lanczos + unsharp) dan enhance (auto level, saturasi, ketajaman).
 * Operasi yang butuh model AI (hapus latar, ganti latar, inpaint) tetap lewat provider gambar di AI Gateway.
 * Memakai Imagick bila tersedia, GD sebagai cadangan.
 */
class ImageTools
{
    public const LOCAL_OPS = ['upscale', 'enhance'];

    public static function process(AiJob $job): void
    {
        $up = $job->upload_id ? Upload::find($job->upload_id) : null;
        if (! $up || ! Storage::disk($up->disk ?: 'local')->exists($up->path)) {
            self::fail($job, 'Berkas foto sumber tidak ditemukan.');

            return;
        }
        $bytes = Storage::disk($up->disk ?: 'local')->get($up->path);
        $p = $job->params ?? [];
        $fmt = in_array($p['output'] ?? 'png', ['png', 'jpg', 'webp'], true) ? $p['output'] : 'png';
        $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp'][$fmt];
        $maxPx = (int) (Entitlements::grants($job->user)['limits']['photo_max_px'] ?? 4096);
        try {
            $job->update(['progress' => 40]);
            $out = class_exists(\Imagick::class) ? self::imagick($bytes, $p['operation'], $fmt, $maxPx) : self::gd($bytes, $p['operation'], $fmt, $maxPx);
            $stored = Outputs::store($job, [['bytes' => $out, 'mime' => $mime]], ! empty(Entitlements::grants($job->user)['limits']['watermark']));
            $job->update(['status' => 'completed', 'progress' => 100, 'completed_at' => now(), 'error' => null, 'result' => $stored + ['engine' => 'local']]);
        } catch (\Throwable $e) {
            report($e);
            self::fail($job, 'Pemrosesan foto gagal: '.mb_substr($e->getMessage(), 0, 150));
        }
    }

    private static function imagick(string $bytes, string $op, string $fmt, int $maxPx): string
    {
        $im = new \Imagick;
        $im->readImageBlob($bytes);
        $im->autoOrient();
        if ($op === 'upscale') {
            $w = $im->getImageWidth();
            $h = $im->getImageHeight();
            $scale = min(2.0, $maxPx / max($w, $h));
            if ($scale > 1.01) {
                $im->resizeImage((int) round($w * $scale), (int) round($h * $scale), \Imagick::FILTER_LANCZOS, 1);
            }
            $im->unsharpMaskImage(0, 0.8, 0.6, 0.02);
        } else { // enhance
            $im->normalizeImage();
            $im->modulateImage(100, 112, 100);
            $im->unsharpMaskImage(0, 0.7, 0.5, 0.02);
        }
        $im->setImageFormat($fmt === 'jpg' ? 'jpeg' : $fmt);
        if ($fmt !== 'png') {
            $im->setImageCompressionQuality(92);
        }
        $out = $im->getImagesBlob();
        $im->clear();

        return $out;
    }

    private static function gd(string $bytes, string $op, string $fmt, int $maxPx): string
    {
        $src = @imagecreatefromstring($bytes) ?: throw new \RuntimeException('Format gambar tidak didukung.');
        $w = imagesx($src);
        $h = imagesy($src);
        if ($op === 'upscale') {
            $scale = min(2.0, $maxPx / max($w, $h));
            $nw = (int) round($w * max(1, $scale));
            $nh = (int) round($h * max(1, $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imageconvolution($dst, [[0, -1, 0], [-1, 5, -1], [0, -1, 0]], 1, 0);
        } else {
            $dst = $src;
            imagefilter($dst, IMG_FILTER_CONTRAST, -8);
            imageconvolution($dst, [[0, -1, 0], [-1, 5, -1], [0, -1, 0]], 1, 0);
        }
        ob_start();
        match ($fmt) {
            'jpg' => imagejpeg($dst, null, 92),
            'webp' => imagewebp($dst, null, 92),
            default => imagepng($dst),
        };

        return (string) ob_get_clean();
    }

    private static function fail(AiJob $job, string $msg): void
    {
        $job->update(['status' => 'failed', 'progress' => 100, 'completed_at' => now(), 'error' => mb_substr($msg, 0, 250)]);
        StudioController::refundJob($job, 'Job #'.$job->id.' gagal diproses');
    }
}
