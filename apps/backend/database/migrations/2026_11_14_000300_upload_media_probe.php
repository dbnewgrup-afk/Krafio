<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Durasi & resolusi video diukur (ffprobe) saat diunggah → biaya kredit edit/clipper dari durasi asli. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploads', function (Blueprint $t) {
            $t->decimal('duration', 9, 2)->nullable()->after('size');
            $t->unsignedInteger('width')->nullable()->after('duration');
            $t->unsignedInteger('height')->nullable()->after('width');
        });
    }

    public function down(): void
    {
        Schema::table('uploads', fn (Blueprint $t) => $t->dropColumn(['duration', 'width', 'height']));
    }
};
