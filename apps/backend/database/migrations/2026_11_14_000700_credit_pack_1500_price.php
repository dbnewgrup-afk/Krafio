<?php

use App\Models\Addon;
use Illuminate\Database\Migrations\Migration;

/**
 * Paket 1.500 kredit: Rp299.000 → Rp315.000 (Rp210/kredit) agar tetap di atas margin 55% walau pembeli datang
 * dari afiliasi (komisi 10%) — lihat report/BIAYA_AI_DAN_MARGIN_2026-11-14.md bagian 8. Hanya bila harga belum diubah Admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Addon::where('code', 'credits-1500')->where('price', 299000)->update(['price' => 315000]);
    }

    public function down(): void
    {
        Addon::where('code', 'credits-1500')->where('price', 315000)->update(['price' => 299000]);
    }
};
