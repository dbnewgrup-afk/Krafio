<?php

namespace Tests\Feature\JualKirim;

use App\Facades\JualKirim;
use App\Models\Approval;
use App\Models\Payment;
use App\Services\Billing;
use App\Services\Integrations;
use App\Services\JualKirim\JualKirimClient;
use App\Services\Payment\JualKirimInvoices;
use App\Services\Payment\PaymentGateway;
use App\Services\Wallet;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Feature\KrafioTestCase;

/**
 * Driver pembayaran "jualkirim" (Invoice JualKirim) untuk langganan/add-on/top-up Krafio.
 * Seluruh request JualKirim memakai Http::fake(); tidak ada panggilan ke API asli.
 */
class JualKirimPaymentTest extends KrafioTestCase
{
    private string $invoiceStatus = 'sent';

    private ?int $invoiceTotal = null;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'jualkirim.base_url' => 'https://jk.test',
            'jualkirim.email' => 'ops@krafio.test',
            'jualkirim.password' => 'rahasia-uji',
            'jualkirim.api_key' => 'KEYUJI0000000000000000A',
            'jualkirim.retry.sleep_ms' => 0,
        ]);
        $this->app->forgetInstance(JualKirimClient::class);
        JualKirim::clearResolvedInstances();
        Cache::flush();

        Http::fake(function (Request $r) {
            $path = parse_url($r->url(), PHP_URL_PATH);

            return match (true) {
                $path === '/api/v1/auth/login' => Http::response(['data' => ['token' => 'eyJ.uji.token', 'organizations' => [['id' => 'org-1', 'name' => 'Krafio', 'api_key' => 'KEYUJI0000000000000000A']]]]),
                $path === '/api/v1/me' => Http::response(['data' => ['user' => ['first_name' => 'Ops'], 'organizations' => [['id' => 'org-1', 'name' => 'Krafio', 'api_key' => 'KEYUJI0000000000000000A']]]]),
                $r->method() === 'POST' && $path === '/api/v1/invoices' => Http::response(['message' => 'Tagihan berhasil dibuat', 'data' => ['id' => 'inv-1', 'invoice_number' => 'INV-20261111-0001', 'status' => 'sent', 'grand_total' => $r['items'][0]['price'], 'payment_link' => 'https://app.jualkirim.id/invoice/pay/abc']], 201),
                $r->method() === 'GET' && $path === '/api/v1/invoices/inv-1' => Http::response(['data' => ['invoice' => ['id' => 'inv-1', 'status' => $this->invoiceStatus, 'grand_total' => $this->invoiceTotal ?? Payment::latest('id')->value('amount')], 'status_label' => 'x', 'payment_link' => 'https://app.jualkirim.id/invoice/pay/abc']]),
                default => Http::response(['message' => 'tidak ada'], 404),
            };
        });
    }

    private function useJualKirim(string $confirmation = 'auto'): void
    {
        Integrations::save('payment', 'jualkirim', ['settlement_type' => 'wallet', 'admin_fee_paid_by' => 'merchant', 'confirmation' => $confirmation], true, null);
    }

    /** Panggil endpoint status (bypass jeda 10 detik antar-sinkron). */
    private function poll(int $paymentId)
    {
        Cache::forget("jualkirim:sync:{$paymentId}");

        return $this->json_('GET', "/app/payments/{$paymentId}")->assertOk();
    }

    public function test_auto_topup_creates_invoice_and_activates_once_paid(): void
    {
        $this->useJualKirim('auto');
        $u = $this->user(['phone' => '+6281234567890']);
        $this->actingUser($u);

        $p = $this->json_('POST', '/app/wallet/topups', ['amount' => 150000, 'method' => 'qris'])->assertCreated()->json('data');
        $this->assertSame('https://app.jualkirim.id/invoice/pay/abc', $p['redirect_url']);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/api/v1/invoices')
            && $r['customer_phone'] === '081234567890' && $r['customer_email'] === $u->email
            && $r['items'][0]['price'] === 150000 && $r['items'][0]['qty'] === 1
            && $r['settlement_type'] === 'wallet' && $r['admin_fee_paid_by'] === 'merchant'
            && $r['notes'] === 'Ref Krafio '.$p['reference'] && ! isset($r['bank_account_id']));
        $this->assertSame('inv-1', Payment::find($p['id'])->gateway_payload['gateway_ref']);

        $this->poll($p['id'])->assertJsonPath('data.status', 'pending');
        $this->assertSame(0, Wallet::balance($u->id, 'topup'));

        $this->invoiceStatus = 'paid';
        $this->poll($p['id'])->assertJsonPath('data.status', 'paid');
        $this->poll($p['id'])->assertJsonPath('data.status', 'paid'); // idempoten
        $this->assertSame(150000, Wallet::balance($u->id, 'topup'));
        $this->assertSame('jualkirim_poll', Payment::find($p['id'])->gateway_payload['confirmed_by']);
    }

    public function test_auto_subscription_activates_via_scheduler_sync(): void
    {
        $this->useJualKirim('auto');
        $u = $this->user();
        $this->actingUser($u);

        $res = $this->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => 'starter', 'cycle' => 'monthly', 'source' => 'gateway'])->assertOk();
        $res->assertJsonPath('data.status', 'pending_payment')->assertJsonPath('data.payment.redirect_url', 'https://app.jualkirim.id/invoice/pay/abc');

        $this->assertSame(0, JualKirimInvoices::syncPending());
        $this->invoiceStatus = 'paid';
        $this->assertSame(1, JualKirimInvoices::syncPending());
        $this->json_('GET', '/me')->assertJsonPath('data.plan.code', 'starter');
    }

    public function test_manual_mode_requires_admin_approval_even_when_paid(): void
    {
        $this->useJualKirim('manual');
        $u = $this->user();
        $this->actingUser($u);
        $p = $this->json_('POST', '/app/wallet/topups', ['amount' => 100000, 'method' => 'va'])->assertCreated()->json('data');

        $ap = Approval::where('type', 'payment')->where('subject_id', $p['id'])->firstOrFail();
        $this->assertSame('sent', $ap->payload['gateway_status']);
        $this->assertSame(100000, $ap->payload['amount']);

        $this->invoiceStatus = 'paid';
        $this->poll($p['id'])->assertJsonPath('data.status', 'pending'); // tidak aktif otomatis
        $this->assertSame(0, Wallet::balance($u->id, 'topup'));
        $this->assertSame('paid', $ap->fresh()->payload['gateway_status']); // admin diberi tanda "lunas"

        $this->admin();
        $this->json_('GET', '/admin/approvals?type=payment')->assertOk()->assertJsonPath('data.0.payload.gateway_status', 'paid');
        $this->json_('POST', "/admin/approvals/{$ap->id}/decide", ['decision' => 'approve'])->assertNoContent();
        $this->assertSame('paid', Payment::find($p['id'])->status);
        $this->assertSame(100000, Wallet::balance($u->id, 'topup'));
        $this->assertSame('admin', Payment::find($p['id'])->gateway_payload['confirmed_by']);
    }

    public function test_manual_mode_admin_can_approve_without_polling_and_reject_with_reason(): void
    {
        $this->useJualKirim('manual');
        $u = $this->user();
        $this->actingUser($u);
        $p1 = $this->json_('POST', '/app/wallet/topups', ['amount' => 60000, 'method' => 'va'], ['Idempotency-Key' => 'a1'])->json('data');
        $p2 = $this->json_('POST', '/app/wallet/topups', ['amount' => 70000, 'method' => 'va'], ['Idempotency-Key' => 'a2'])->json('data');
        $a1 = Approval::where('subject_id', $p1['id'])->where('type', 'payment')->firstOrFail();
        $a2 = Approval::where('subject_id', $p2['id'])->where('type', 'payment')->firstOrFail();

        $this->admin();
        // Admin sudah cek manual di dashboard JualKirim → setujui walau status belum terbaca
        $this->json_('POST', "/admin/approvals/{$a1->id}/decide", ['decision' => 'approve'])->assertNoContent();
        $this->json_('POST', "/admin/approvals/{$a2->id}/decide", ['decision' => 'reject'])->assertStatus(422);
        $this->json_('POST', "/admin/approvals/{$a2->id}/decide", ['decision' => 'reject', 'reason' => 'Belum ada dana masuk'])->assertNoContent();

        $this->assertSame(60000, Wallet::balance($u->id, 'topup'));
        $this->assertSame('failed', Payment::find($p2['id'])->status);
    }

    public function test_manual_mode_closes_approval_when_payment_expires_unpaid(): void
    {
        $this->useJualKirim('manual');
        $u = $this->user();
        $this->actingUser($u);
        $p = $this->json_('POST', '/app/wallet/topups', ['amount' => 80000, 'method' => 'va'])->json('data');

        $this->travel(25)->hours();
        Billing::releaseExpiredReservations();
        JualKirimInvoices::syncPending();

        $ap = Approval::where('type', 'payment')->where('subject_id', $p['id'])->first();
        $this->assertSame('rejected', $ap->status);
        $this->assertStringContainsString('kedaluwarsa', $ap->reason);
    }

    public function test_amount_mismatch_is_never_activated(): void
    {
        $this->useJualKirim('auto');
        $u = $this->user();
        $this->actingUser($u);
        $p = $this->json_('POST', '/app/wallet/topups', ['amount' => 100000, 'method' => 'va'])->json('data');

        $this->invoiceStatus = 'paid';
        $this->invoiceTotal = 1000;
        $this->poll($p['id'])->assertJsonPath('data.status', 'pending');
        $this->assertSame(0, Wallet::balance($u->id, 'topup'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.mismatch']);
    }

    public function test_cancelled_invoice_fails_payment(): void
    {
        $this->useJualKirim('auto');
        $this->actingUser($this->user());
        $p = $this->json_('POST', '/app/wallet/topups', ['amount' => 100000, 'method' => 'va'])->json('data');

        $this->invoiceStatus = 'cancelled';
        $this->poll($p['id'])->assertJsonPath('data.status', 'failed');
    }

    public function test_user_without_phone_is_asked_to_complete_profile(): void
    {
        $this->useJualKirim('auto');
        $u = $this->user();
        $u->forceFill(['phone' => null])->save();
        $this->actingUser($u);

        $this->json_('POST', '/app/wallet/topups', ['amount' => 100000, 'method' => 'va'])->assertStatus(422)->assertJsonPath('code', 'phone_required');
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/api/v1/invoices'));
    }

    public function test_other_users_cannot_read_payment_status(): void
    {
        $this->useJualKirim('auto');
        $this->actingUser($this->user());
        $p = $this->json_('POST', '/app/wallet/topups', ['amount' => 100000, 'method' => 'va'])->json('data');

        $this->actingUser($this->user());
        $this->json_('GET', "/app/payments/{$p['id']}")->assertNotFound();
    }

    public function test_connection_test_checks_api_key_against_organization(): void
    {
        [$ok, $msg] = PaymentGateway::test(['driver' => 'jualkirim']);
        $this->assertTrue($ok, $msg);
        $this->assertStringContainsString('Krafio', $msg);

        config(['jualkirim.api_key' => 'ZZZZZZZZZZZZZZZZZZZZZZZ']);
        $this->app->forgetInstance(JualKirimClient::class);
        JualKirim::clearResolvedInstances();
        [$ok] = PaymentGateway::test(['driver' => 'jualkirim']);
        $this->assertFalse($ok);
    }
}
