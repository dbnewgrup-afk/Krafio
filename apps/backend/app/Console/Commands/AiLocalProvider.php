<?php

namespace App\Console\Commands;

use App\Models\AiModel;
use App\Models\AiProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Daftarkan runtime AI lokal (Ollama/Jan, OpenAI-compatible) sebagai provider teks CADANGAN, plus slot provider
 * UTAMA (draft) untuk API berbayar yang diisi Admin. AI Gateway memakai urutan prioritas: utama (1) → lokal (50).
 */
class AiLocalProvider extends Command
{
    protected $signature = 'krafio:ai-local {--model=qwen2.5:3b : Model di Ollama} {--url=http://127.0.0.1:11434/v1 : Base URL OpenAI-compatible}';

    protected $description = 'Daftarkan Ollama/Jan lokal sebagai provider AI teks cadangan + slot provider utama (draft)';

    public function handle(): int
    {
        $url = rtrim((string) $this->option('url'), '/');
        $model = (string) $this->option('model');
        try {
            $ok = Http::timeout(5)->get(preg_replace('#/v1$#', '', $url).'/api/tags')->successful();
        } catch (\Throwable) {
            $ok = false;
        }
        if (! $ok) {
            $this->warn("Server {$url} belum menjawab — provider tetap didaftarkan, jalankan Ollama lalu uji dari Admin → AI Tools.");
        }
        $local = AiProvider::firstOrNew(['name' => 'Ollama lokal (cadangan)']);
        $local->fill(['driver' => 'openai_compatible', 'base_url' => $url, 'capabilities' => ['text'], 'status' => 'active', 'priority' => 50]);
        if (! $local->api_key) {
            $local->api_key = 'ollama'; // Ollama tidak memerlukan key; kolom wajib terisi untuk driver openai_compatible
        }
        $local->save();
        AiModel::updateOrCreate(['provider_id' => $local->id, 'capability' => 'text'], ['name' => $model, 'model_id' => $model, 'credit_cost' => 1, 'is_active' => true, 'is_fallback' => true, 'cost_idr' => 0]);

        $primary = AiProvider::firstOrCreate(['name' => 'API teks utama (OpenAI-compatible)'], [
            'driver' => 'openai_compatible', 'base_url' => 'https://api.openai.com/v1', 'capabilities' => ['text'], 'status' => 'draft', 'priority' => 1,
        ]);
        AiModel::firstOrCreate(['provider_id' => $primary->id, 'capability' => 'text'], ['name' => 'gpt-4o-mini', 'model_id' => 'gpt-4o-mini', 'credit_cost' => 1, 'is_active' => true, 'is_fallback' => false, 'cost_idr' => 3]);

        $this->info("Provider lokal aktif: {$url} ({$model}), prioritas 50.");
        $this->info('Slot provider utama dibuat sebagai DRAFT: isi API key & base URL di Admin → AI → Provider, lalu ubah status ke Active.');

        return self::SUCCESS;
    }
}
