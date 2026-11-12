<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Domain kustom website (paket Pro+): verifikasi kepemilikan lewat TXT + arah CNAME sebelum dipakai. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $t) {
            $t->string('domain_status', 20)->default('none')->after('custom_domain'); // none | pending | verified | failed
            $t->string('domain_token', 64)->nullable()->after('domain_status');
            $t->timestamp('domain_verified_at')->nullable()->after('domain_token');
            $t->timestamp('domain_checked_at')->nullable()->after('domain_verified_at');
            $t->string('domain_error', 255)->nullable()->after('domain_checked_at');
        });
        // Domain yang sudah diisi admin sebelumnya dianggap perlu verifikasi ulang
        DB::table('websites')->whereNotNull('custom_domain')->where('custom_domain', '!=', '')
            ->update(['domain_status' => 'pending', 'domain_token' => DB::raw('LOWER(HEX(RANDOM_BYTES(16)))')]);
    }

    public function down(): void
    {
        Schema::table('websites', fn (Blueprint $t) => $t->dropColumn(['domain_status', 'domain_token', 'domain_verified_at', 'domain_checked_at', 'domain_error']));
    }
};
