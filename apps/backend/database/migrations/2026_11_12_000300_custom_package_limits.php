<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom package (Enterprise, PRD 55.2.3) kini bisa mengatur batas struktural sendiri
 * (proyek, website, akun sosial, storage, job paralel, dll.) selain fitur & kuota.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_packages', function (Blueprint $t) {
            $t->json('limits')->nullable()->after('quotas');
        });
    }

    public function down(): void
    {
        Schema::table('custom_packages', function (Blueprint $t) {
            $t->dropColumn('limits');
        });
    }
};
