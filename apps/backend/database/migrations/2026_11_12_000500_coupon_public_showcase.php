<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kupon bisa ditampilkan di halaman Promo & beranda (judul, deskripsi, gambar, urutan). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $t) {
            $t->boolean('is_public')->default(false)->after('is_active');
            $t->string('headline', 120)->nullable()->after('is_public');
            $t->string('description', 500)->nullable()->after('headline');
            $t->string('image', 255)->nullable()->after('description');
            $t->unsignedInteger('sort')->default(0)->after('image');
            $t->index(['is_public', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $t) {
            $t->dropIndex(['is_public', 'is_active']);
            $t->dropColumn(['is_public', 'headline', 'description', 'image', 'sort']);
        });
    }
};
