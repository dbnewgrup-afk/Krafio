<?php

use App\Models\EmailTemplate;
use App\Support\LifecycleMail;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blueprint "Siklus Langganan" & "Saldo, Komisi Affiliate":
 * - alasan & waktu pembatalan komisi (refund/chargeback/kecurangan selama masa tahan);
 * - template email pengingat, perpanjangan gagal, paket berakhir, withdrawal disetujui, komisi dibatalkan.
 */
return new class extends Migration
{
    /** Teks bawaan lama — hanya template yang belum diedit admin yang diperbarui. */
    private const OLD_EXPIRING = "Halo {{name}},\n\nPaket {{plan}} berakhir pada {{ends_at}}. Perpanjang agar fitur tetap terbuka.";

    public function up(): void
    {
        Schema::table('commissions', function (Blueprint $t) {
            $t->string('reversal_reason', 500)->nullable()->after('available_at');
            $t->timestamp('reversed_at')->nullable()->after('reversal_reason');
        });

        foreach (LifecycleMail::TEMPLATES as $key => [$sid, $bid, $sen, $ben]) {
            $row = EmailTemplate::where('key', $key)->first();
            $attrs = ['subject_id' => $sid, 'body_id' => $bid, 'subject_en' => $sen, 'body_en' => $ben, 'is_active' => true];
            if (! $row) {
                EmailTemplate::create(['key' => $key] + $attrs);
            } elseif ($key === 'subscription_expiring' && $row->body_id === self::OLD_EXPIRING) {
                $row->update($attrs);
            }
        }
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $t) {
            $t->dropColumn(['reversal_reason', 'reversed_at']);
        });
        EmailTemplate::whereIn('key', ['renewal_failed', 'subscription_ended', 'withdrawal_approved', 'commission_reversed'])->delete();
    }
};
