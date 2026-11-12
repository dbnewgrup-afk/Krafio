<?php

namespace App\Services;

use App\Http\Controllers\Api\AuthController;
use App\Models\Approval;
use App\Models\Commission;
use App\Models\CreditLedgerEntry;
use App\Models\CreditPack;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserAddon;
use App\Notifications\Notice;
use App\Support\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Refund (PRD: "Pengajuan refund — perlu pemeriksaan → approve/reject"; "Refund/reversal membuat ledger entry baru";
 * blueprint: komisi dibatalkan saat refund). Kebijakan detail (PRD P-09) diatur Admin lewat pengaturan:
 * refund.enabled, refund.window_days, refund.purposes.
 *
 * - Paket/add-on/custom: dana kembali ke saldo top-up (default) atau ditransfer manual (wajib nomor referensi);
 *   item dihentikan, kredit paket/add-on dari pembayaran itu kedaluwarsa, komisi affiliate dibatalkan/ditarik.
 * - Top-up: hanya bila saldo top-up masih cukup; saldo didebit lalu dana ditransfer manual (wajib referensi).
 * Gateway refund otomatis (API Midtrans/Xendit/Tripay/JualKirim) belum dipakai — butuh credential & izin refund dari gateway.
 */
class Refunds
{
    public const PURPOSES = ['subscription', 'addon', 'custom', 'topup'];

    /** @return array{0:bool,1:?string} [bisa?, alasan bila tidak] */
    public static function eligibility(User $u, Payment $p): array
    {
        $window = (int) Settings::get('refund.window_days', 7);
        $purposes = (array) Settings::get('refund.purposes', self::PURPOSES);
        if (! Settings::get('refund.enabled', true)) {
            return [false, 'Pengajuan refund sedang ditutup.'];
        }
        if ($p->user_id !== $u->id || $p->status !== 'paid' || $p->refunded_at) {
            return [false, 'Pembayaran ini tidak dapat direfund.'];
        }
        if (! in_array($p->purpose, $purposes, true)) {
            return [false, 'Jenis pembelian ini tidak dapat direfund.'];
        }
        if (! $p->paid_at || $p->paid_at->lt(now()->subDays($window))) {
            return [false, "Batas pengajuan refund {$window} hari setelah pembayaran sudah lewat."];
        }
        if (Approval::where('type', 'refund')->where('subject_id', $p->id)->where('status', 'pending')->exists()) {
            return [false, 'Pengajuan refund untuk pembayaran ini sedang diperiksa.'];
        }
        if ($p->purpose === 'topup' && Wallet::balance($u->id, 'topup') < (int) $p->amount) {
            return [false, 'Saldo top-up sudah terpakai sehingga top-up ini tidak dapat direfund.'];
        }

        return [true, null];
    }

    public static function request(User $u, Payment $p, string $reason): Approval
    {
        [$ok, $why] = self::eligibility($u, $p);
        if (! $ok) {
            throw new ApiException('refund_not_allowed', $why, 422);
        }
        $a = Approval::create([
            'type' => 'refund', 'subject_type' => 'Payment', 'subject_id' => $p->id, 'user_id' => $u->id,
            'title' => 'Refund '.$p->reference.' — Rp'.number_format((int) $p->amount, 0, ',', '.').' — '.$u->email,
            'payload' => ['reference' => $p->reference, 'amount' => (int) $p->amount, 'purpose' => $p->purpose, 'item' => $p->purpose_code, 'gateway' => $p->gateway, 'paid_at' => $p->paid_at?->toIso8601String(), 'reason' => $reason],
        ]);
        Notification::send(User::where('role', 'admin')->get(), new Notice('Pengajuan refund', $p->reference.' — Rp'.number_format((int) $p->amount, 0, ',', '.'), 'approval', '/admin/approvals/refunds'));
        $u->notify(new Notice('Refund diajukan', 'Pengajuan refund '.$p->reference.' sedang diperiksa admin.', 'transaction', '/app/subscription'));
        Audit::log('refund.requested', $p, ['reason' => $reason], $u->id);

        return $a;
    }

    /** Dipanggil dari Approval (sudah di dalam transaksi DB & sudah re-auth admin). */
    public static function approve(Approval $a, User $admin, string $method = 'balance', ?string $reference = null): void
    {
        $p = Payment::lockForUpdate()->findOrFail($a->subject_id);
        if ($p->status !== 'paid' || $p->refunded_at) {
            throw new ApiException('refund_not_allowed', 'Pembayaran ini sudah tidak dapat direfund.', 422);
        }
        $u = User::findOrFail($p->user_id);
        $amount = (int) $p->amount;
        $method = $p->purpose === 'topup' ? 'transfer' : ($method === 'transfer' ? 'transfer' : 'balance');
        if ($method === 'transfer' && mb_strlen(trim((string) $reference)) < 3) {
            throw new ApiException('validation_failed', 'Nomor referensi transfer wajib diisi.', 422, [], ['reference' => ['Nomor referensi transfer wajib diisi.']]);
        }

        // 1) Uang (ledger entry baru, tidak menghapus transaksi lama)
        if ($p->purpose === 'topup') {
            if (Wallet::balance($u->id, 'topup') < $amount) {
                throw new ApiException('insufficient_balance', 'Saldo top-up pengguna sudah tidak mencukupi untuk refund top-up ini.', 422);
            }
            Wallet::post($u->id, 'topup', 'debit', $amount, 'Refund top-up '.$p->reference.' (transfer ke rekening)', ['payment_refund', $p->id], $reference, $admin->id);
        } elseif ($method === 'balance') {
            Wallet::post($u->id, 'topup', 'credit', $amount, 'Refund '.$p->reference, ['order_refund', $p->id], null, $admin->id);
        }

        // 2) Item dihentikan
        $itemNote = self::stopItems($u, $p);

        // 3) Komisi affiliate dibatalkan / ditarik kembali
        self::reverseCommissions($p, 'Refund pembayaran '.$p->reference);

        $p->update(['status' => 'refunded', 'refunded_at' => now(), 'refund_amount' => $amount, 'refund_method' => $method, 'refund_reference' => $reference]);
        Audit::log('refund.approved', $p, ['amount' => $amount, 'method' => $method, 'reference' => $reference], $admin->id);
        $amountTxt = 'Rp'.number_format($amount, 0, ',', '.');
        $methodNote = $method === 'balance' ? "Dana {$amountTxt} sudah masuk ke saldo top-up Anda." : "Dana {$amountTxt} ditransfer ke rekening Anda (ref {$reference}).";
        Messenger::notify($u, 'refund_approved', ['reference' => $p->reference, 'amount' => $amountTxt, 'method_note' => $methodNote, 'item_note' => $itemNote, 'link' => rtrim((string) config('krafio.frontend_url'), '/').'/app/wallet'],
            'Refund disetujui', $methodNote.' '.$itemNote, '/app/wallet');
    }

    public static function reject(Approval $a, string $reason): void
    {
        $p = Payment::find($a->subject_id);
        if ($p && ($u = User::find($p->user_id))) {
            Messenger::notify($u, 'refund_rejected', ['reference' => $p->reference, 'reason' => $reason, 'link' => rtrim((string) config('krafio.frontend_url'), '/').'/app/support'],
                'Refund ditolak', 'Pengajuan refund '.$p->reference.' ditolak: '.$reason, '/app/support');
            Audit::log('refund.rejected', $p, ['reason' => $reason]);
        }
    }

    /** Hentikan paket/add-on dari pembayaran ini & kedaluwarsakan kredit yang berasal darinya. */
    private static function stopItems(User $u, Payment $p): string
    {
        if ($p->purpose === 'topup') {
            return 'Saldo top-up dikurangi sebesar nominal refund.';
        }
        if ($p->purpose === 'addon') {
            UserAddon::where('payment_id', $p->id)->update(['status' => 'cancelled', 'ends_at' => now()]);
            $packIds = CreditLedgerEntry::where('ref_type', 'addon')->where('ref_id', $p->id)->pluck('pack_id');
            CreditPack::whereIn('id', $packIds)->update(['expires_at' => now()]);

            return 'Add-on '.$p->purpose_code.' dihentikan.';
        }
        // Paket katalog / custom package: periode yang dibayar (berjalan atau di muka) dihentikan
        $subs = Subscription::where('payment_id', $p->id)->get();
        if ($subs->isEmpty()) { // data lama sebelum pembayaran ditautkan: periode aktif terakhir dari paket yang sama
            $subs = Subscription::where('user_id', $u->id)->where('plan_code', $p->purpose_code)->whereIn('status', ['active', 'past_due'])->latest('ends_at')->limit(1)->get();
        }
        foreach ($subs as $s) {
            $s->update(['status' => 'cancelled', 'ends_at' => $s->starts_at && $s->starts_at->isFuture() ? $s->starts_at : now(), 'scheduled_plan_code' => null, 'scheduled_cycle' => null]);
            CreditPack::where('subscription_id', $s->id)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->update(['expires_at' => now()]);
        }
        if (! $u->fresh()->activeSubscription()) {
            AuthController::grantFreePlan($u, true, notify: false);
        }

        return 'Paket '.($subs->first()->plan_name ?? $p->purpose_code).' dihentikan; akun kembali ke paket yang berlaku.';
    }

    /**
     * Komisi masa tahan → dibatalkan. Komisi yang sudah masuk saldo komisi → ditarik kembali sebatas saldo yang ada
     * (ledger debit "Koreksi komisi"), sisanya dicatat di audit untuk ditindaklanjuti admin.
     */
    public static function reverseCommissions(Payment $p, string $reason): void
    {
        foreach (Commission::where('payment_id', $p->id)->whereNotIn('status', ['reversed'])->lockForUpdate()->get() as $c) {
            $short = 0;
            if (in_array($c->status, ['available', 'paid'], true)) {
                $take = min((int) $c->amount, Wallet::balance($c->referrer_id, 'commission'));
                if ($take > 0) {
                    Wallet::post($c->referrer_id, 'commission', 'debit', $take, 'Koreksi komisi #'.$c->id.' (refund '.$p->reference.')', ['commission_reversal', $c->id], $reason);
                }
                $short = (int) $c->amount - $take;
            }
            $c->forceFill(['status' => 'reversed', 'reversal_reason' => $reason, 'reversed_at' => now()])->save();
            if ($ref = User::find($c->referrer_id)) {
                $amount = 'Rp'.number_format((int) $c->amount, 0, ',', '.');
                Messenger::notify($ref, 'commission_reversed', ['amount' => $amount, 'reason' => $reason, 'link' => rtrim((string) config('krafio.frontend_url'), '/').'/app/affiliate'], 'Komisi dibatalkan', 'Komisi '.$amount.' dibatalkan: '.$reason, '/app/affiliate');
            }
            Audit::log('commission.reversed', $c, ['reason' => $reason, 'uncollected' => $short]);
        }
    }

    /** Riwayat pembayaran user + status refund (halaman Langganan). */
    public static function history(User $u): array
    {
        $pending = Approval::where('type', 'refund')->where('user_id', $u->id)->where('status', 'pending')->pluck('subject_id')->all();

        return Payment::where('user_id', $u->id)->whereIn('status', ['paid', 'refunded'])->latest('paid_at')->limit(30)->get()->map(function (Payment $p) use ($u, $pending) {
            [$ok] = self::eligibility($u, $p);

            return [
                'id' => $p->id, 'reference' => $p->reference, 'purpose' => $p->purpose, 'item' => $p->purpose_code, 'cycle' => $p->cycle, 'amount' => (int) $p->amount,
                'gateway' => $p->gateway, 'status' => $p->status, 'paid_at' => $p->paid_at?->toIso8601String(), 'refunded_at' => $p->refunded_at?->toIso8601String(),
                'refund_method' => $p->refund_method, 'refund_pending' => in_array($p->id, $pending, true), 'refundable' => $ok,
            ];
        })->all();
    }
}
