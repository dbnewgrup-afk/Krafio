<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\Commission;
use App\Models\CustomPackage;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\ReferralClick;
use App\Models\User;
use App\Models\UserAddon;
use App\Models\WebhookLog;
use App\Models\Withdrawal;
use App\Models\WithdrawalDestination;
use App\Notifications\Notice;
use App\Services\Audit;
use App\Services\Billing;
use App\Services\Settings;
use App\Services\Wallet;
use App\Services\Payment\JualKirimInvoices;
use App\Services\Payment\PaymentGateway;
use Illuminate\Support\Facades\Cache;
use App\Support\ApiException;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class BillingController extends Controller
{
    public function subscription(Request $r)
    {
        $u = $r->user();
        $cur = $u->activeSubscription();
        $plan = $cur ? \App\Models\Plan::where('code', $cur->plan_code)->first() : null;

        return ['data' => [
            'current' => $cur ? [
                'plan' => $plan ? Present::plan($plan) : ['code' => $cur->plan_code, 'name' => $cur->plan_name],
                'status' => $cur->status, 'starts_at' => $cur->starts_at?->toIso8601String(), 'ends_at' => $cur->ends_at?->toIso8601String(), 'cycle' => $cur->cycle,
                'granted_by_admin' => $cur->source === 'admin',
                // Akhir periode terakhir yang sudah dibayar (termasuk perpanjangan di muka)
                'paid_until' => \App\Models\Subscription::where('user_id', $u->id)->where('status', 'active')->where('plan_code', $cur->plan_code)->latest('ends_at')->first()?->ends_at?->toIso8601String() ?? $cur->ends_at?->toIso8601String(),
            ] : null,
            'history' => $u->subscriptions()->latest('id')->limit(30)->get()->map(fn ($s) => [
                'id' => $s->id, 'plan_name' => $s->plan_name, 'status' => $s->ends_at && $s->ends_at->isPast() && $s->status === 'active' ? 'expired' : $s->status,
                'starts_at' => $s->starts_at?->toIso8601String(), 'ends_at' => $s->ends_at?->toIso8601String(), 'price' => (int) $s->price,
            ]),
        ]];
    }

    /** Rincian harga sebelum bayar: normal, prorata upgrade, diskon kupon/promo terbaik, PPN (PRD 55.4–55.5). */
    public function quote(Request $r)
    {
        $data = $r->validate(['kind' => 'required|in:plan,addon,custom', 'code' => 'required|string|max:40', 'cycle' => 'nullable|in:monthly,yearly', 'coupon' => 'nullable|string|max:40']);
        $u = $r->user();
        $item = Billing::resolve($u, $data['kind'], $data['code'], $data['cycle'] ?? null);
        $cur = $u->activeSubscription();
        $isDowngrade = $data['kind'] === 'plan' && $cur && $cur->plan_code !== $data['code'] && $cur->source !== 'trial' && $cur->price > 0 && ! Billing::isUpgrade($u, $item);

        return ['data' => Billing::quote($u, $item, $data['coupon'] ?? null) + ['is_downgrade' => $isDowngrade, 'downgrade_at' => $isDowngrade ? $cur->ends_at?->toIso8601String() : null]];
    }

    /** Checkout: saldo top-up (atomik) atau payment gateway (aktif setelah webhook) — PRD 5.4, 55. */
    public function order(Request $r)
    {
        $u = $r->user();
        $data = $r->validate(['kind' => 'required|in:plan,addon,custom', 'code' => 'required|string|max:40', 'cycle' => 'nullable|in:monthly,yearly', 'source' => 'required|in:balance,gateway', 'coupon' => 'nullable|string|max:40']);
        $item = Billing::resolve($u, $data['kind'], $data['code'], $data['cycle'] ?? null);
        $idem = $r->header('Idempotency-Key');
        $purpose = $data['kind'] === 'plan' ? 'subscription' : $data['kind'];

        // Downgrade berlaku pada periode tagihan berikutnya (BR-034)
        $cur = $u->activeSubscription();
        if ($data['kind'] === 'plan' && $cur && $cur->plan_code !== $data['code'] && $cur->source !== 'trial' && $cur->price > 0 && ! Billing::isUpgrade($u, $item)) {
            // Berlaku di akhir periode TERAKHIR (bila sudah diperpanjang di muka)
            $tail = \App\Models\Subscription::where('user_id', $u->id)->where('status', 'active')->latest('ends_at')->first() ?? $cur;
            $tail->update(['scheduled_plan_code' => $data['code'], 'scheduled_cycle' => $item['cycle']]);
            Audit::log('billing.downgrade_scheduled', $tail, ['to' => $data['code']]);

            return ['data' => ['status' => 'scheduled', 'effective_at' => $tail->ends_at?->toIso8601String()]];
        }

        $q = Billing::quote($u, $item, $data['coupon'] ?? null);
        if (! empty($data['coupon']) && $q['coupon_error']) {
            throw new ApiException('coupon_invalid', $q['coupon_error'], 422, [], ['coupon' => [$q['coupon_error']]]);
        }
        $final = $q['final'];

        if ($data['source'] === 'gateway' && $final > 0) {
            $open = Payment::where('user_id', $u->id)->where('status', 'pending')->where('purpose', '!=', 'topup')
                ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()))->latest('id')->get();
            // Klik "bayar" berulang untuk item & nominal yang sama → pakai tagihan yang sudah ada (tanpa invoice ganda)
            $same = $open->first(fn ($o) => $o->purpose === $purpose && $o->purpose_code === $data['code'] && $o->cycle === $item['cycle'] && (int) $o->amount === $final && ! empty($o->gateway_payload['redirect_url']));
            if ($same) {
                return ['data' => ['status' => 'pending_payment', 'payment' => self::paymentData($same), 'simulate' => $this->canSimulate(), 'reused' => true]];
            }
            // Satu tagihan paket (plan/custom) terbuka per akun: hindari dua paket berbeda sama-sama dibayar
            $plan = in_array($purpose, ['subscription', 'custom'], true) ? $open->first(fn ($o) => in_array($o->purpose, ['subscription', 'custom'], true)) : null;
            if ($plan) {
                throw new ApiException('pending_payment_exists', 'Masih ada tagihan paket yang belum dibayar ('.$plan->reference.'). Selesaikan atau batalkan dulu.', 409, ['payment' => self::paymentData($plan)]);
            }
            $p = Billing::newPayment($u, $purpose, $data['code'], $item['cycle'], $final, 'gateway', $idem, $q);
            Billing::reserveCoupon($u, $q['coupon'], $q['discount'], $p->id); // BR-031
            $gw = $this->openGateway($p, $u, 'Krafio '.$item['name']);

            return ['data' => ['status' => 'pending_payment', 'payment' => ['id' => $p->id, 'reference' => $p->reference, 'amount' => $final, 'redirect_url' => $gw['redirect_url']], 'simulate' => $this->canSimulate()]];
        }
        if ($idem && LedgerEntry::where('user_id', $u->id)->where('ref_type', 'order')->where('description', 'like', "%[{$idem}]")->exists()) {
            return ['data' => ['status' => 'active']]; // permintaan ganda
        }
        DB::transaction(function () use ($u, $item, $idem, $q, $final) {
            if ($final > 0) {
                Wallet::post($u->id, 'topup', 'debit', $final, 'Pembelian '.$item['name'].($idem ? " [{$idem}]" : ''), ['order', null]);
            }
            $red = Billing::reserveCoupon($u, $q['coupon'], $q['discount'], null);
            Billing::activate($u, $item, $final, null, $q['coupon']);
            Billing::finalizeCoupon($red, true);
            // Bila aktivasi gagal, transaksi DB dibatalkan seluruhnya sehingga debit ikut batal (kompensasi PRD 5.4).
        });
        Audit::log('billing.order', $u, ['kind' => $item['kind'], 'code' => $item['code'], 'normal' => $q['normal'], 'paid' => $final, 'coupon' => $q['coupon']['code'] ?? null]);

        return ['data' => ['status' => 'active']];
    }

    public function cancelDowngrade(Request $r)
    {
        \App\Models\Subscription::where('user_id', $r->user()->id)->where('status', 'active')->update(['scheduled_plan_code' => null, 'scheduled_cycle' => null]);

        return response()->noContent();
    }

    public function autoRenew(Request $r)
    {
        $r->user()->forceFill(['auto_renew' => $r->validate(['enabled' => 'required|boolean'])['enabled']])->save();

        return response()->noContent();
    }

    public function trial(Request $r)
    {
        Billing::startTrial($r->user());

        return response()->noContent();
    }

    /** Prakiraan kapan kredit habis dari laju pemakaian 14 hari terakhir. */
    public function creditForecast(Request $r)
    {
        return ['data' => \App\Services\Ops::creditForecast($r->user())];
    }

    public function creditHistory(Request $r)
    {
        $q = \App\Models\CreditLedgerEntry::where('user_id', $r->user()->id)->latest('id');

        return Present::paginate($q->paginate(min(100, (int) $r->query('per_page', 25))), fn ($e) => [
            'id' => $e->id, 'direction' => $e->direction, 'amount' => (int) $e->amount, 'description' => $e->description, 'created_at' => $e->created_at->toIso8601String(),
        ]);
    }

    public function myAddons(Request $r)
    {
        return ['data' => UserAddon::where('user_id', $r->user()->id)->latest()->get()->map(fn ($a) => [
            'id' => $a->id, 'name' => $a->name, 'status' => $a->ends_at && $a->ends_at->isPast() ? 'expired' : $a->status, 'ends_at' => $a->ends_at?->toIso8601String(),
        ])];
    }

    public function customPackages(Request $r)
    {
        return ['data' => CustomPackage::where('status', 'published')->where(fn ($q) => $q->whereNull('offered_to')->orWhere('offered_to', $r->user()->id))->get()->map(fn ($c) => [
            'id' => $c->id, 'code' => $c->code, 'name' => $c->name, 'description' => $c->description, 'price' => (int) $c->price, 'duration_days' => $c->duration_days, 'features' => $c->features,
        ])];
    }

    public function requestCustom(Request $r)
    {
        $data = $r->validate(['note' => 'required|string|min:10|max:2000']);
        $u = $r->user();
        $a = Approval::create(['type' => 'custom_package', 'subject_type' => 'User', 'subject_id' => $u->id, 'user_id' => $u->id, 'title' => 'Permintaan custom package dari '.$u->email, 'payload' => $data]);
        Notification::send(User::where('role', 'admin')->get(), new Notice('Permintaan custom package', $u->email, 'approval', '/admin/approvals/custom-packages'));

        return response()->json(['data' => ['id' => $a->id]], 201);
    }

    /* ---------------- Wallet ---------------- */
    public function ledger(Request $r)
    {
        $q = LedgerEntry::where('user_id', $r->user()->id);
        if (in_array($w = $r->query('wallet'), Wallet::WALLETS, true)) {
            $q->where('wallet', $w);
        }
        if ($s = $r->query('search')) {
            $q->where('description', 'like', "%{$s}%");
        }
        $sort = (string) $r->query('sort', '');
        $q->orderBy(in_array(ltrim($sort, '-'), ['amount', 'created_at'], true) ? ltrim($sort, '-') : 'id', $sort && ! str_starts_with($sort, '-') ? 'asc' : 'desc');
        if ($r->query('export') === 'csv') {
            return $this->csv('ledger.csv', ['Tanggal', 'Dompet', 'Arah', 'Nominal', 'Saldo setelah', 'Keterangan'], $q->limit(5000)->get()->map(fn ($e) => [$e->created_at, $e->wallet, $e->direction, $e->amount, $e->balance_after, $e->description]));
        }

        return Present::paginate($q->paginate(min(100, (int) $r->query('per_page', 25))), fn ($e) => [
            'id' => $e->id, 'wallet' => $e->wallet, 'direction' => $e->direction, 'amount' => (int) $e->amount, 'balance_after' => (int) $e->balance_after, 'description' => $e->description, 'created_at' => $e->created_at->toIso8601String(),
        ]);
    }

    public static function csv(string $name, array $head, $rows)
    {
        return response()->streamDownload(function () use ($head, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $head);
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, (array) $row)); // cegah formula injection
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    public function topup(Request $r)
    {
        $min = (int) Settings::get('wallet.topup_min');
        $max = (int) Settings::get('wallet.topup_max');
        $data = $r->validate(['amount' => "required|integer|min:{$min}|max:{$max}", 'method' => 'required|in:va,ewallet,qris'], [
            'amount.min' => 'Minimal top-up Rp'.number_format($min, 0, ',', '.').'.', 'amount.max' => 'Maksimal top-up Rp'.number_format($max, 0, ',', '.').' per transaksi.',
        ]);
        // Top-up nominal sama yang masih menunggu dibayar → pakai tagihan yang ada (hindari invoice ganda)
        $same = Payment::where('user_id', $r->user()->id)->where('purpose', 'topup')->where('status', 'pending')->where('amount', (int) $data['amount'])
            ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()))->latest('id')->first();
        if ($same && ! empty($same->gateway_payload['redirect_url'])) {
            return response()->json(['data' => ['id' => $same->id, 'reference' => $same->reference, 'amount' => (int) $same->amount, 'status' => $same->status, 'redirect_url' => $same->gateway_payload['redirect_url'], 'simulate' => $this->canSimulate(), 'reused' => true]], 200);
        }
        $p = Billing::newPayment($r->user(), 'topup', null, null, (int) $data['amount'], $data['method'], $r->header('Idempotency-Key'));
        Audit::log('wallet.topup_created', $p, ['amount' => $p->amount]);
        $gw = $this->openGateway($p, $r->user(), 'Top-up saldo Krafio');

        return response()->json(['data' => ['id' => $p->id, 'reference' => $p->reference, 'amount' => (int) $p->amount, 'status' => $p->status, 'redirect_url' => $gw['redirect_url'], 'simulate' => $this->canSimulate()]], 201);
    }

    /**
     * Buat tagihan di gateway aktif (sekali per payment; idempoten lewat gateway_payload).
     * Bila gateway menolak, payment ditandai gagal dan reservasi kupon dilepas — tidak ada tagihan yatim.
     */
    private function openGateway(Payment $p, $u, string $desc): array
    {
        if (! empty($p->gateway_payload['redirect_url'])) {
            return ['redirect_url' => $p->gateway_payload['redirect_url']];
        }
        try {
            $res = PaymentGateway::create($p, $u, $desc);
        } catch (\Throwable $e) {
            $p->update(['status' => 'failed', 'gateway_payload' => ['error' => $e instanceof ApiException ? $e->errorCode : 'gateway_error']]);
            Billing::finalizeCoupon(\App\Models\CouponRedemption::where('payment_id', $p->id)->where('status', 'reserved')->first(), false);
            throw $e;
        }
        $p->update(['gateway' => PaymentGateway::driver(), 'gateway_payload' => $res]);

        return $res;
    }

    public static function paymentData(Payment $p): array
    {
        return [
            'id' => $p->id, 'reference' => $p->reference, 'purpose' => $p->purpose, 'purpose_code' => $p->purpose_code, 'cycle' => $p->cycle,
            'amount' => (int) $p->amount, 'status' => $p->status, 'redirect_url' => $p->gateway_payload['redirect_url'] ?? null,
            'expires_at' => $p->expires_at?->toIso8601String(), 'created_at' => $p->created_at?->toIso8601String(),
        ];
    }

    /** Tagihan gateway milik user yang masih bisa dibayar (untuk banner "tagihan belum dibayar"). */
    public function pendingPayments(Request $r)
    {
        $list = Payment::where('user_id', $r->user()->id)->where('status', 'pending')->where('method', '!=', 'balance')
            ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()))->latest('id')->limit(10)->get()
            ->filter(fn ($p) => ! empty($p->gateway_payload['redirect_url']) || $this->canSimulate());

        return ['data' => $list->map(fn ($p) => self::paymentData($p))->values()];
    }

    /** Batalkan tagihan yang belum dibayar. JualKirim: invoice ikut dihapus agar link bayar mati. */
    public function cancelPayment(Request $r, Payment $payment)
    {
        abort_unless($payment->user_id === $r->user()->id, 404);
        if ($payment->gateway === JualKirimInvoices::DRIVER && $payment->status === 'pending') {
            if (JualKirimInvoices::sync($payment) !== 'pending') { // ternyata sudah dibayar/batal di gateway
                return ['data' => self::paymentData($payment->fresh())];
            }
            JualKirimInvoices::cancelRemote($payment);
        }
        $done = DB::transaction(function () use ($payment) {
            $p = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if ($p->status !== 'pending') {
                return false;
            }
            $p->update(['status' => 'cancelled']);
            Billing::finalizeCoupon(\App\Models\CouponRedemption::where('payment_id', $p->id)->where('status', 'reserved')->first(), false);
            Approval::where('type', 'payment')->where('subject_id', $p->id)->where('status', 'pending')->update(['status' => 'rejected', 'reason' => 'Dibatalkan pengguna.', 'decided_at' => now()]);

            return true;
        });
        if (! $done) {
            throw new ApiException('not_cancellable', 'Tagihan ini sudah tidak dapat dibatalkan.', 422);
        }
        Audit::log('payment.cancelled', $payment, ['by' => 'user']);

        return ['data' => self::paymentData($payment->fresh())];
    }

    /** Status pembayaran untuk polling klien. Gateway tanpa webhook (JualKirim) dicek langsung, maks. sekali per 10 detik. */
    public function paymentStatus(Request $r, Payment $payment)
    {
        abort_unless($payment->user_id === $r->user()->id, 404);
        if ($payment->gateway === JualKirimInvoices::DRIVER && $payment->status === 'pending' && Cache::add("jualkirim:sync:{$payment->id}", 1, 10)) {
            try {
                JualKirimInvoices::sync($payment);
                $payment->refresh();
            } catch (\Throwable) {
                // Status tetap pending; scheduler akan mencoba lagi.
            }
        }

        return ['data' => ['id' => $payment->id, 'reference' => $payment->reference, 'purpose' => $payment->purpose, 'amount' => (int) $payment->amount, 'status' => $payment->status]];
    }

    private function canSimulate(): bool
    {
        return app()->environment('local') && PaymentGateway::driver() === 'manual';
    }

    /** Simulasi pembayaran — HANYA environment lokal tanpa gateway (pengujian alur top-up). */
    public function simulatePaid(Request $r, Payment $payment)
    {
        abort_unless($payment->user_id === $r->user()->id && $this->canSimulate(), 404);
        Billing::markPaid($payment->id, ['simulated' => true]);

        return response()->noContent();
    }

    /** Webhook payment gateway: verifikasi HMAC, nominal, referensi, idempoten (PRD 5.3 butir 7). */
    public function webhook(Request $r, string $gateway)
    {
        $log = WebhookLog::create(['source' => "payment:{$gateway}", 'event' => (string) ($r->input('transaction_status') ?? $r->input('status') ?? $r->input('event', 'payment')), 'signature_valid' => false, 'status' => 'received', 'payload' => $r->except(['card', 'cvv', 'signature_key'])]);
        if (in_array($gateway, ['midtrans', 'xendit', 'tripay'], true)) {
            $v = PaymentGateway::verifyWebhook($gateway, $r); // melempar 401 bila signature salah
        } else {
            // Gateway generik/manual: HMAC-SHA256 body dengan secret dari Settings
            $secret = (string) Settings::get("payment.webhook_secret.{$gateway}");
            if ($secret === '' || ! hash_equals(hash_hmac('sha256', $r->getContent(), $secret), (string) $r->header('X-Signature'))) {
                throw new ApiException('invalid_signature', 'Signature tidak valid.', 401);
            }
            $v = ['reference' => (string) $r->input('reference'), 'status' => (string) $r->input('status'), 'amount' => (int) $r->input('amount')];
        }
        $log->update(['signature_valid' => true]);
        $p = Payment::where('reference', $v['reference'])->first();
        if (! $p || ($v['amount'] && (int) $v['amount'] !== (int) $p->amount)) {
            $log->update(['status' => 'mismatch']);
            throw new ApiException('payment_mismatch', 'Data pembayaran tidak cocok.', 422);
        }
        if ($v['status'] === 'paid' && in_array($p->status, ['cancelled', 'failed', 'expired'], true) && $p->gateway !== JualKirimInvoices::DRIVER) {
            // Dibayar setelah dibatalkan/kedaluwarsa: jangan aktifkan diam-diam, minta admin menindaklanjuti
            if (empty($p->gateway_payload['late_paid'])) {
                $p->update(['gateway_payload' => array_merge($p->gateway_payload ?? [], ['late_paid' => true])]);
                Audit::log('payment.late_paid', $p, ['gateway' => $gateway, 'amount' => (int) $p->amount], $p->user_id);
                Notification::send(User::where('role', 'admin')->get(), new Notice('Pembayaran terlambat perlu dicek', $p->reference.' dibayar setelah tagihan '.$p->status.'.', 'billing', '/admin/finance/payments'));
            }
        } elseif ($v['status'] === 'paid' && $p->gateway === JualKirimInvoices::DRIVER) {
            JualKirimInvoices::confirmPaid($p, ['confirmed_by' => 'webhook', 'webhook_id' => $log->id]); // hormati mode auto/manual
        } elseif ($v['status'] === 'paid') {
            Billing::markPaid($p->id, ['gateway' => $gateway, 'webhook_id' => $log->id]);
        } elseif (in_array($v['status'], ['failed', 'expired'], true) && $p->status === 'pending') {
            $p->update(['status' => $v['status']]);
            Billing::finalizeCoupon(\App\Models\CouponRedemption::where('payment_id', $p->id)->where('status', 'reserved')->first(), false);
        }
        $log->update(['status' => 'processed']);

        return ['data' => ['ok' => true]];
    }

    public function destinations(Request $r)
    {
        return ['data' => WithdrawalDestination::where('user_id', $r->user()->id)->latest()->get()->map(fn ($d) => $this->dest($d))];
    }

    private function dest(WithdrawalDestination $d): array
    {
        return ['id' => $d->id, 'type' => $d->type, 'provider' => $d->provider, 'holder' => $d->holder, 'account_masked' => '****'.$d->account_last4];
    }

    public function addDestination(Request $r)
    {
        $data = $r->validate(['type' => 'required|in:bank,ewallet', 'provider' => 'required|string|max:60', 'holder' => 'required|string|max:120', 'account' => 'required|regex:/^\d{6,20}$/'], ['account.regex' => 'Nomor rekening/akun berupa 6–20 digit angka.']);
        if (WithdrawalDestination::where('user_id', $r->user()->id)->count() >= 5) {
            throw new ApiException('limit_reached', 'Maksimal 5 tujuan pencairan.', 422);
        }
        $d = WithdrawalDestination::create(['user_id' => $r->user()->id, 'type' => $data['type'], 'provider' => $data['provider'], 'holder' => $data['holder'], 'account_number' => $data['account'], 'account_last4' => substr($data['account'], -4)]);
        Audit::log('wallet.destination_added', $d);

        return response()->json(['data' => $this->dest($d)], 201);
    }

    public function deleteDestination(Request $r, WithdrawalDestination $destination)
    {
        abort_unless($destination->user_id === $r->user()->id, 404);
        $destination->delete();

        return response()->noContent();
    }

    public function withdrawals(Request $r)
    {
        return ['data' => Withdrawal::where('user_id', $r->user()->id)->latest()->limit(50)->get()->map(fn ($w) => self::wd($w))];
    }

    public static function wd(Withdrawal $w): array
    {
        return [
            'id' => $w->id, 'amount' => (int) $w->amount, 'status' => $w->status, 'destination' => $w->destination_snapshot, 'admin_note' => $w->admin_note,
            'reference' => $w->reference, 'created_at' => $w->created_at->toIso8601String(), 'updated_at' => $w->updated_at->toIso8601String(),
        ];
    }

    /** Withdrawal: hanya saldo komisi; nominal dipindah ke reserved (debit ledger) agar tidak diajukan dua kali (PRD 5.5). */
    public function requestWithdrawal(Request $r)
    {
        $u = $r->user();
        $min = (int) Settings::get('wallet.withdraw_min');
        $data = $r->validate(['amount' => "required|integer|min:{$min}", 'destination_id' => 'required|integer'], ['amount.min' => 'Minimal withdrawal Rp'.number_format($min, 0, ',', '.').'.']);
        $dest = WithdrawalDestination::where('id', $data['destination_id'])->where('user_id', $u->id)->firstOrFail();
        $idem = $r->header('Idempotency-Key');
        if ($idem && ($w = Withdrawal::where('idempotency_key', $idem)->first())) {
            return ['data' => self::wd($w)];
        }
        $w = DB::transaction(function () use ($u, $data, $dest, $idem) {
            $w = Withdrawal::create(['user_id' => $u->id, 'destination_id' => $dest->id, 'destination_snapshot' => ['type' => $dest->type, 'provider' => $dest->provider, 'holder' => $dest->holder, 'account_masked' => '****'.$dest->account_last4], 'amount' => $data['amount'], 'status' => 'requested', 'idempotency_key' => $idem]);
            Wallet::post($u->id, 'commission', 'debit', (int) $data['amount'], 'Withdrawal #'.$w->id.' (ditahan)', ['withdrawal', $w->id]);
            Approval::create(['type' => 'withdrawal', 'subject_type' => 'Withdrawal', 'subject_id' => $w->id, 'user_id' => $u->id, 'title' => 'Withdrawal Rp'.number_format($w->amount, 0, ',', '.').' — '.$u->email, 'payload' => ['amount' => $w->amount]]);

            return $w;
        });
        Notification::send(User::where('role', 'admin')->get(), new Notice('Withdrawal baru', $u->email.' — Rp'.number_format($w->amount, 0, ',', '.'), 'approval', '/admin/approvals/withdrawals'));
        Audit::log('wallet.withdrawal_requested', $w, ['amount' => $w->amount]);

        return response()->json(['data' => self::wd($w)], 201);
    }

    /* ---------------- Affiliate ---------------- */
    public function affiliate(Request $r)
    {
        $u = $r->user();
        Billing::releaseCommissions($u->id);
        $c = Commission::where('referrer_id', $u->id);
        $sum = fn ($s) => (int) (clone $c)->where('status', $s)->sum('amount');

        return ['data' => [
            'code' => $u->referral_code,
            'link' => rtrim(config('krafio.frontend_url'), '/').'/register?ref='.$u->referral_code,
            'active' => (bool) Settings::get('affiliate.active'),
            'rate_label' => Settings::get('affiliate.rate_percent').'%',
            'stats' => [
                'clicks' => ReferralClick::where('referrer_id', $u->id)->count(),
                'referrals' => User::where('referred_by', $u->id)->count(),
                'conversions' => (clone $c)->distinct('referred_id')->count('referred_id'),
                'pending' => $sum('pending') + $sum('approved'), 'available' => Wallet::balance($u->id, 'commission'), 'reversed' => $sum('reversed'), 'paid' => $sum('paid'),
            ],
        ]];
    }

    public function commissions(Request $r)
    {
        $q = Commission::with('referred')->where('referrer_id', $r->user()->id)->latest();
        if ($s = $r->query('status')) {
            $q->where('status', $s);
        }

        return Present::paginate($q->paginate(min(100, (int) $r->query('per_page', 25))), fn ($c) => [
            'id' => $c->id, 'referred' => $c->referred ? Present::mask($c->referred->email) : '—', 'amount' => (int) $c->amount, 'status' => $c->status, 'created_at' => $c->created_at->toIso8601String(),
        ]);
    }

    public function trackClick(Request $r, string $code)
    {
        $u = User::where('referral_code', strtoupper($code))->first();
        if ($u) {
            ReferralClick::create(['referrer_id' => $u->id, 'ip_hash' => hash('sha256', (string) $r->ip())]);
        }

        return response()->noContent();
    }
}
