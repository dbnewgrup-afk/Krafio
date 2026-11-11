<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Commission;
use App\Services\Integrations;
use App\Services\Wallet;
use Illuminate\Support\Facades\Http;

/** Alur C — Top-up, checkout, kupon, prorata, downgrade, webhook gateway (PRD 5.3–5.6, 55.4–55.5). */
class BillingFlowTest extends KrafioTestCase
{
    public function test_c1_topup_limits_and_simulated_payment_is_idempotent(): void
    {
        $u = $this->user();
        $this->actingUser($u);
        $this->json_('POST', '/app/wallet/topups', ['amount' => 49000, 'method' => 'va'])->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->json_('POST', '/app/wallet/topups', ['amount' => 1000001, 'method' => 'va'])->assertStatus(422);
        $p = $this->json_('POST', '/app/wallet/topups', ['amount' => 100000, 'method' => 'va'])->assertCreated()->json('data');
        $this->assertSame('pending', $p['status']);
        // simulasi hanya untuk APP_ENV=local → di testing ditolak 404
        $this->json_('POST', "/app/wallet/topups/{$p['id']}/simulate-paid")->assertNotFound();
        \App\Services\Billing::markPaid($p['id']);
        \App\Services\Billing::markPaid($p['id']); // duplikat tidak boleh menambah dua kali
        $this->assertSame(100000, Wallet::balance($u->id, 'topup'));
    }

    public function test_c2_buy_plan_from_balance_with_bonus_coupon_and_commission_on_paid_amount(): void
    {
        $ref = $this->user();
        $u = $this->user(['referred_by' => $ref->id], 'free', 100000);
        $this->actingUser($u);
        $q = $this->json_('POST', '/app/orders/quote', ['kind' => 'plan', 'code' => 'starter', 'cycle' => 'monthly', 'coupon' => 'BONUS100'])->assertOk();
        $this->assertSame(59000, $q->json('data.final'));
        $this->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => 'starter', 'cycle' => 'monthly', 'source' => 'balance', 'coupon' => 'BONUS100'])->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertSame(41000, Wallet::balance($u->id, 'topup'));
        $this->json_('GET', '/me')->assertJsonPath('data.plan.code', 'starter')->assertJsonPath('data.entitlements.credits.remaining', 400); // 300 paket + 100 bonus
        $c = Commission::where('referred_id', $u->id)->firstOrFail();
        $this->assertSame(5900, (int) $c->amount); // 10% dari nominal dibayar
        $this->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => 'pro', 'cycle' => 'monthly', 'source' => 'balance', 'coupon' => 'TIDAKADA'])->assertStatus(422)->assertJsonPath('code', 'coupon_invalid');
    }

    public function test_c3_upgrade_is_prorated_and_downgrade_is_scheduled(): void
    {
        $u = $this->user([], 'free', 500000);
        $this->actingUser($u);
        $this->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => 'starter', 'cycle' => 'monthly', 'source' => 'balance'])->assertOk();
        $q = $this->json_('POST', '/app/orders/quote', ['kind' => 'plan', 'code' => 'pro', 'cycle' => 'monthly'])->json('data');
        $this->assertTrue($q['is_upgrade']);
        $this->assertGreaterThan(50000, $q['prorata']);
        $this->assertSame('Upgrade Starter ke Pro', $q['coupon']['name']); // promo upgrade otomatis
        $this->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => 'pro', 'cycle' => 'monthly', 'source' => 'balance'])->assertOk();
        $this->json_('GET', '/me')->assertJsonPath('data.plan.code', 'pro')->assertJsonPath('data.entitlements.credits.remaining', 1000);
        $this->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => 'starter', 'cycle' => 'monthly', 'source' => 'balance'])->assertOk()->assertJsonPath('data.status', 'scheduled');
        $this->assertSame('starter', Subscription::where('user_id', $u->id)->where('status', 'active')->first()->scheduled_plan_code);
    }

    public function test_c4_insufficient_balance_and_addon_credit_pack(): void
    {
        $u = $this->user([], 'free', 10000);
        $this->actingUser($u);
        $this->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => 'pro', 'cycle' => 'monthly', 'source' => 'balance'])->assertStatus(422)->assertJsonPath('code', 'insufficient_balance');
        Wallet::post($u->id, 'topup', 'credit', 100000, 'uji', ['test', null]);
        $this->json_('POST', '/app/orders', ['kind' => 'addon', 'code' => 'credits-200', 'source' => 'balance'])->assertOk();
        $this->json_('GET', '/me')->assertJsonPath('data.entitlements.credits.remaining', 250);
        $this->json_('POST', '/app/orders', ['kind' => 'addon', 'code' => 'seo-advanced', 'source' => 'balance'])->assertStatus(422)->assertJsonPath('code', 'addon_conflict');
    }

    public function test_c5_midtrans_gateway_create_and_signed_webhook(): void
    {
        Integrations::save('payment', 'midtrans', ['server_key' => 'SB-Mid-server-TEST', 'client_key' => 'SB-Mid-client-TEST', 'is_production' => false], true, null);
        Http::fake(['app.sandbox.midtrans.com/*' => Http::response(['token' => 'tok', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/tok'], 201)]);
        $u = $this->user();
        $this->actingUser($u);
        $p = $this->json_('POST', '/app/wallet/topups', ['amount' => 150000, 'method' => 'qris'])->assertCreated()->json('data');
        $this->assertStringContainsString('midtrans.com', $p['redirect_url']);

        $body = ['order_id' => $p['reference'], 'status_code' => '200', 'gross_amount' => '150000.00', 'transaction_status' => 'settlement', 'fraud_status' => 'accept'];
        $this->json_('POST', '/webhooks/payment/midtrans', $body + ['signature_key' => 'salah'])->assertStatus(401);
        $sig = hash('sha512', $p['reference'].'200'.'150000.00'.'SB-Mid-server-TEST');
        $this->json_('POST', '/webhooks/payment/midtrans', $body + ['signature_key' => $sig])->assertOk();
        $this->json_('POST', '/webhooks/payment/midtrans', $body + ['signature_key' => $sig])->assertOk(); // duplikat
        $this->assertSame(150000, Wallet::balance($u->id, 'topup'));
        $this->assertSame('paid', Payment::where('reference', $p['reference'])->value('status'));
        $this->assertDatabaseHas('webhook_logs', ['source' => 'payment:midtrans', 'signature_valid' => 1, 'status' => 'processed']);
    }

    public function test_c6_xendit_and_tripay_webhook_verification(): void
    {
        $u = $this->user();
        Integrations::save('payment', 'xendit', ['secret_key' => 'xnd_test', 'callback_token' => 'cb-token-uji'], true, null);
        $p = Payment::create(['user_id' => $u->id, 'reference' => 'KRFX1', 'purpose' => 'topup', 'amount' => 50000, 'status' => 'pending', 'expires_at' => now()->addDay()]);
        $this->json_('POST', '/webhooks/payment/xendit', ['external_id' => 'KRFX1', 'status' => 'PAID', 'amount' => 50000], ['x-callback-token' => 'salah'])->assertStatus(401);
        $this->json_('POST', '/webhooks/payment/xendit', ['external_id' => 'KRFX1', 'status' => 'PAID', 'amount' => 50000], ['x-callback-token' => 'cb-token-uji'])->assertOk();
        $this->assertSame('paid', $p->fresh()->status);

        Integrations::save('payment', 'tripay', ['merchant_code' => 'T1', 'api_key' => 'k', 'private_key' => 'priv-uji'], true, null);
        $p2 = Payment::create(['user_id' => $u->id, 'reference' => 'KRFT1', 'purpose' => 'topup', 'amount' => 60000, 'status' => 'pending', 'expires_at' => now()->addDay()]);
        $raw = json_encode(['merchant_ref' => 'KRFT1', 'status' => 'PAID', 'total_amount' => 60000]);
        $this->call('POST', '/api/webhooks/payment/tripay', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_CALLBACK_SIGNATURE' => hash_hmac('sha256', $raw, 'priv-uji')], $raw)->assertOk();
        $this->assertSame('paid', $p2->fresh()->status);
        $this->assertSame(110000, Wallet::balance($u->id, 'topup'));
    }

    public function test_c7_expired_payment_releases_coupon_reservation(): void
    {
        $u = $this->user([], 'free');
        $this->actingUser($u);
        $this->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => 'starter', 'cycle' => 'monthly', 'source' => 'gateway', 'coupon' => 'BONUS100'])->assertOk()->assertJsonPath('data.status', 'pending_payment');
        $this->assertSame(1, \App\Models\Coupon::where('code', 'BONUS100')->value('reserved'));
        Payment::where('user_id', $u->id)->update(['expires_at' => now()->subMinute()]);
        \App\Services\Billing::releaseExpiredReservations();
        $this->assertSame(0, \App\Models\Coupon::where('code', 'BONUS100')->value('reserved'));
        $this->assertDatabaseHas('coupon_redemptions', ['user_id' => $u->id, 'status' => 'released']);
    }

    public function test_c8_trial_once_per_phone(): void
    {
        $u = $this->user(['phone' => '+628111222333']);
        $this->actingUser($u)->json_('POST', '/app/trial')->assertNoContent();
        // Paket Gratis diganti trial Pro: saldo = 100 kredit trial
        $this->json_('GET', '/me')->assertJsonPath('data.plan.code', 'pro')->assertJsonPath('data.entitlements.credits.remaining', 100)->assertJsonPath('data.trial_available', false);
        $this->json_('POST', '/app/trial')->assertStatus(422)->assertJsonPath('code', 'trial_used');
    }
}
