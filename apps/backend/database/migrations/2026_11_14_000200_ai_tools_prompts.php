<?php

use App\Models\AiPrompt;
use App\Support\AiTools;
use Illuminate\Database\Migrations\Migration;

/**
 * AI Tools: prompt sistem & instruksi tiap tool teks masuk Prompt Library (bisa diedit Admin),
 * dan prompt gambar/video bawaan lama diganti template yang memakai field form baru
 * (hanya bila admin belum mengubahnya).
 */
return new class extends Migration
{
    private const OLD = [
        'image.photo_generate' => '{{prompt}}, gaya {{style}}, foto produk komersial, pencahayaan rapi, fokus tajam, tanpa teks, tanpa watermark',
        'video.video_generate' => '{{prompt}}, video promosi pendek, gerakan kamera halus, rasio {{ratio}}, durasi {{duration}} detik',
        'image.photo_edit' => '{{prompt}} (operasi: {{operation}})',
    ];

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
        foreach (self::OLD as $key => $old) {
            $tool = AiTools::forType(substr($key, strpos($key, '.') + 1));
            $p = AiPrompt::where('key', $key)->first();
            if ($p && trim($p->template) === $old && $tool) {
                $p->update(['template' => $tool['prompt_template'], 'version' => '2.0']);
            }
        }
    }

    public function down(): void
    {
        AiPrompt::where('key', 'like', 'tool.%')->delete();
    }
};
