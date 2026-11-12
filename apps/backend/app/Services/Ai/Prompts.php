<?php

namespace App\Services\Ai;

use App\Models\AiPrompt;

/**
 * Prompt Library (Admin → AI → Prompt): satu baris per key (unik), dipakai bila berstatus AKTIF.
 * Ubah isi/versi dari Admin untuk mengganti perilaku AI tanpa deploy; status draft/arsip → kembali ke teks bawaan.
 */
class Prompts
{
    public static function template(string $key): ?string
    {
        $tpl = AiPrompt::where('key', $key)->where('status', 'active')->value('template');

        return $tpl !== null && trim((string) $tpl) !== '' ? (string) $tpl : null;
    }

    /** Render teks biasa; bila prompt tidak aktif/tidak ada → $fallback (dirender dengan variabel yang sama). */
    public static function render(string $key, array $vars, string $fallback = ''): string
    {
        return self::fill(self::template($key) ?? $fallback, $vars);
    }

    public static function fill(string $tpl, array $vars): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i', fn ($m) => array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : '', $tpl) ?? $tpl;
    }

    /**
     * Render workflow JSON (ComfyUI "API format"): placeholder di dalam string di-escape JSON, placeholder tanpa
     * tanda kutip (mis. "seed": {{seed}}) diganti angka apa adanya. Mengembalikan array siap dikirim.
     */
    public static function workflow(string $tpl, array $vars): array
    {
        $json = preg_replace_callback('/"\{\{\s*([a-z_]+)\s*\}\}"|\{\{\s*([a-z_]+)\s*\}\}/i', function ($m) use ($vars) {
            $quoted = ($m[1] ?? '') !== '';
            $v = $vars[$quoted ? $m[1] : $m[2]] ?? '';
            if (! $quoted && is_numeric($v)) {
                return (string) $v;
            }

            return json_encode((string) $v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $tpl);
        $data = json_decode((string) $json, true);
        if (! is_array($data)) {
            throw new \RuntimeException('Workflow ComfyUI tidak valid (bukan JSON).');
        }

        return $data;
    }
}
