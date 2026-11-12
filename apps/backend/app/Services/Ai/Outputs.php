<?php

namespace App\Services\Ai;

use App\Models\AiJob;
use Illuminate\Support\Facades\Storage;

/**
 * Simpan hasil AI ke storage privat (ai-outputs/{user}/{job}/{n}.{ext}) dan watermark gambar untuk paket
 * yang punya batas `watermark` (paket Gratis). Hasil disajikan lewat GET /api/app/jobs/{job}/files/{n} (pemilik saja).
 */
class Outputs
{
    public const EXT = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif', 'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'];

    /**
     * @param  array<int, array{bytes:string, mime:string}>  $files
     * @return array{files: array<int, array{path:string, mime:string, kind:string}>, media: array<int, array{url:string, kind:string}>, preview_images: string[]}
     */
    public static function store(AiJob $job, array $files, bool $watermark): array
    {
        $out = ['files' => [], 'media' => [], 'preview_images' => []];
        foreach (array_values($files) as $i => $f) {
            $mime = $f['mime'];
            $kind = str_starts_with($mime, 'video/') ? 'video' : 'image';
            $bytes = $kind === 'image' && $watermark ? self::watermark($f['bytes'], $mime) : $f['bytes'];
            $path = 'ai-outputs/'.$job->user_id.'/'.$job->id.'/'.$i.'.'.(self::EXT[$mime] ?? 'bin');
            Storage::disk('local')->put($path, $bytes);
            $url = '/api/app/jobs/'.$job->id.'/files/'.$i;
            $out['files'][] = ['path' => $path, 'mime' => $mime, 'kind' => $kind];
            $out['media'][] = ['url' => $url, 'kind' => $kind];
            if ($kind === 'image') {
                $out['preview_images'][] = $url;
            }
        }

        return $out;
    }

    /** Tanda "Krafio" semi transparan di pojok kanan bawah (GD, tanpa font eksternal). */
    public static function watermark(string $bytes, string $mime): string
    {
        $img = @imagecreatefromstring($bytes);
        if (! $img) {
            return $bytes;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $text = 'Krafio';
        $font = 5;
        $tw = imagefontwidth($font) * strlen($text);
        $th = imagefontheight($font);
        $stamp = imagecreatetruecolor($tw + 8, $th + 4);
        imagealphablending($stamp, false);
        imagesavealpha($stamp, true);
        imagefill($stamp, 0, 0, imagecolorallocatealpha($stamp, 0, 0, 0, 80));
        imagestring($stamp, $font, 4, 2, $text, imagecolorallocatealpha($stamp, 255, 255, 255, 30));
        $scale = max(1, (int) round($w / 6 / ($tw + 8)));
        $sw = ($tw + 8) * $scale;
        $sh = ($th + 4) * $scale;
        imagealphablending($img, true);
        imagecopyresampled($img, $stamp, max(0, $w - $sw - (int) ($w * 0.03)), max(0, $h - $sh - (int) ($h * 0.03)), 0, 0, $sw, $sh, $tw + 8, $th + 4);
        ob_start();
        match ($mime) {
            'image/jpeg' => imagejpeg($img, null, 90),
            'image/webp' => imagewebp($img, null, 90),
            'image/gif' => imagegif($img),
            default => imagepng($img),
        };

        return (string) ob_get_clean();
    }
}
