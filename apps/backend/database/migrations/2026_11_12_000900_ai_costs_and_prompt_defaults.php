<?php

use App\Models\AiPrompt;
use App\Support\AiPromptDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Biaya provider per model (dasar perhitungan margin AI & alert 55%) dan prompt bawaan Prompt Library.
 * cost_idr: Rupiah per 1.000 token (model teks) atau per hasil (model gambar/video).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_models', function (Blueprint $t) {
            $t->unsignedInteger('cost_idr')->default(0)->after('credit_cost');
        });
        Schema::table('ai_jobs', function (Blueprint $t) {
            $t->unsignedInteger('provider_cost')->nullable()->after('cost');
        });
        foreach (AiPromptDefaults::PROMPTS as $key => [$name, $template]) {
            AiPrompt::firstOrCreate(['key' => $key], ['name' => $name, 'template' => $template, 'version' => '1.0', 'status' => 'active']);
        }
    }

    public function down(): void
    {
        Schema::table('ai_jobs', fn (Blueprint $t) => $t->dropColumn('provider_cost'));
        Schema::table('ai_models', fn (Blueprint $t) => $t->dropColumn('cost_idr'));
    }
};
