<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rasio 4 template bawaan diselaraskan dengan desain resmi (assets/krafio_website_assets/02_katalog_template)
 * agar foto tidak terpotong di kartu katalog. Hanya mengubah template bawaan yang rasionya belum diubah admin.
 */
return new class extends Migration
{
    private const MAP = [
        'Batik modern flatlay' => ['4:5', '16:9'],
        'Skincare botol kaca' => ['1:1', '9:16'],
        'Testimoni pelanggan' => ['9:16', '1:1'],
        'Tutorial 30 detik' => ['16:9', '9:16'],
    ];

    public function up(): void
    {
        foreach (self::MAP as $title => [$old, $new]) {
            DB::table('templates')->where('title_id', $title)->where('ratio', $old)->update(['ratio' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::MAP as $title => [$old, $new]) {
            DB::table('templates')->where('title_id', $title)->where('ratio', $new)->update(['ratio' => $old]);
        }
    }
};
