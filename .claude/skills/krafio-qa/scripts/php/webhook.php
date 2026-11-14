<?php
// Kirim webhook pembayaran bertanda tangan ke endpoint ASLI (lokal saja). Dipanggil oleh pay.sh lewat tinker.
use App\Models\Payment;
use App\Services\Integrations;
use App\Services\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

if (! app()->environment('local')) { echo "TOLAK: hanya APP_ENV=local\n"; return; }
$ref = getenv('KRAFIO_REF') ?: '';
$status = getenv('KRAFIO_STATUS') ?: 'paid';
$tamper = getenv('KRAFIO_TAMPER') === '1';
$times = max(1, (int) (getenv('KRAFIO_TIMES') ?: 1));
$p = $ref !== '' ? Payment::where('reference', $ref)->first()
    : Payment::where('status', 'pending')->whereHas('user', fn ($q) => $q->where('email', getenv('KRAFIO_EMAIL') ?: 'qa@krafio.test'))->latest('id')->first();
if (! $p) { echo "TIDAK ADA pembayaran ".($ref ?: 'pending milik '.(getenv('KRAFIO_EMAIL') ?: 'qa@krafio.test'))."\n"; return; }
$c = Integrations::active('payment');
$driver = in_array($c['driver'] ?? null, ['midtrans', 'xendit', 'tripay'], true) ? $c['driver'] : 'sim';
$amount = (int) $p->amount;
$headers = ['Accept' => 'application/json'];
switch ($driver) {
    case 'midtrans':
        $map = ['paid' => 'settlement', 'failed' => 'deny', 'expired' => 'expire'];
        $body = ['order_id' => $p->reference, 'status_code' => '200', 'gross_amount' => $amount.'.00', 'transaction_status' => $map[$status] ?? $status, 'fraud_status' => 'accept'];
        $body['signature_key'] = hash('sha512', $body['order_id'].$body['status_code'].$body['gross_amount'].$c['server_key']);
        break;
    case 'xendit':
        $body = ['external_id' => $p->reference, 'status' => strtoupper($status === 'paid' ? 'PAID' : $status), 'amount' => $amount];
        $headers['x-callback-token'] = (string) $c['callback_token'];
        break;
    case 'tripay':
        $body = ['merchant_ref' => $p->reference, 'status' => strtoupper($status), 'total_amount' => $amount];
        $headers['X-Callback-Signature'] = hash_hmac('sha256', json_encode($body), (string) $c['private_key']);
        break;
    default:
        $secret = (string) Settings::get('payment.webhook_secret.sim');
        if ($secret === '') { $secret = Str::random(40); Settings::set('payment.webhook_secret.sim', $secret); }
        $body = ['reference' => $p->reference, 'status' => $status, 'amount' => $amount];
        $headers['X-Signature'] = hash_hmac('sha256', json_encode($body), $secret);
}
if ($tamper) {
    foreach (['signature_key', 'X-Signature', 'X-Callback-Signature', 'x-callback-token'] as $k) {
        if (isset($body[$k])) { $body[$k] = str_repeat('0', 64); }
        if (isset($headers[$k])) { $headers[$k] = str_repeat('0', 64); }
    }
}
$url = 'http://127.0.0.1:8000/api/webhooks/payment/'.$driver;
for ($i = 1; $i <= $times; $i++) {
    $res = Http::withHeaders($headers)->withBody(json_encode($body), 'application/json')->post($url);
    echo "KIRIM #{$i} {$driver} {$p->reference} status={$status} → HTTP {$res->status()} ".mb_substr($res->body(), 0, 160)."\n";
}
$p->refresh();
echo "HASIL pembayaran {$p->reference}: {$p->status} (purpose {$p->purpose}, Rp".number_format($amount, 0, ',', '.').")\n";
