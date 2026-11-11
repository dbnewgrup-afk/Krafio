<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\User;
use App\Services\Integrations;
use App\Support\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Payment Gateway Adapter (PRD 31.4). Driver: manual (simulasi lokal), midtrans, xendit, tripay.
 * Credential dibaca dari Integrasi yang diatur Admin. Webhook diverifikasi per driver sebelum
 * Billing::markPaid dipanggil (idempoten).
 */
class PaymentGateway
{
    public static function config(): array
    {
        return Integrations::active('payment') ?? ['driver' => 'manual'];
    }

    public static function driver(): string
    {
        return self::config()['driver'];
    }

    /** @return array{redirect_url:?string, gateway_ref:?string} */
    public static function create(Payment $p, User $u, string $description): array
    {
        $c = self::config();
        $frontend = rtrim(config('krafio.frontend_url'), '/');
        $finish = $frontend.($p->purpose === 'topup' ? '/app/wallet' : '/app/subscription').'?payment='.$p->reference;
        $amount = (int) $p->amount;
        $name = mb_substr($description, 0, 50);

        if ($c['driver'] === 'midtrans') {
            $base = ! empty($c['is_production']) ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
            $res = Http::timeout(20)->withBasicAuth((string) $c['server_key'], '')->acceptJson()->post($base.'/snap/v1/transactions', [
                'transaction_details' => ['order_id' => $p->reference, 'gross_amount' => $amount],
                'customer_details' => ['first_name' => $u->name, 'email' => $u->email, 'phone' => $u->phone],
                'item_details' => [['id' => $p->purpose, 'price' => $amount, 'quantity' => 1, 'name' => $name]],
                'callbacks' => ['finish' => $finish],
            ]);
            self::ensure($res);

            return ['redirect_url' => $res->json('redirect_url'), 'gateway_ref' => $res->json('token')];
        }
        if ($c['driver'] === 'xendit') {
            $res = Http::timeout(20)->withBasicAuth((string) $c['secret_key'], '')->acceptJson()->post('https://api.xendit.co/v2/invoices', [
                'external_id' => $p->reference, 'amount' => $amount, 'description' => $description, 'payer_email' => $u->email,
                'success_redirect_url' => $finish, 'failure_redirect_url' => $finish, 'currency' => 'IDR', 'invoice_duration' => 86400,
            ]);
            self::ensure($res);

            return ['redirect_url' => $res->json('invoice_url'), 'gateway_ref' => $res->json('id')];
        }
        if ($c['driver'] === 'tripay') {
            $base = ! empty($c['is_production']) ? 'https://tripay.co.id/api' : 'https://tripay.co.id/api-sandbox';
            $res = Http::timeout(20)->withToken((string) $c['api_key'])->acceptJson()->post($base.'/transaction/create', [
                'method' => ($c['default_method'] ?? '') ?: 'QRIS',
                'merchant_ref' => $p->reference,
                'amount' => $amount,
                'customer_name' => $u->name, 'customer_email' => $u->email, 'customer_phone' => $u->phone,
                'order_items' => [['name' => $name, 'price' => $amount, 'quantity' => 1]],
                'return_url' => $finish,
                'expired_time' => now()->addDay()->timestamp,
                'signature' => hash_hmac('sha256', $c['merchant_code'].$p->reference.$amount, (string) $c['private_key']),
            ]);
            self::ensure($res);

            return ['redirect_url' => $res->json('data.checkout_url'), 'gateway_ref' => $res->json('data.reference')];
        }

        return ['redirect_url' => null, 'gateway_ref' => null];
    }

    private static function ensure($res): void
    {
        if (! $res->successful()) {
            throw new ApiException('gateway_error', 'Payment gateway menolak permintaan ('.$res->status().').', 502);
        }
    }

    /** Verifikasi webhook per driver. @return array{reference:string, status:string, amount:?int} (paid|failed|expired|pending) */
    public static function verifyWebhook(string $driver, Request $r): array
    {
        $c = Integrations::active('payment');
        if (! $c || $c['driver'] !== $driver) {
            throw new ApiException('gateway_inactive', 'Gateway tidak aktif.', 404);
        }
        if ($driver === 'midtrans') {
            $sig = hash('sha512', $r->input('order_id').$r->input('status_code').$r->input('gross_amount').$c['server_key']);
            if (! hash_equals($sig, (string) $r->input('signature_key'))) {
                throw new ApiException('invalid_signature', 'Signature tidak valid.', 401);
            }
            $st = (string) $r->input('transaction_status');
            $fraud = (string) $r->input('fraud_status', 'accept');
            $status = 'pending';
            if (in_array($st, ['settlement', 'capture'], true) && $fraud === 'accept') {
                $status = 'paid';
            } elseif ($st === 'expire') {
                $status = 'expired';
            } elseif (in_array($st, ['cancel', 'deny', 'failure'], true)) {
                $status = 'failed';
            }

            return ['reference' => (string) $r->input('order_id'), 'status' => $status, 'amount' => (int) round((float) $r->input('gross_amount'))];
        }
        if ($driver === 'xendit') {
            if (! hash_equals((string) $c['callback_token'], (string) $r->header('x-callback-token'))) {
                throw new ApiException('invalid_signature', 'Callback token tidak valid.', 401);
            }
            $st = strtoupper((string) $r->input('status'));

            return ['reference' => (string) $r->input('external_id'), 'status' => in_array($st, ['PAID', 'SETTLED'], true) ? 'paid' : ($st === 'EXPIRED' ? 'expired' : 'pending'), 'amount' => (int) $r->input('amount')];
        }
        if ($driver === 'tripay') {
            $sig = hash_hmac('sha256', $r->getContent(), (string) $c['private_key']);
            if (! hash_equals($sig, (string) $r->header('X-Callback-Signature'))) {
                throw new ApiException('invalid_signature', 'Signature tidak valid.', 401);
            }
            $st = strtoupper((string) $r->input('status'));
            $status = $st === 'PAID' ? 'paid' : ($st === 'EXPIRED' ? 'expired' : ($st === 'FAILED' ? 'failed' : 'pending'));

            return ['reference' => (string) $r->input('merchant_ref'), 'status' => $status, 'amount' => (int) $r->input('total_amount', $r->input('amount_received'))];
        }
        throw new ApiException('invalid_driver', 'Driver tidak dikenal.', 404);
    }

    /** Uji kredensial tanpa membuat transaksi. @return array{0:bool,1:string} */
    public static function test(array $c): array
    {
        try {
            if ($c['driver'] === 'midtrans') {
                $base = ! empty($c['is_production']) ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
                $res = Http::timeout(15)->withBasicAuth((string) ($c['server_key'] ?? ''), '')->acceptJson()->get($base.'/v2/krafio-connection-test/status');
                $unauthorized = $res->status() === 401 || (int) $res->json('status_code') === 401;

                return $unauthorized ? [false, 'Server key ditolak Midtrans.'] : [true, 'Kredensial Midtrans diterima.'];
            }
            if ($c['driver'] === 'xendit') {
                $res = Http::timeout(15)->withBasicAuth((string) ($c['secret_key'] ?? ''), '')->acceptJson()->get('https://api.xendit.co/balance');

                return $res->successful() ? [true, 'Terhubung ke Xendit.'] : [false, 'Xendit menolak secret key ('.$res->status().').'];
            }
            if ($c['driver'] === 'tripay') {
                $base = ! empty($c['is_production']) ? 'https://tripay.co.id/api' : 'https://tripay.co.id/api-sandbox';
                $res = Http::timeout(15)->withToken((string) ($c['api_key'] ?? ''))->acceptJson()->get($base.'/merchant/payment-channel');

                return $res->successful() && $res->json('success') ? [true, 'Terhubung ke Tripay ('.count((array) $res->json('data')).' kanal).'] : [false, 'Tripay menolak API key ('.$res->status().').'];
            }

            return [true, 'Mode manual: pembayaran disimulasikan (hanya lingkungan lokal).'];
        } catch (\Throwable $e) {
            return [false, 'Tidak dapat terhubung: '.mb_substr($e->getMessage(), 0, 150)];
        }
    }
}
