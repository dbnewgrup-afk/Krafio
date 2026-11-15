<?php

use App\Services\Settings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Driver Claude (teks) & Gemini (teks/gambar/video): siapkan provider + model dalam status DRAFT tanpa API key.
 * Admin mengisi key di Admin → AI → Provider lalu mengubah status ke Active.
 * Biaya (cost_idr): Rp per 1.000 token (teks, campuran input:output ±1:2) atau per hasil (gambar/video 5 detik 720p),
 * kurs Rp16.500 — acuan report/BIAYA_AI_DAN_MARGIN_2026-11-14.md.
 * Tool teks: Naskah & Artikel memakai Gemini Flash, tool lain Claude Haiku (sesuai Kalkulator margin).
 * Hapus latar sekarang dikerjakan model gambar (biaya = 1 gambar) → biaya kredit bawaan 2 → 8.
 */
return new class extends Migration
{
    private const PROVIDERS = [
        'Anthropic Claude' => ['driver' => 'anthropic', 'capabilities' => ['text'], 'priority' => 10, 'models' => [
            ['name' => 'Claude Haiku 5.5', 'model_id' => 'claude-haiku-5-5', 'capability' => 'text', 'cost_idr' => 6, 'is_fallback' => true],
            ['name' => 'Claude Sonnet 5.5', 'model_id' => 'claude-sonnet-5-5', 'capability' => 'text', 'cost_idr' => 121, 'is_fallback' => false],
        ]],
        'Google Gemini' => ['driver' => 'gemini', 'capabilities' => ['text', 'image', 'video'], 'priority' => 20, 'models' => [
            ['name' => 'Gemini 3.8 Flash', 'model_id' => 'gemini-3.8-flash', 'capability' => 'text', 'cost_idr' => 46, 'is_fallback' => true],
            ['name' => 'Nano Banana 2.1', 'model_id' => 'gemini-nano-banana-2.1', 'capability' => 'image', 'cost_idr' => 555, 'is_fallback' => true],
            ['name' => 'Gemini Omni Flash', 'model_id' => 'gemini-omni-1.2-flash', 'capability' => 'video', 'cost_idr' => 5775, 'is_fallback' => true],
        ]],
    ];

    private const TOOL_MODELS = [
        'content_script' => 'gemini-3.8-flash', 'article_writer' => 'gemini-3.8-flash',
        'content_caption' => 'claude-haiku-5-5', 'content_brainstorm' => 'claude-haiku-5-5', 'content_plan' => 'claude-haiku-5-5',
        'copywriting' => 'claude-haiku-5-5', 'story_series' => 'claude-haiku-5-5', 'prompt_optimizer' => 'claude-haiku-5-5',
    ];

    public function up(): void
    {
        foreach (self::PROVIDERS as $name => $cfg) {
            if (DB::table('ai_providers')->where('driver', $cfg['driver'])->exists()) {
                continue;
            }
            // Provider Gemini lama lewat jalur OpenAI-compatible (key sudah diisi Admin) → jadikan driver gemini, key tetap
            $existing = $cfg['driver'] === 'gemini'
                ? DB::table('ai_providers')->where('driver', 'openai_compatible')->where('base_url', 'like', '%generativelanguage.googleapis.com%')->orderBy('id')->first()
                : null;
            if ($existing) {
                $id = $existing->id;
                $caps = array_values(array_unique(array_merge((array) json_decode((string) $existing->capabilities, true), $cfg['capabilities'])));
                DB::table('ai_providers')->where('id', $id)->update(['driver' => 'gemini', 'base_url' => null, 'capabilities' => json_encode($caps), 'updated_at' => now()]);
            } else {
                $id = DB::table('ai_providers')->insertGetId([
                    'name' => $name, 'driver' => $cfg['driver'], 'base_url' => null, 'api_key' => null,
                    'capabilities' => json_encode($cfg['capabilities']), 'status' => 'draft', 'health' => 'unknown',
                    'priority' => $cfg['priority'], 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            foreach ($cfg['models'] as $m) {
                if (DB::table('ai_models')->where('provider_id', $id)->where('model_id', $m['model_id'])->exists()) {
                    continue;
                }
                // Provider lama sudah punya model teks bawaan → model baru hanya dipakai lewat pilihan per tool
                if ($existing && $m['capability'] === 'text' && DB::table('ai_models')->where('provider_id', $id)->where('capability', 'text')->exists()) {
                    $m['is_fallback'] = false;
                }
                DB::table('ai_models')->insert($m + ['provider_id' => $id, 'credit_cost' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        $over = (array) Settings::get('ai_tools.overrides', []);
        foreach (self::TOOL_MODELS as $tool => $model) {
            if (empty($over[$tool]['model'])) {
                $over[$tool] = ((array) ($over[$tool] ?? [])) + ['model' => $model];
            }
        }
        Settings::set('ai_tools.overrides', $over);

        $costs = (array) Settings::get('credits.costs', []);
        if (! isset($costs['remove_bg'])) {
            Settings::set('credits.costs', ['remove_bg' => 8] + $costs);
        }
    }

    public function down(): void
    {
        foreach (self::PROVIDERS as $cfg) {
            DB::table('ai_providers')->where('driver', $cfg['driver'])->whereNull('api_key')->where('status', 'draft')->delete();
        }
    }
};
