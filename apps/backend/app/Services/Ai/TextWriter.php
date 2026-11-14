<?php

namespace App\Services\Ai;

use App\Models\AiJob;
use App\Services\AiGateway;
use App\Support\AiTools;
use Illuminate\Support\Str;

/**
 * Mesin konten teks untuk AI Tools berkapabilitas `text`.
 *
 * - Mode `parts`: satu panggilan AI per part/variasi (naskah bersambung, caption, copy, cerita). Part berikutnya
 *   mendapat ringkasan part sebelumnya agar alurnya menyambung.
 * - Mode `list`: item dibuat per batch (ide konten, kalender) agar model kecil tetap konsisten.
 * Hasil disimpan terstruktur: {format: doc, title, parts:[{title, sections:[{key,label,content}]}], items, item_fields}.
 * Progres & hasil sementara ditulis setelah tiap part sehingga pengguna melihat konten muncul bertahap.
 * Tanpa provider teks aktif tidak ada hasil contoh: job ditolak saat dibuat (StudioController) atau gagal + kuota kembali.
 */
class TextWriter
{
    public static function run(AiJob $job, array $tool): void
    {
        app()->setLocale(($job->user->locale ?? 'id') === 'en' ? 'en' : 'id'); // label bagian mengikuti bahasa pengguna
        $params = $job->params ?? [];
        $vars = self::vars($tool, $params);
        $system = Prompts::render('tool.'.$tool['key'].'.system', $vars, $tool['system'] ?? '')
            ."\n\nATURAN: gunakan nama brand, produk, harga, dan fakta PERSIS seperti di brief — jangan mengarang nama lain. Isi setiap kunci dengan teks biasa (bukan objek). Jawab HANYA dengan satu objek JSON valid tanpa teks lain dan tanpa markdown code fence.";
        $instruction = Prompts::render('tool.'.$tool['key'].'.instruction', $vars, $tool['instruction'] ?? '{{topic}}');
        $brief = self::brief($tool, $params);

        ($tool['mode'] ?? 'parts') === 'list'
            ? self::listMode($job, $tool, $params, $system, $instruction, $brief)
            : self::partsMode($job, $tool, $params, $system, $instruction, $brief);
    }

    /* ---------------- Mode parts ---------------- */

    private static function partsMode(AiJob $job, array $tool, array $params, string $system, string $instruction, string $brief): void
    {
        $n = AiTools::partCount($tool, $params);
        $sections = self::activeSections($tool, $params);
        $isSeries = $n > 1 && in_array($tool['key'], ['content_script', 'story_series'], true);
        $result = ['format' => 'doc', 'tool' => $tool['key'], 'title' => null, 'parts' => [], 'sections' => self::labels($sections)];
        $cost = 0;
        $providerId = null;

        for ($i = 1; $i <= $n; $i++) {
            $ctx = '';
            if ($isSeries) {
                $prev = array_map(fn ($p, $k) => 'Part '.($k + 1).': '.$p['title'].' — '.Str::limit($p['summary'] ?? '', 220), $result['parts'], array_keys($result['parts']));
                $ctx = "\n\nINI PART {$i} DARI {$n} dalam satu series"
                    .(isset($params['series_style']) ? ' (pola: '.AiTools::optionLabel($tool, 'series_style', $params['series_style']).')' : '').'.'
                    .($prev ? "\nPart sebelumnya:\n".implode("\n", $prev)."\nLanjutkan alurnya, jangan mengulang isi part sebelumnya." : "\nIni part pembuka: perkenalkan konteks dengan kuat.")
                    .($i < $n ? "\nAkhiri dengan pengait ke part berikutnya." : "\nIni part terakhir: tutup series dengan memuaskan.");
            } elseif ($n > 1) {
                $ctx = "\n\nINI VARIASI {$i} DARI {$n}. Gunakan sudut pandang/pendekatan yang berbeda dari variasi lain."
                    .($result['parts'] ? "\nVariasi sebelumnya dibuka dengan: ".implode(' | ', array_map(fn ($p) => Str::limit($p['first'] ?? '', 80), $result['parts'])) : '');
            }
            $spec = self::jsonSpec(array_merge(['title' => ['Judul', $n > 1 ? "judul singkat part/variasi {$i}" : 'judul singkat konten']], $sections));
            $user = $instruction."\n\nBRIEF:\n".$brief.$ctx."\n\nFORMAT JAWABAN (JSON):\n".$spec;

            $res = AiGateway::chat([['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]], self::maxTokens($tool, $params), ['json' => true, 'temperature' => 0.7, 'frequency_penalty' => 0.4]);
            if (! $res) {
                throw new \RuntimeException($i === 1 ? 'Penyedia AI teks tidak merespons. Kuota sudah dikembalikan.' : 'Penyedia AI berhenti merespons di part '.$i.'. Kuota sudah dikembalikan.');
            }
            $providerId = $res['provider_id'];
            $cost += (int) ($res['cost'] ?? 0);
            $data = self::decode($res['text']);
            $part = ['title' => self::str($data['title'] ?? '') ?: ($n > 1 ? "Part {$i}" : self::str($params[$tool['prompt_field']] ?? '')), 'sections' => []];
            foreach ($sections as $k => $def) {
                $v = self::str($data[$k] ?? '');
                if ($k === 'hashtags' && $v !== '') {
                    $v = self::hashtags($v);
                }
                if ($v !== '') {
                    $part['sections'][] = ['key' => $k, 'label' => AiTools::tr($def[0]), 'content' => $v];
                }
            }
            if (! $part['sections']) { // model tidak mengikuti format → simpan teks mentah apa adanya
                $part['sections'][] = ['key' => 'content', 'label' => 'Konten', 'content' => trim($res['text'])];
            }
            $first = $part['sections'][0]['content'];
            $result['parts'][] = $part + ['summary' => Str::limit(implode(' ', array_column($part['sections'], 'content')), 300), 'first' => $first];
            $result['title'] ??= $n > 1 ? self::str($params[$tool['prompt_field']] ?? $part['title']) : $part['title'];
            // Hasil sementara: pengguna melihat part yang sudah selesai sambil menunggu
            $job->update(['progress' => (int) min(95, 10 + 85 * $i / $n), 'provider_id' => $providerId, 'result' => self::clean($result) + ['partial' => $i < $n]]);
        }
        $job->update(['status' => 'completed', 'progress' => 100, 'completed_at' => now(), 'provider_id' => $providerId, 'provider_cost' => $cost, 'result' => self::clean($result)]);
    }

    /* ---------------- Mode list ---------------- */

    private static function listMode(AiJob $job, array $tool, array $params, string $system, string $instruction, string $brief): void
    {
        $total = max(1, min((int) ($tool['max_parts'] ?? 30), (int) ($params[$tool['count_field']] ?? 5)));
        $batch = max(1, (int) ($tool['batch'] ?? 6));
        $fields = $tool['item_fields'];
        $result = ['format' => 'doc', 'tool' => $tool['key'], 'title' => self::str($params[$tool['prompt_field']] ?? ''), 'items' => [], 'item_fields' => self::labels($fields)];
        $cost = 0;
        $providerId = null;
        $guard = 0;
        while (count($result['items']) < $total && $guard++ < (int) ceil($total / $batch) + 2) {
            $from = count($result['items']) + 1;
            $want = min($batch, $total - $from + 1);
            $spec = '{"items": [ '.self::jsonSpec($fields).' , ... ]} — tepat '.$want.' item.';
            $ctx = $tool['key'] === 'content_plan' ? "\nBuat item untuk hari ke-{$from} sampai ke-".($from + $want - 1).'.' : '';
            if ($result['items']) {
                $ctx .= "\nJangan mengulang item yang sudah ada: ".implode('; ', array_map(fn ($it) => Str::limit((string) ($it['title'] ?? ''), 60), $result['items'])).'.';
            }
            $user = $instruction."\n\nBRIEF:\n".$brief."\n\nBuat {$want} item.".$ctx."\n\nFORMAT JAWABAN (JSON):\n".$spec;
            $res = AiGateway::chat([['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]], min(3200, 300 * $want + 300), ['json' => true, 'temperature' => 0.8, 'frequency_penalty' => 0.4]);
            if (! $res) {
                if (! $result['items']) {
                    throw new \RuntimeException('Penyedia AI teks tidak merespons. Kuota sudah dikembalikan.');
                }
                break;
            }
            $providerId = $res['provider_id'];
            $cost += (int) ($res['cost'] ?? 0);
            $data = self::decode($res['text']);
            $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : (array_is_list($data) ? $data : []);
            foreach (array_slice($items, 0, $want) as $it) {
                if (! is_array($it)) {
                    $it = ['title' => self::str($it)];
                }
                $row = [];
                foreach ($fields as $k => $_) {
                    $row[$k] = self::str($it[$k] ?? '');
                }
                if ($tool['key'] === 'content_plan' && $row['day'] === '') {
                    $row['day'] = (string) (count($result['items']) + 1);
                }
                if (implode('', $row) !== '') {
                    $result['items'][] = $row;
                }
            }
            if (! $items) {
                break; // model tidak menghasilkan item; hindari loop
            }
            $job->update(['progress' => (int) min(95, 10 + 85 * count($result['items']) / $total), 'provider_id' => $providerId, 'result' => $result + ['partial' => true]]);
        }
        if (! $result['items']) {
            throw new \RuntimeException('Penyedia AI tidak menghasilkan item dalam format yang diminta.');
        }
        $job->update(['status' => 'completed', 'progress' => 100, 'completed_at' => now(), 'provider_id' => $providerId, 'provider_cost' => $cost, 'result' => $result]);
    }

    /* ---------------- Prompt helper ---------------- */

    /** Variabel {{key}} untuk Prompt Library: label opsi (bukan kode), kosong → "-". */
    public static function vars(array $tool, array $params): array
    {
        $v = [];
        foreach ($tool['fields'] as $f) {
            $raw = $params[$f['key']] ?? null;
            $v[$f['key']] = $raw === null || $raw === '' || $raw === [] ? '-' : AiTools::optionLabel($tool, $f['key'], $raw);
        }

        return $v;
    }

    /** Brief berbentuk daftar "Label: nilai" dari semua field yang terisi. */
    public static function brief(array $tool, array $params): string
    {
        $lines = [];
        foreach ($tool['fields'] as $f) {
            if (! array_key_exists($f['key'], $params) || in_array($f['type'], ['toggle'], true) && ! $params[$f['key']]) {
                continue;
            }
            $raw = $params[$f['key']];
            if ($raw === '' || $raw === [] || $raw === null) {
                continue;
            }
            $lines[] = '- '.AiTools::tr($f['label'], 'id').': '.AiTools::optionLabel($tool, $f['key'], $raw);
        }

        return implode("\n", $lines);
    }

    /** Bagian yang aktif: tanpa syarat, atau syaratnya dicentang di "include"/toggle. */
    private static function activeSections(array $tool, array $params): array
    {
        $include = (array) ($params['include'] ?? []);

        return array_filter($tool['sections'] ?? [], function ($def) use ($include, $params) {
            $cond = $def[2] ?? null;
            if (! $cond) {
                return true;
            }

            return in_array($cond, $include, true) || ! empty($params[$cond]) && $params[$cond] === true;
        });
    }

    private static function jsonSpec(array $fields): string
    {
        $pairs = [];
        foreach ($fields as $k => $def) {
            $pairs[] = '"'.$k.'": "'.($def[1] ?? AiTools::tr($def[0], 'id')).'"';
        }

        return '{'.implode(', ', $pairs).'}';
    }

    private static function labels(array $defs): array
    {
        return array_map(fn ($k, $d) => ['key' => $k, 'label' => AiTools::tr($d[0])], array_keys($defs), array_values($defs));
    }

    private static function maxTokens(array $tool, array $params): int
    {
        $base = match ($tool['key']) {
            'article_writer' => (int) ($params['length'] ?? 800) * 2 + 400,
            'story_series' => (int) ($params['length'] ?? 300) * 2 + 200,
            'content_script' => max(1100, (int) ($params['duration'] ?? 30) * 10 + 800),
            'content_caption' => ($params['length'] ?? '') === 'long' ? 1200 : 900,
            default => 1000,
        };

        return min(4000, $base);
    }

    /* ---------------- Parsing ---------------- */

    public static function decode(string $text): array
    {
        $t = trim($text);
        $t = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $t) ?? $t;
        $d = json_decode($t, true);
        $obj = strpos($t, '{');
        $arr = strpos($t, '[');
        $objectFirst = $obj !== false && ($arr === false || $obj < $arr);
        if (! is_array($d) && $objectFirst) {
            // Objek: potong ke kurung tutup terakhir; bila terpotong (batas token) tutup string/kurung yang terbuka.
            // Jangan pernah mengambil array di DALAM objek — itu menghilangkan kunci bagian.
            $end = strrpos($t, '}');
            $d = $end !== false ? json_decode(substr($t, $obj, $end - $obj + 1), true) : null;
            if (! is_array($d) || array_is_list($d)) {
                $d = json_decode(self::repair(substr($t, $obj)), true);
            }
        } elseif (! is_array($d) && $arr !== false) {
            $end = strrpos($t, ']');
            $d = json_decode($end !== false ? substr($t, $arr, $end - $arr + 1) : self::repair(substr($t, $arr)), true);
            if (! is_array($d)) {
                $d = json_decode(self::repair(substr($t, $arr)), true);
            }
        }

        return is_array($d) ? $d : [];
    }

    /** Tutup JSON yang terpotong: string terbuka, koma menggantung, dan kurung yang belum ditutup. */
    public static function repair(string $json): string
    {
        $stack = [];
        $inStr = false;
        $esc = false;
        $len = strlen($json);
        for ($i = 0; $i < $len; $i++) {
            $c = $json[$i];
            if ($inStr) {
                if ($esc) {
                    $esc = false;
                } elseif ($c === '\\') {
                    $esc = true;
                } elseif ($c === '"') {
                    $inStr = false;
                }

                continue;
            }
            if ($c === '"') {
                $inStr = true;
            } elseif ($c === '{' || $c === '[') {
                $stack[] = $c === '{' ? '}' : ']';
            } elseif (($c === '}' || $c === ']') && $stack) {
                array_pop($stack);
            }
        }
        $out = $json.($inStr ? '"' : '');
        $out = preg_replace('/[,:]\s*$/', '', rtrim($out)) ?? $out;
        $out = preg_replace('/,\s*"[^"]*"\s*$/', '', $out) ?? $out; // kunci tanpa nilai di akhir

        return $out.implode('', array_reverse($stack));
    }

    private static function hashtags(string $v): string
    {
        // Model sudah menulis beberapa #tag → ambil tag-nya saja (kalimat di sela tag dibuang);
        // selain itu setiap kata/frasa dipisah koma/spasi dijadikan tag.
        if (preg_match_all('/#[\p{L}\p{N}_]+/u', $v, $m) && count($m[0]) >= 2) {
            $tags = $m[0];
        } else {
            $tags = array_map(fn ($t) => '#'.preg_replace('/[^\p{L}\p{N}_]/u', '', ltrim($t, '#')), preg_split('/[\s,]+/u', $v, -1, PREG_SPLIT_NO_EMPTY));
        }
        $tags = array_values(array_unique(array_filter($tags, fn ($t) => mb_strlen($t) > 2 && ! preg_match('/^#\d+$/', $t))));

        return implode(' ', $tags);
    }

    /** Nilai apa pun (string/list/objek) → teks rapi. */
    public static function str($v): string
    {
        if (is_string($v) || is_numeric($v)) {
            return trim((string) $v);
        }
        if (is_bool($v) || $v === null) {
            return '';
        }
        $lines = [];
        foreach ((array) $v as $k => $item) {
            if (is_array($item)) {
                $parts = [];
                foreach ($item as $ik => $iv) {
                    $s = self::str($iv);
                    if ($s !== '') {
                        $parts[] = is_string($ik) ? ucfirst(str_replace('_', ' ', $ik)).': '.$s : $s;
                    }
                }
                $lines[] = (is_int($k) ? ($k + 1).'. ' : $k.': ').implode(' — ', $parts);
            } else {
                $s = self::str($item);
                if ($s !== '') {
                    $lines[] = is_int($k) ? $s : $k.': '.$s;
                }
            }
        }

        return implode("\n", $lines);
    }

    private static function clean(array $r): array
    {
        $r['parts'] = array_map(fn ($p) => ['title' => $p['title'], 'sections' => $p['sections']], $r['parts'] ?? []);

        return $r;
    }
}
