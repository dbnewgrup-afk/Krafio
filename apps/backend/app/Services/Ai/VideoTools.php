<?php

namespace App\Services\Ai;

use App\Http\Controllers\Api\StudioController;
use App\Models\AiJob;
use App\Models\Upload;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Edit Video & Video Clipper berbasis FFmpeg (PRD 27.4–27.5; pola AutoClip/LosslessCut/Concat di register Bab 53).
 * Berjalan di worker antrean; argumen selalu array (tanpa shell) sehingga input pengguna tidak bisa disuntikkan.
 *
 * Clipper:
 *  - mode auto  : pilih N momen tanpa tumpang tindih (energi audio "loud", perubahan adegan "scene", atau merata "even");
 *  - mode manual: rentang waktu dari pengguna, mis. "0:30-1:05, 2:10-2:40".
 *  Tata letak crop / blur (video utuh di atas latar blur) / fit (bingkai hitam), 720p atau 1080p, fade, judul (ASS), thumbnail.
 * Durasi asli diukur ffprobe saat unggah; biaya kredit dihitung dari durasi itu.
 */
class VideoTools
{
    public const TARGET = ['1:1' => [1080, 1080], '4:5' => [1080, 1350], '9:16' => [1080, 1920], '16:9' => [1920, 1080]];

    public const MAX_CLIP = 180;

    public static function ffmpeg(): string
    {
        return (string) config('krafio.ffmpeg', 'ffmpeg');
    }

    public static function ffprobe(): string
    {
        return (string) config('krafio.ffprobe', 'ffprobe');
    }

    public static function available(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                $p = new Process([self::ffmpeg(), '-version']);
                $p->setTimeout(10)->run();
                $ok = $p->isSuccessful();
            } catch (\Throwable) {
                $ok = false;
            }
        }

        return $ok;
    }

    /** @return array{duration: float, width: ?int, height: ?int, audio: bool} */
    public static function probe(string $src): array
    {
        $proc = new Process([self::ffprobe(), '-v', 'error', '-show_entries', 'format=duration:stream=codec_type,width,height', '-of', 'json', $src]);
        $proc->setTimeout(60)->run();
        $j = json_decode($proc->getOutput(), true) ?: [];
        $v = collect($j['streams'] ?? [])->firstWhere('codec_type', 'video');

        return [
            'duration' => (float) ($j['format']['duration'] ?? 0),
            'width' => isset($v['width']) ? (int) $v['width'] : null,
            'height' => isset($v['height']) ? (int) $v['height'] : null,
            'audio' => collect($j['streams'] ?? [])->contains('codec_type', 'audio'),
        ];
    }

    public static function duration(string $src): float
    {
        return self::probe($src)['duration'];
    }

    public static function hasAudio(string $src): bool
    {
        return self::probe($src)['audio'];
    }

    public static function process(AiJob $job): void
    {
        $up = $job->upload_id ? Upload::find($job->upload_id) : null;
        if (! $up || ! Storage::disk($up->disk ?: 'local')->exists($up->path)) {
            self::fail($job, 'Berkas video sumber tidak ditemukan.');

            return;
        }
        $src = Storage::disk($up->disk ?: 'local')->path($up->path);
        $p = $job->params ?? [];
        $info = self::probe($src);
        $duration = $info['duration'];
        if ($duration <= 0) {
            self::fail($job, 'Video tidak dapat dibaca. Pastikan formatnya MP4, MOV, atau WebM.');

            return;
        }
        $paid = $job->type === 'video_clipper' ? (int) ($p['source_minutes'] ?? 0) : (int) ($p['minutes'] ?? 0);
        if ($paid > 0 && $duration > $paid * 60 + 5) {
            self::fail($job, 'Durasi video ('.self::fmt($duration).') melebihi durasi yang dihitung saat membuat job ('.$paid.' menit).');

            return;
        }
        $tmp = storage_path('app/private/tmp/job-'.$job->id);
        @mkdir($tmp, 0700, true);
        try {
            $job->update(['progress' => 15]);
            $files = $job->type === 'video_clipper' ? self::clipper($job, $src, $info, $tmp) : [self::edit($p, $src, $info, $tmp)];
            $out = [];
            foreach ($files as $f) {
                $out[] = ['bytes' => (string) file_get_contents($f['path']), 'mime' => 'video/mp4'];
            }
            foreach ($files as $f) {
                $out[] = ['bytes' => (string) @file_get_contents($f['thumb'] ?? ''), 'mime' => 'image/jpeg'];
            }
            $stored = Outputs::store($job, array_values(array_filter($out, fn ($o) => $o['bytes'] !== '')), false);
            $n = count($files);
            $media = array_slice($stored['media'], 0, $n);
            $thumbs = array_slice($stored['media'], $n);
            $clips = [];
            foreach ($files as $i => $f) {
                $clips[] = ['url' => $media[$i]['url'], 'thumb' => $thumbs[$i]['url'] ?? null, 'start' => $f['start'] ?? 0, 'end' => $f['end'] ?? null, 'score' => $f['score'] ?? null, 'label' => $f['label'] ?? null];
            }
            $job->update(['status' => 'completed', 'progress' => 100, 'completed_at' => now(), 'error' => null, 'result' => [
                'media' => $media, 'files' => $stored['files'], 'preview_images' => array_column($thumbs, 'url'), 'clips' => $clips,
                'source_duration' => round($duration, 1), 'engine' => 'ffmpeg', 'notes' => $files[0]['notes'] ?? [],
            ]]);
        } catch (\Throwable $e) {
            report($e);
            self::fail($job, 'Pemrosesan video gagal: '.mb_substr($e->getMessage(), 0, 150));
        } finally {
            foreach (glob($tmp.'/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($tmp);
        }
    }

    /* ---------------- Naskah → Video (kinetic text) ---------------- */

    public const THEMES = [
        'night' => ['0x1E1B4B', '0x4C1D95', '&H00FFFFFF', '&H00B48C81'],
        'coffee' => ['0x3B2416', '0x8B5E3C', '&H00E6F2FF', '&H004F86C6'],
        'ocean' => ['0x0C4A6E', '0x0EA5E9', '&H00FFFFFF', '&H00FDE68A'],
        'sunset' => ['0xF97316', '0xDB2777', '&H00FFFFFF', '&H004A1E3A'],
        'mint' => ['0x064E3B', '0x10B981', '&H00FFFFFF', '&H00D1FAE5'],
        'mono' => ['0x111111', '0x3F3F46', '&H00FFFFFF', '&H00A1A1AA'],
    ];

    /** Baris naskah → adegan (nomor/bullet di depan dibiarkan; baris kosong dibuang; maks 30). */
    public static function scenes(string $script): array
    {
        $lines = array_values(array_filter(array_map(fn ($l) => trim(preg_replace('/\s+/u', ' ', $l)), preg_split('/\r?\n/', $script)), fn ($l) => $l !== ''));

        return array_slice(array_map(fn ($l) => mb_substr($l, 0, 160), $lines), 0, 30);
    }

    public static function scriptVideo(AiJob $job): void
    {
        $p = $job->params ?? [];
        $scenes = self::scenes((string) ($p['script'] ?? $job->prompt));
        if (! $scenes) {
            self::fail($job, 'Naskah kosong.');

            return;
        }
        $sec = max(1, min(10, (int) ($p['seconds'] ?? 3)));
        $total = count($scenes) * $sec;
        [$w, $h] = self::size($p['ratio'] ?? '9:16', $p['resolution'] ?? '720');
        [$c0, $c1, $textColor, $accent] = self::THEMES[$p['theme'] ?? 'night'] ?? self::THEMES['night'];
        $tmp = storage_path('app/private/tmp/job-'.$job->id);
        @mkdir($tmp, 0700, true);
        try {
            $job->update(['status' => 'processing', 'progress' => 20, 'started_at' => $job->started_at ?? now()]);
            $ass = $tmp.'/script.ass';
            file_put_contents($ass, self::assScenes($scenes, $sec, $w, $h, $textColor, $accent, $p));
            $out = $tmp.'/out.mp4';
            self::run([self::ffmpeg(), '-y', '-hide_banner', '-loglevel', 'error', '-f', 'lavfi', '-i', "gradients=s={$w}x{$h}:c0={$c0}:c1={$c1}:x0=0:y0=0:x1={$w}:y1={$h}:speed=0.008:rate=30:d={$total}",
                '-vf', 'vignette=PI/5,subtitles=filename='.self::filterPath($ass), '-t', (string) $total, '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '22', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', '-an'], $out);
            $job->update(['progress' => 85]);
            $thumb = self::thumb($out, $tmp.'/thumb.jpg', min(1.0, $sec / 2));
            $files = [['bytes' => (string) file_get_contents($out), 'mime' => 'video/mp4']];
            if ($thumb) {
                $files[] = ['bytes' => (string) file_get_contents($thumb), 'mime' => 'image/jpeg'];
            }
            $stored = Outputs::store($job, $files, false);
            $job->update(['status' => 'completed', 'progress' => 100, 'completed_at' => now(), 'error' => null, 'result' => [
                'media' => [$stored['media'][0]], 'files' => $stored['files'], 'preview_images' => isset($stored['media'][1]) ? [$stored['media'][1]['url']] : [],
                'clips' => [['url' => $stored['media'][0]['url'], 'thumb' => $stored['media'][1]['url'] ?? null, 'start' => 0, 'end' => $total, 'score' => null, 'label' => count($scenes).' adegan']],
                'source_duration' => $total, 'engine' => 'ffmpeg', 'notes' => [],
            ]]);
        } catch (\Throwable $e) {
            report($e);
            self::fail($job, 'Render video gagal: '.mb_substr($e->getMessage(), 0, 150));
        } finally {
            foreach (glob($tmp.'/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($tmp);
        }
    }

    private static function assScenes(array $scenes, int $sec, int $w, int $h, string $text, string $accent, array $p): string
    {
        $size = (int) round($w / ['s' => 18, 'm' => 13, 'l' => 10][$p['size'] ?? 'm']);
        $align = ['top' => 8, 'center' => 5, 'bottom' => 2][$p['position'] ?? 'center'];
        $margin = (int) round($h * 0.12);
        $t = fn (float $x) => sprintf('%d:%02d:%05.2f', intdiv((int) $x, 3600), intdiv((int) $x % 3600, 60), fmod($x, 60));
        $esc = fn (string $s) => str_replace(['\\', '{', '}'], ['', '(', ')'], $s);
        $out = "[Script Info]\nScriptType: v4.00+\nPlayResX: {$w}\nPlayResY: {$h}\nWrapStyle: 0\n\n[V4+ Styles]\n"
            ."Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding\n"
            ."Style: Scene,Arial,{$size},{$text},{$text},&H64000000,&H00000000,-1,0,0,0,100,100,0,0,1,".max(2, (int) ($size / 14)).",3,{$align},70,70,{$margin},1\n"
            .'Style: Brand,Arial,'.(int) round($size * 0.45).",{$accent},{$accent},&H64000000,&H00000000,-1,0,0,0,100,100,0,0,1,1,1,2,40,40,".(int) round($h * 0.04).",1\n"
            ."Style: Bar,Arial,10,{$accent},{$accent},&H00000000,&H00000000,0,0,0,0,100,100,0,0,1,0,0,7,0,0,0,1\n\n"
            ."[Events]\nFormat: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text\n";
        $anim = ! empty($p['animate']) ? '{\\fad(220,160)\\fscx86\\fscy86\\t(0,260,\\fscx100\\fscy100)}' : '';
        foreach ($scenes as $i => $line) {
            $out .= 'Dialogue: 0,'.$t($i * $sec).','.$t(($i + 1) * $sec).",Scene,,0,0,0,,{$anim}".$esc($line)."\n";
            // Indikator progres adegan di tepi atas
            $bw = (int) round($w * ($i + 1) / count($scenes));
            $out .= 'Dialogue: 1,'.$t($i * $sec).','.$t(($i + 1) * $sec).",Bar,,0,0,0,,{\\p1}m 0 0 l {$bw} 0 {$bw} 8 0 8{\\p0}\n";
        }
        if (trim((string) ($p['brand'] ?? '')) !== '') {
            $out .= 'Dialogue: 2,'.$t(0).','.$t(count($scenes) * $sec).',Brand,,0,0,0,,'.$esc(mb_substr(trim((string) $p['brand']), 0, 40))."\n";
        }

        return $out;
    }

    /* ---------------- Edit Video ---------------- */

    private static function edit(array $p, string $src, array $info, string $tmp): array
    {
        $op = $p['operation'] ?? 'reframe';
        $duration = $info['duration'];
        $out = $tmp.'/out.mp4';
        $base = [self::ffmpeg(), '-y', '-hide_banner', '-loglevel', 'error'];
        $res = $p['resolution'] ?? '1080';
        switch ($op) {
            case 'trim':
                $start = max(0, min((float) ($p['start'] ?? 0), $duration - 1));
                $len = max(1, min((float) ($p['length'] ?? 30), $duration - $start));
                $r = self::run([...$base, '-ss', (string) $start, '-i', $src, '-t', (string) $len, ...self::h264()], $out) + ['start' => round($start, 1), 'end' => round($start + $len, 1)];
                break;
            case 'compress':
                $r = self::run([...$base, '-i', $src, '-vf', "scale=-2:'min(720,ih)'", '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '28', '-c:a', 'aac', '-b:a', '96k', '-movflags', '+faststart'], $out);
                break;
            case 'mute':
                $r = self::run([...$base, '-i', $src, '-an', '-c:v', 'copy', '-movflags', '+faststart'], $out);
                break;
            case 'speed':
                $s = in_array($p['speed'] ?? '1.5', ['0.5', '1.25', '1.5', '2'], true) ? (float) $p['speed'] : 1.5;
                $r = self::run([...$base, '-i', $src, '-filter_complex', "[0:v]setpts=PTS/{$s}[v]".($info['audio'] ? ";[0:a]atempo={$s}[a]" : ''), '-map', '[v]', ...($info['audio'] ? ['-map', '[a]'] : []), ...self::h264()], $out);
                break;
            default: // reframe
                $r = self::run([...$base, '-i', $src, '-filter_complex', self::layout($p['ratio'] ?? '9:16', $p['layout'] ?? 'crop', $res, '0:v', 'v'), '-map', '[v]', ...($info['audio'] ? ['-map', '0:a'] : []), ...self::h264()], $out);
        }

        return $r + ['thumb' => self::thumb($out, $tmp.'/thumb0.jpg', 1)];
    }

    /* ---------------- Clipper ---------------- */

    private static function clipper(AiJob $job, string $src, array $info, string $tmp): array
    {
        $p = $job->params ?? [];
        $duration = $info['duration'];
        $notes = [];
        if (($p['mode'] ?? 'auto') === 'manual') {
            $windows = self::parseRanges((string) ($p['ranges'] ?? ''), $duration);
            if (! $windows) {
                throw new \RuntimeException('Rentang waktu tidak valid. Contoh: 0:30-1:05, 2:10-2:40');
            }
        } else {
            $n = max(1, min(10, (int) ($p['clips'] ?? 3)));
            $len = (float) max(5, min(self::MAX_CLIP, (int) ($p['clip_len'] ?? 30)));
            if ($len >= $duration) {
                $len = floor($duration);
                $n = 1;
                $notes[] = 'Video lebih pendek dari panjang klip — dibuat 1 klip sepanjang video.';
            }
            $gap = max(0, (int) ($p['min_gap'] ?? 0));
            $pick = $p['pick'] ?? 'loud';
            if ($pick === 'loud' && ! $info['audio']) {
                $pick = 'scene';
                $notes[] = 'Video tanpa suara — momen dipilih dari perubahan adegan.';
            }
            $job->update(['progress' => 25]);
            $scores = match ($pick) {
                'scene' => self::sceneScores($src, $duration),
                'even' => [],
                default => self::loudness($src),
            };
            $windows = $scores ? self::bestWindows($scores, $len, $n, $gap) : [];
            if (count($windows) < $n) { // lengkapi secara merata bila analisis kurang
                foreach (self::evenWindows($duration, $len, $n) as $w) {
                    if (count($windows) >= $n) {
                        break;
                    }
                    if (! collect($windows)->contains(fn ($x) => abs($x['start'] - $w['start']) < $len)) {
                        $windows[] = $w;
                    }
                }
            }
            $windows = array_map(fn ($w) => $w + ['len' => $len], $windows);
        }
        usort($windows, fn ($a, $b) => $a['start'] <=> $b['start']);
        $job->update(['progress' => 40]);

        $out = [];
        $n = count($windows);
        foreach ($windows as $i => $w) {
            $start = max(0, min($w['start'], $duration - 1));
            $len = min($w['len'], $duration - $start);
            $file = $tmp."/clip{$i}.mp4";
            $filter = self::layout($p['ratio'] ?? '9:16', $p['layout'] ?? 'crop', $p['resolution'] ?? '1080', '0:v', 'v0');
            $last = 'v0';
            if (! empty($p['fade'])) {
                $filter .= ";[{$last}]fade=t=in:st=0:d=0.4,fade=t=out:st=".max(0, $len - 0.5).':d=0.5[v1]';
                $last = 'v1';
            }
            $title = trim((string) ($p['title'] ?? ''));
            if ($title !== '' && ($p['title_mode'] ?? 'first3') !== 'none') {
                $ass = $tmp."/title{$i}.ass";
                [$w0, $h0] = self::size($p['ratio'] ?? '9:16', $p['resolution'] ?? '1080');
                file_put_contents($ass, self::assTitle($title, $w0, $h0, ($p['title_mode'] ?? 'first3') === 'full' ? $len : min(3.0, $len)));
                $filter .= ";[{$last}]subtitles=filename=".self::filterPath($ass).'[v2]';
                $last = 'v2';
            }
            $audio = $info['audio'] ? (! empty($p['fade']) ? ['-af', 'afade=t=in:st=0:d=0.4,afade=t=out:st='.max(0, $len - 0.5).':d=0.5'] : []) : ['-an'];
            $r = self::run([self::ffmpeg(), '-y', '-hide_banner', '-loglevel', 'error', '-ss', (string) $start, '-i', $src, '-t', (string) $len, '-filter_complex', $filter, '-map', "[{$last}]", ...($info['audio'] ? ['-map', '0:a'] : []), ...$audio, ...self::h264()], $file);
            $out[] = $r + ['start' => round($start, 1), 'end' => round($start + $len, 1), 'score' => $w['score'] ?? null, 'label' => $w['label'] ?? null, 'thumb' => self::thumb($file, $tmp."/thumb{$i}.jpg", min(1.0, $len / 2)), 'notes' => $notes];
            $job->update(['progress' => (int) min(95, 40 + 55 * ($i + 1) / $n)]);
        }

        return $out;
    }

    /** "0:30-1:05, 2:10-2:40" / "30-65" / "1:02:03-1:02:40" → jendela valid (maks 10, maks 180 dtk). */
    public static function parseRanges(string $text, float $duration): array
    {
        $out = [];
        foreach (preg_split('/[,;\n]+/', $text) as $part) {
            if (! preg_match('/^\s*([\d:.]+)\s*[-–—]\s*([\d:.]+)\s*$/u', $part, $m)) {
                continue;
            }
            [$a, $b] = [self::secs($m[1]), self::secs($m[2])];
            if ($a === null || $b === null || $b <= $a || $a >= $duration) {
                continue;
            }
            $b = min($b, $duration, $a + self::MAX_CLIP);
            $out[] = ['start' => $a, 'len' => $b - $a, 'score' => null, 'label' => trim($part)];
            if (count($out) >= 10) {
                break;
            }
        }

        return $out;
    }

    private static function secs(string $t): ?float
    {
        $parts = array_map('floatval', explode(':', $t));
        if (count($parts) > 3) {
            return null;
        }
        $s = 0.0;
        foreach ($parts as $x) {
            $s = $s * 60 + $x;
        }

        return $s;
    }

    /** RMS audio per detik (dB) → skor energi. @return array<int, float> detik => skor */
    private static function loudness(string $src): array
    {
        $proc = new Process([self::ffmpeg(), '-hide_banner', '-nostats', '-i', $src, '-vn', '-af', 'aresample=8000,asetnsamples=n=8000:p=0,astats=metadata=1:reset=1,ametadata=print:key=lavfi.astats.Overall.RMS_level:file=-', '-f', 'null', '-']);
        $proc->setTimeout(1200)->run();
        $scores = [];
        $sec = 0;
        foreach (preg_split('/\r?\n/', $proc->getOutput()) as $line) {
            if (preg_match('/RMS_level=(-?[\d.]+|-inf)/', $line, $m)) {
                $db = $m[1] === '-inf' ? -90.0 : (float) $m[1];
                $scores[$sec++] = max(0.0, 90 + $db);
            }
        }

        return $scores;
    }

    /** Jumlah perubahan adegan per detik. */
    private static function sceneScores(string $src, float $duration): array
    {
        $proc = new Process([self::ffmpeg(), '-hide_banner', '-i', $src, '-an', '-vf', "scale=320:-2,select='gt(scene,0.3)',showinfo", '-f', 'null', '-']);
        $proc->setTimeout(1200)->run();
        $scores = array_fill(0, (int) ceil($duration) + 1, 0.0);
        foreach (preg_split('/\r?\n/', $proc->getErrorOutput()) as $line) {
            if (preg_match('/pts_time:([\d.]+)/', $line, $m)) {
                $scores[(int) $m[1]] = ($scores[(int) $m[1]] ?? 0) + 1.0;
            }
        }

        return array_sum($scores) > 0 ? $scores : [];
    }

    /** N jendela tidak tumpang tindih (+ jeda minimal) dengan skor total tertinggi. */
    private static function bestWindows(array $scores, float $len, int $n, int $gap = 0): array
    {
        $L = max(1, (int) round($len));
        if (count($scores) < $L) {
            return [];
        }
        $prefix = [0.0];
        foreach (array_values($scores) as $i => $s) {
            $prefix[$i + 1] = $prefix[$i] + $s;
        }
        $cands = [];
        for ($s = 0; $s + $L <= count($scores); $s++) {
            $cands[] = ['start' => (float) $s, 'score' => round($prefix[$s + $L] - $prefix[$s], 1)];
        }
        usort($cands, fn ($a, $b) => $b['score'] <=> $a['score']);
        $picked = [];
        foreach ($cands as $c) {
            foreach ($picked as $p) {
                if (abs($p['start'] - $c['start']) < $L + $gap) {
                    continue 2;
                }
            }
            $picked[] = $c;
            if (count($picked) >= $n) {
                break;
            }
        }

        return $picked;
    }

    private static function evenWindows(float $duration, float $len, int $n): array
    {
        $out = [];
        $step = $n > 1 ? max($len, ($duration - $len) / ($n - 1)) : 0;
        for ($i = 0; $i < $n && $i * $step + $len <= $duration + 0.5; $i++) {
            $out[] = ['start' => round(min($i * $step, max(0, $duration - $len)), 2), 'score' => null];
        }

        return $out;
    }

    /* ---------------- Filter ---------------- */

    private static function size(string $ratio, string $res): array
    {
        [$w, $h] = self::TARGET[$ratio] ?? self::TARGET['9:16'];
        if ($res === '720') {
            [$w, $h] = [(int) (round($w * 2 / 3 / 2) * 2), (int) (round($h * 2 / 3 / 2) * 2)];
        }

        return [$w, $h];
    }

    /**
     * Filtergraph tata letak → label [out].
     * crop: potong tengah; blur: video utuh di atas latar blur dirinya; fit: video utuh + bingkai hitam.
     */
    public static function layout(string $ratio, string $layout, string $res, string $in, string $out): string
    {
        [$w, $h] = self::size($ratio, $res);
        $r = $w / $h;

        return match ($layout) {
            'blur' => "[{$in}]split=2[bg][fg];[bg]scale={$w}:{$h}:force_original_aspect_ratio=increase,crop={$w}:{$h},boxblur=20:2[bgb];"
                ."[fg]scale={$w}:{$h}:force_original_aspect_ratio=decrease[fgs];[bgb][fgs]overlay=(W-w)/2:(H-h)/2,setsar=1[{$out}]",
            'fit' => "[{$in}]scale={$w}:{$h}:force_original_aspect_ratio=decrease,pad={$w}:{$h}:(ow-iw)/2:(oh-ih)/2:black,setsar=1[{$out}]",
            default => "[{$in}]crop='min(iw,ih*{$r})':'min(ih,iw/{$r})',scale={$w}:{$h},setsar=1[{$out}]",
        };
    }

    /** Subtitle ASS untuk judul/hook di bagian atas video (libass; font Arial/sistem). */
    private static function assTitle(string $text, int $w, int $h, float $until): string
    {
        $size = (int) round($w / 13);
        $margin = (int) round($h * 0.08);
        $t = str_replace(['\\', '{', '}', "\n"], ['', '(', ')', '\\N'], mb_substr($text, 0, 120));
        $end = sprintf('%d:%02d:%05.2f', intdiv((int) $until, 3600), intdiv((int) $until % 3600, 60), fmod($until, 60));

        return "[Script Info]\nScriptType: v4.00+\nPlayResX: {$w}\nPlayResY: {$h}\nWrapStyle: 0\n\n"
            ."[V4+ Styles]\nFormat: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding\n"
            ."Style: Title,Arial,{$size},&H00FFFFFF,&H00FFFFFF,&H00000000,&H64000000,-1,0,0,0,100,100,0,0,1,".max(2, (int) ($size / 12)).",2,8,60,60,{$margin},1\n\n"
            ."[Events]\nFormat: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text\nDialogue: 0,0:00:00.00,{$end},Title,,0,0,0,,{$t}\n";
    }

    /** Escape path untuk argumen filter FFmpeg (bukan shell). */
    private static function filterPath(string $p): string
    {
        return "'".str_replace(['\\', "'", ':'], ['\\\\', "\\'", '\\:'], $p)."'";
    }

    private static function h264(): array
    {
        return ['-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '128k', '-movflags', '+faststart', '-threads', '0'];
    }

    private static function thumb(string $video, string $out, float $at): ?string
    {
        $proc = new Process([self::ffmpeg(), '-y', '-hide_banner', '-loglevel', 'error', '-ss', (string) $at, '-i', $video, '-frames:v', '1', '-vf', 'scale=360:-2', '-q:v', '4', $out]);
        $proc->setTimeout(60)->run();

        return is_file($out) ? $out : null;
    }

    private static function run(array $args, string $out): array
    {
        $args[] = $out;
        $proc = new Process($args);
        $proc->setTimeout(1800)->run();
        if (! $proc->isSuccessful() || ! is_file($out) || filesize($out) === 0) {
            throw new \RuntimeException(trim(mb_substr($proc->getErrorOutput(), -300)) ?: 'FFmpeg gagal.');
        }

        return ['path' => $out];
    }

    private static function fmt(float $s): string
    {
        return sprintf('%d:%02d', intdiv((int) $s, 60), (int) $s % 60);
    }

    private static function fail(AiJob $job, string $msg): void
    {
        $job->update(['status' => 'failed', 'progress' => 100, 'completed_at' => now(), 'error' => mb_substr($msg, 0, 250)]);
        StudioController::refundJob($job, 'Job #'.$job->id.' gagal diproses');
    }
}
