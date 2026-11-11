<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akun yang dibuat Admin: langsung aktif, tetapi user wajib memverifikasi email ATAU nomor HP,
 * dan diingatkan untuk mengganti kata sandi awal (tanpa paksaan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            $t->boolean('must_change_password')->default(false)->after('password');
            $t->foreignId('created_by')->nullable()->after('referred_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('created_by');
            $t->dropColumn(['phone_verified_at', 'must_change_password']);
        });
    }
};
