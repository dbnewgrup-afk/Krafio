<?php

namespace App\Services\Payment;

use App\Facades\JualKirim;
use App\Models\Approval;
use App\Models\CouponRedemption;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\Notice;
use App\Services\Audit;
use App\Services\Billing;
use App\Services\JualKirim\Exceptions\JualKirimException;
use App\Services\JualKirim\JualKirimClient;
use App\Support\ApiException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Driver pembayaran "jualkirim": langganan, add-on, dan top-up dibayar lewat Invoice JualKirim
 * (tag Invoice = penagihan ke customer). Pelanggan membayar di `payment_link` publik JualKirim.
 *
 * JualKirim tidak punya webhook, jadi status lunas diambil dengan polling `GET /api/v1/invoices/{id}`:
 * - scheduler tiap menit (syncPending), dan
 * - saat pelanggan membuka status pembayaran (GET /app/payments/{id}).
 * Aktivasi tetap lewat Billing::markPaid (idempoten), sama seperti webhook gateway lain.
 */
class JualKirimInvoices
{
    public const DRIVER = 'jualkirim';

    /** @return array{redirect_url:string, gateway_ref:string, invoice_number:?string} */
    public static function open(Payment $p, User $u, string $description, array $c): array
    {
        $phone = self::phone($u->phone);
        if (! $phone) {
            throw new ApiException('phone_required', 'Lengkapi nomor HP di Profil sebelum membayar.', 422);
        }
        $settlement = ($c['settlement_type'] ?? '') ?: 'wallet';
        if ($settlement === 'bank' && empty($c['bank_account_id'])) {
            throw new ApiException('gateway_misconfigured', 'Pembayaran belum dapat diproses. Hubungi admin.', 503);
        }
        $tz = 'Asia/Jakarta';
        $payload = array_filter([
            'customer_name' => mb_substr($u->name, 0, 120),
            'customer_phone' => $phone,
            'customer_email' => $u->email,
            'admin_fee_paid_by' => ($c['admin_fee_paid_by'] ?? '') ?: 'merchant',
            'settlement_type' => $settlement,
            'bank_account_id' => $settlement === 'bank' ? $c['bank_account_id'] : null,
            'invoice_date' => now($tz)->toDateString(),
            'due_date' => ($p->expires_at ?? now()->addDay())->copy()->setTimezone($tz)->toDateString(),
            'notes' => 'Ref Krafio '.$p->reference,
            'items' => [['description' => mb_substr($description, 0, 200), 'qty' => 1, 'unit' => 'paket', 'price' => (int) $p->amount]],
        ], fn ($v) => $v !== null);

        try {
            $data = JualKirim::invoices()->create($payload)['data'] ?? [];
        } catch (JualKirimException $e) {
            Log::channel('jualkirim')->warning('Gagal membuat invoice untuk pembayaran Krafio', ['payment' => $p->reference, 'status' => $e->status]);
            throw new ApiException('gateway_error', 'Payment gateway menolak permintaan. Coba lagi beberapa saat lagi.', 502);
        }
        if (empty($data['id']) || empty($data['payment_link'])) {
            throw new ApiException('gateway_error', 'Payment gateway tidak mengembalikan link pembayaran.', 502);
        }
        if (isset($data['grand_total']) && (int) round((float) $data['grand_total']) !== (int) $p->amount) {
            Log::channel('jualkirim')->warning('Total invoice berbeda dari nominal pembayaran', ['payment' => $p->reference, 'amount' => (int) $p->amount, 'grand_total' => $data['grand_total']]);
        }

        if (self::mode($c) === 'manual') {
            self::openApproval($p, $u, $data['invoice_number'] ?? null);
        }

        return ['redirect_url' => (string) $data['payment_link'], 'gateway_ref' => (string) $data['id'], 'invoice_number' => $data['invoice_number'] ?? null];
    }

    /**
     * Mode konfirmasi (diatur Admin di Integrasi → Payment):
     * - auto   : lunas di JualKirim → langsung aktif (polling; atau webhook bila kelak tersedia).
     * - manual : setiap tagihan masuk antrean Approval "payment"; admin memverifikasi lalu menyetujui.
     */
    public static function mode(?array $c = null): string
    {
        $c ??= PaymentGateway::config();

        return ($c['driver'] ?? null) === self::DRIVER && ($c['confirmation'] ?? '') === 'manual' ? 'manual' : 'auto';
    }

    private static function openApproval(Payment $p, User $u, ?string $invoiceNumber): void
    {
        if (Approval::where('type', 'payment')->where('subject_id', $p->id)->exists()) {
            return;
        }
        Approval::create([
            'type' => 'payment', 'subject_type' => 'Payment', 'subject_id' => $p->id, 'user_id' => $u->id,
            'title' => 'Pembayaran Rp'.number_format((int) $p->amount, 0, ',', '.').' — '.$p->reference.' ('.$u->email.')',
            'payload' => ['amount' => (int) $p->amount, 'reference' => $p->reference, 'purpose' => $p->purpose, 'invoice_number' => $invoiceNumber, 'gateway_status' => 'sent'],
        ]);
    }

    private static function approval(Payment $p): ?Approval
    {
        return Approval::where('type', 'payment')->where('subject_id', $p->id)->where('status', 'pending')->first();
    }

    /** Catat status JualKirim di approval agar admin tahu tagihan sudah lunas (mode manual). */
    private static function annotateApproval(Payment $p, string $status): void
    {
        $a = self::approval($p);
        if (! $a || ($a->payload['gateway_status'] ?? null) === $status) {
            return;
        }
        $a->update(['payload' => array_merge($a->payload ?? [], ['gateway_status' => $status])]);
        if ($status === 'paid') {
            Notification::send(User::where('role', 'admin')->get(), new Notice('Pembayaran siap diverifikasi', $p->reference.' sudah lunas di JualKirim.', 'approval', '/admin/approvals/payments'));
        }
    }

    /** Tutup approval yang tidak lagi relevan (tagihan batal/kedaluwarsa tanpa pembayaran). */
    private static function closeApproval(Payment $p, string $reason): void
    {
        self::approval($p)?->update(['status' => 'rejected', 'reason' => $reason, 'decided_at' => now()]);
    }

    /** Cek status invoice di JualKirim lalu aktifkan bila lunas. Mengembalikan status pembayaran terbaru. */
    public static function sync(Payment $p): string
    {
        $ref = $p->gateway_payload['gateway_ref'] ?? null;
        if ($p->gateway !== self::DRIVER || ! $ref || ! in_array($p->status, ['pending', 'expired'], true) || ! empty($p->gateway_payload['late_paid'])) {
            return $p->status;
        }
        $data = JualKirim::invoices()->find($ref)['data'] ?? [];
        $invoice = $data['invoice'] ?? [];
        $status = strtolower((string) ($invoice['status'] ?? $data['status'] ?? ''));
        $total = $invoice['grand_total'] ?? null;

        if ($status === 'paid') {
            if ($total !== null && (int) round((float) $total) !== (int) $p->amount) {
                Audit::log('payment.mismatch', $p, ['gateway' => self::DRIVER, 'amount' => (int) $p->amount, 'grand_total' => $total], $p->user_id);
                Log::channel('jualkirim')->warning('Invoice lunas tetapi total tidak cocok — tidak diaktifkan', ['payment' => $p->reference]);
                self::annotateApproval($p, 'amount_mismatch');

                return $p->status;
            }
            self::confirmPaid($p, ['confirmed_by' => 'jualkirim_poll', 'invoice_status' => $status]);
        } elseif ($status === 'cancelled' && $p->status === 'pending') {
            $p->update(['status' => 'failed']);
            Billing::finalizeCoupon(CouponRedemption::where('payment_id', $p->id)->where('status', 'reserved')->first(), false);
            self::closeApproval($p, 'Tagihan dibatalkan di JualKirim.');
        } elseif ($p->status === 'expired') {
            self::closeApproval($p, 'Tagihan kedaluwarsa tanpa pembayaran.');
        }

        return (string) $p->fresh()->status;
    }

    /**
     * Tagihan terkonfirmasi lunas (dari polling atau webhook). Mode auto → aktif; mode manual → tandai
     * approval "lunas" dan biarkan admin yang menyetujui. Nominal harus sudah diverifikasi pemanggil.
     */
    public static function confirmPaid(Payment $p, array $meta): void
    {
        if (self::mode() === 'manual' || self::approval($p)) {
            if (self::approval($p)) {
                self::annotateApproval($p, 'paid');
            } elseif ($p->status === 'expired') {
                self::flagLatePaid($p); // approval sudah ditutup karena kedaluwarsa
            }

            return;
        }
        if ($p->status === 'expired') {
            self::flagLatePaid($p);

            return;
        }
        Billing::markPaid($p->id, array_merge($p->gateway_payload ?? [], $meta));
    }

    /** Dipanggil scheduler tiap menit. Kegagalan satu invoice tidak menghentikan yang lain. */
    public static function syncPending(): int
    {
        $n = 0;
        $payments = Payment::where('gateway', self::DRIVER)
            ->where(fn ($q) => $q->where('status', 'pending')->orWhere(fn ($q) => $q->where('status', 'expired')->where('expires_at', '>', now()->subDays(3))))
            ->where('created_at', '>', now()->subDays(7))
            ->orderBy('id')->limit(100)->get();
        foreach ($payments as $p) {
            try {
                $before = $p->status;
                if (self::sync($p) !== $before) {
                    $n++;
                }
            } catch (Throwable $e) {
                Log::channel('jualkirim')->warning('Sinkron status invoice gagal', ['payment' => $p->reference, 'error' => class_basename($e)]);
            }
        }

        return $n;
    }

    /** Uji kredensial (.env) tanpa membuat transaksi. @return array{0:bool,1:string} */
    public static function test(): array
    {
        $client = app(JualKirimClient::class);
        if (! $client->hasCredentials() || ! $client->configuredApiKey()) {
            return [false, 'Isi JUALKIRIM_EMAIL, JUALKIRIM_PASSWORD, dan JUALKIRIM_API_KEY di .env server.'];
        }
        $org = JualKirim::me()->organizationForKey((string) $client->configuredApiKey());

        return $org ? [true, "Terhubung ke JualKirim (organisasi {$org->name})."] : [false, 'JUALKIRIM_API_KEY tidak cocok dengan organisasi akun JualKirim.'];
    }

    /** +62812… / 62812… → 0812… (format contoh di spec JualKirim). */
    public static function phone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }

        return str_starts_with($digits, '62') ? '0'.substr($digits, 2) : $digits;
    }

    /** Pelanggan membayar setelah tagihan Krafio kedaluwarsa: jangan aktifkan otomatis, minta admin menindaklanjuti. */
    private static function flagLatePaid(Payment $p): void
    {
        $p->update(['gateway_payload' => array_merge($p->gateway_payload ?? [], ['late_paid' => true])]);
        Audit::log('payment.late_paid', $p, ['gateway' => self::DRIVER, 'amount' => (int) $p->amount], $p->user_id);
        Notification::send(User::where('role', 'admin')->get(), new Notice('Pembayaran terlambat perlu dicek', $p->reference.' lunas di JualKirim setelah tagihan Krafio kedaluwarsa.', 'billing', '/admin/finance/payments'));
    }
}
