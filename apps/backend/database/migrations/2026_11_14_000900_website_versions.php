<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** B5 / PRD 28.3: riwayat versi website (setiap simpan, terbit, dan pulihkan) agar pengguna bisa kembali ke versi sebelumnya. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('website_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('kind', 12); // save | publish | restore
            $t->json('content');
            $t->timestamp('created_at')->useCurrent();
            $t->index(['website_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_versions');
    }
};
