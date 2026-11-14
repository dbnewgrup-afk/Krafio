<?php

use App\Models\AiPrompt;
use App\Support\AiTools;
use Illuminate\Database\Migrations\Migration;

/** Prompt Library untuk tool teks baru (mis. Prompt Optimizer). Idempoten: prompt yang sudah ada/diubah Admin tidak disentuh. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (AiTools::definitions() as $key => $t) {
            if ($t['capability'] !== 'text') {
                continue;
            }
            $label = AiTools::tr($t['label'], 'id');
            AiPrompt::firstOrCreate(['key' => "tool.{$key}.system"], ['name' => "Sistem — {$label}", 'template' => $t['system'], 'version' => '1.0', 'status' => 'active']);
            AiPrompt::firstOrCreate(['key' => "tool.{$key}.instruction"], ['name' => "Instruksi — {$label}", 'template' => $t['instruction'], 'version' => '1.0', 'status' => 'active']);
        }
    }

    public function down(): void {}
};
