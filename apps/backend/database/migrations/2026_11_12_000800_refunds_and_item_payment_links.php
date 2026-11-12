<?php

use App\Models\EmailTemplate;
use App\Support\LifecycleMail;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refund (PRD Approval "Pengajuan refund", 31.x "Refund/reversal membuat ledger entry baru"):
 * - jejak refund di pembayaran; paket & add-on tertaut ke pembayaran asal agar refund menonaktifkan item yang tepat;
 * - template email keputusan refund (dan template siklus yang belum ada di instalasi berjalan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $t) {
            $t->timestamp('refunded_at')->nullable()->after('paid_at');
            $t->unsignedBigInteger('refund_amount')->nullable()->after('refunded_at');
            $t->string('refund_method', 20)->nullable()->after('refund_amount'); // balance | transfer
            $t->string('refund_reference', 120)->nullable()->after('refund_method');
        });
        Schema::table('subscriptions', function (Blueprint $t) {
            $t->foreignId('payment_id')->nullable()->after('user_id')->constrained('payments')->nullOnDelete();
        });
        Schema::table('user_addons', function (Blueprint $t) {
            $t->foreignId('payment_id')->nullable()->after('user_id')->constrained('payments')->nullOnDelete();
        });
        foreach (LifecycleMail::TEMPLATES as $key => [$sid, $bid, $sen, $ben]) {
            EmailTemplate::firstOrCreate(['key' => $key], ['subject_id' => $sid, 'body_id' => $bid, 'subject_en' => $sen, 'body_en' => $ben, 'is_active' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('user_addons', fn (Blueprint $t) => $t->dropConstrainedForeignId('payment_id'));
        Schema::table('subscriptions', fn (Blueprint $t) => $t->dropConstrainedForeignId('payment_id'));
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn(['refunded_at', 'refund_amount', 'refund_method', 'refund_reference']));
        EmailTemplate::whereIn('key', ['refund_approved', 'refund_rejected'])->delete();
    }
};
