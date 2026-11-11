<?php

namespace Tests\Feature\JualKirim;

use App\Facades\JualKirim;
use App\Models\Commission;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Billing;
use App\Services\Credits;
use App\Services\Integrations;
use App\Services\JualKirim\JualKirimClient;
use App\Services\Payment\JualKirimInvoices;
use App\Services\Settings;
use App\Services\Wallet;
use Carbon\Carbon;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Feature\KrafioTestCase;

/**
 * Alur pembayaran Krafio end-to-end lewat gateway JualKirim (PRD 4.4, 5.3, 5.4, 9.2, 55.5):
 * top-up, beli paket, add-on kredit, upgrade prorata, downgrade, perpanjangan manual & otomatis,
 * past_due, grace period, kupon. JualKirim dipalsukan dengan Http::fake (tanpa API asli).
 */
class PaymentFlowE2ETest extends KrafioTestCase
{
    /** @var array<string, array{amount:int,status:string}> invoice palsu per id */
    private array $invoices = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['jualkirim.base_url' => 'https://jk.test', 'jualkirim.email' => 'ops@krafio.test', 'jualkirim.password' => 'x', 'jualkirim.api_key' => 'KEYUJI0000000000000000A', 'jualkirim.retry.sleep_ms' => 0]);
        $this->app->forgetInstance(JualKirimClient::class);
        JualKirim::clearResolvedInstances();
        Cache::flush();
        Integrations::save('payment', 'jualkirim', ['settlement_type' => 'wallet', 'admin_fee_paid_by' => 'merchant', 'confirmation' => 'auto'], true, null);

        Http::fake(function (Request $r) {
            $path = parse_url($r->url(), PHP_URL_PATH);
            if ($path === '/api/v1/auth/login') {
                return Http::response(['data' => ['token' => 'eyJ.uji', 'organizations' => []]]);
            }
            if ($r->method() === 'POST' && $path === '/api/v1/invoices') {
                $id = 'inv-'.(count($this->invoices) + 1);
                $this->invoices[$id] = ['amount' => (int) $r['items'][0]['price'], 'status' => 'draft'];

                return Http::response(['data' => ['id' => $id, 'invoice_number' => 'BILL/'.$id, 'status' => 'draft', 'grand_total' => $r['items'][0]['price'], 'payment_link' => "https://app.jualkirim.com/invoice/pay/{$id}"]], 201);
            }
            if ($r->method() === 'GET' && preg_match('#^/api/v1/invoices/(inv-\d+)$#', $path, $m) && isset($this->invoices[$m[1]])) {
                return Http::response(['data' => ['invoice' => ['id' => $m[1], 'status' => $this->invoices[$m[1]]['status'], 'grand_total' => $this->invoices[$m[1]]['amount']]]]);
            }

            return Http::response(['message' => 'tidak ada'], 404);
        });
    }

    /** Pelanggan membayar invoice JualKirim untuk payment Krafio ini, lalu scheduler menyinkronkan. */
    private function payAtJualKirim(int $paymentId): void
    {
        $ref = Payment::findOrFail($paymentId)->gateway_payload['gateway_ref'];
        $this->invoices[$ref]['status'] = 'paid';
        JualKirimInvoices::syncPending();
    }

    private function buy(string $kind, string $code, ?string $cycle = 'monthly', ?string $coupon = null): array
    {
        return $this->json_('POST', '/app/orders', array_filter(['kind' => $kind, 'code' => $code, 'cycle' => $cycle, 'source' => 'gateway', 'coupon' => $coupon]))->assertOk()->json('data');
    }

    private function credits(): int
    {
        return (int) $this->json_('GET', '/me')->json('data.entitlements.credits.remaining');
    }

    private function planCode(): ?string
    {
        return $this->json_('GET', '/me')->json('data.plan.code');
    }

    /* ======================= Top-up (PRD 5.3) ======================= */

    public function test_topup_limits_pending_until_paid_credited_once_and_expiry(): void
    {
        $u = $this->user();
        $this->actingUser($u);

        // Batas Rp50.000 – Rp1.000.000
        $this->json_('POST', '/app/wallet/topups', ['amount' => 49999, 'method' => 'qris'])->assertStatus(422);
        $this->json_('POST', '/app/wallet/topups', ['amount' => 1000001, 'method' => 'qris'])->assertStatus(422);
        Http::assertNothingSent();

        $p = $this->json_('POST', '/app/wallet/topups', ['amount' => 100000, 'method' => 'qris'])->assertCreated()->json('data');
        $this->assertSame('pending', $p['status']);
        $this->assertStringStartsWith('https://app.jualkirim.com/invoice/pay/', $p['redirect_url']);
        $this->assertSame(0, Wallet::balance($u->id, 'topup'), 'Saldo tidak bertambah sebelum lunas');

        $this->payAtJualKirim($p['id']);
        JualKirimInvoices::syncPending(); // sinkron ulang (seperti webhook duplikat) tidak menambah dua kali
        Cache::flush();
        $this->json_('GET', "/app/payments/{$p['id']}")->assertJsonPath('data.status', 'paid');
        $this->assertSame(100000, Wallet::balance($u->id, 'topup'));
        $this->assertSame(1, LedgerEntry::where('user_id', $u->id)->where('wallet', 'topup')->where('direction', 'credit')->count(), 'Ledger tercatat satu kali');
        $this->assertTrue($u->notifications()->where('data->title', 'Top-up berhasil')->exists());

        // Top-up kedua kedaluwarsa tanpa dibayar → saldo tetap
        $p2 = $this->json_('POST', '/app/wallet/topups', ['amount' => 50000, 'method' => 'va'])->json('data');
        $this->travel(25)->hours();
        Billing::releaseExpiredReservations();
        $this->assertSame('expired', Payment::find($p2['id'])->status);
        $this->assertSame(100000, Wallet::balance($u->id, 'topup'));
    }

    public function test_topup_never_generates_affiliate_commission(): void
    {
        Settings::set('affiliate.active', true);
        $ref = $this->user();
        $u = $this->user(['referred_by' => $ref->id]);
        $this->actingUser($u);
        $p = $this->json_('POST', '/app/wallet/topups', ['amount' => 200000, 'method' => 'qris'])->json('data');
        $this->payAtJualKirim($p['id']);

        $this->assertSame(200000, Wallet::balance($u->id, 'topup'));
        $this->assertSame(0, Commission::where('referred_id', $u->id)->count());
    }

    /* =================== Langganan baru (PRD 4.4, 9.2) =================== */

    public function test_subscribe_via_gateway_activates_only_after_payment_with_credits_and_commission(): void
    {
        Settings::set('affiliate.active', true);
        $ref = $this->user();
        $u = $this->user(['referred_by' => $ref->id]);
        $this->actingUser($u);
        $this->assertSame('free', $this->planCode());

        $o = $this->buy('plan', 'starter');
        $this->assertSame('pending_payment', $o['status']);
        $paid = $o['payment']['amount'];
        $this->assertSame(53100, $paid, 'Promo referral 10% otomatis untuk pembelian pertama');
        $this->assertSame('free', $this->planCode(), 'Belum aktif sebelum pembayaran terkonfirmasi (redirect tidak cukup)');
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/api/v1/invoices') && $r['items'][0]['price'] === $paid && str_contains($r['items'][0]['description'], 'Starter'));

        $this->payAtJualKirim($o['payment']['id']);

        $this->assertSame('starter', $this->planCode());
        $this->assertSame(300, $this->credits());
        $sub = Subscription::where('user_id', $u->id)->where('plan_code', 'starter')->firstOrFail();
        $this->assertSame($paid, (int) $sub->price);
        $this->assertEqualsWithDelta(30, $sub->starts_at->diffInDays($sub->ends_at), 0.01);
        $this->assertSame(59000, $sub->snapshot['normal_price'], 'Snapshot harga normal disimpan (PRD 4.3 butir 7)');
        $this->assertSame((int) floor($paid * 0.1), (int) Commission::where('referred_id', $u->id)->value('amount'), 'Komisi 10% dari nominal dibayar setelah diskon (BR-032)');
        $this->assertTrue($u->notifications()->exists());
    }

    public function test_yearly_plan_gets_365_days(): void
    {
        $u = $this->user();
        $this->actingUser($u);
        $o = $this->buy('plan', 'starter', 'yearly');
        $this->assertSame(590000, $o['payment']['amount']);
        $this->payAtJualKirim($o['payment']['id']);

        $sub = $u->fresh()->activeSubscription();
        $this->assertSame('yearly', $sub->cycle);
        $this->assertEqualsWithDelta(365, $sub->starts_at->diffInDays($sub->ends_at), 0.01);
    }

    public function test_coupon_is_reserved_while_pending_used_when_paid_and_released_when_expired(): void
    {
        Coupon::create(['code' => 'HEMAT20', 'name' => 'Hemat 20%', 'type' => 'percent', 'value' => 20, 'duration' => 'once', 'max_uses_per_user' => 1, 'is_active' => true]);
        $u = $this->user();
        $this->actingUser($u);
        $paid = $this->buy('plan', 'starter', 'monthly', 'HEMAT20');
        $this->assertLessThan(59000, $paid['payment']['amount']);
        $this->assertSame('reserved', CouponRedemption::where('payment_id', $paid['payment']['id'])->value('status'));
        $this->payAtJualKirim($paid['payment']['id']);
        $this->assertSame('used', CouponRedemption::where('payment_id', $paid['payment']['id'])->value('status'));

        $u2 = $this->user();
        $this->actingUser($u2);
        $exp = $this->buy('plan', 'starter', 'monthly', 'HEMAT20');
        $this->travel(25)->hours();
        Billing::releaseExpiredReservations();
        $this->assertSame('released', CouponRedemption::where('payment_id', $exp['payment']['id'])->value('status'));
    }

    /* ======================= Add-on (PRD 9.3) ======================= */

    public function test_credit_addon_via_gateway_adds_credits_on_top_of_plan(): void
    {
        $u = $this->user();
        $this->actingUser($u);
        $before = $this->credits();
        $o = $this->buy('addon', 'credits-200', null);
        $this->assertSame(49000, $o['payment']['amount']);
        $this->assertSame($before, $this->credits(), 'Kredit belum masuk sebelum lunas');

        $this->payAtJualKirim($o['payment']['id']);
        $this->assertSame($before + 200, $this->credits());
    }

    /* ================ Upgrade & downgrade (PRD 55.5.3–55.5.4) ================ */

    public function test_upgrade_via_gateway_is_prorated_and_replaces_plan_credits(): void
    {
        $u = $this->user();
        $this->actingUser($u);
        $s = $this->buy('plan', 'starter');
        $this->payAtJualKirim($s['payment']['id']);
        $this->travel(10)->days();

        $q = $this->json_('POST', '/app/orders/quote', ['kind' => 'plan', 'code' => 'pro', 'cycle' => 'monthly'])->json('data');
        $this->assertTrue($q['is_upgrade']);
        $this->assertGreaterThan(30000, $q['prorata']); // ±20/30 × 59.000
        $o = $this->buy('plan', 'pro');
        $this->assertSame($q['final'], $o['payment']['amount']);
        $this->assertSame('starter', $this->planCode(), 'Paket lama tetap jalan sampai upgrade dibayar');

        $this->payAtJualKirim($o['payment']['id']);
        $this->assertSame('pro', $this->planCode());
        $this->assertSame(1000, $this->credits(), 'Kredit diganti penuh dengan kredit paket baru');
        $sub = $u->fresh()->activeSubscription();
        $this->assertEqualsWithDelta(30, now()->diffInDays($sub->ends_at), 0.01, 'Siklus dimulai ulang pada hari upgrade');
    }

    public function test_downgrade_is_scheduled_without_payment_and_applied_at_period_end(): void
    {
        $u = $this->user([], 'free', 500000);
        $this->actingUser($u);
        $o = $this->buy('plan', 'pro');
        $this->payAtJualKirim($o['payment']['id']);

        $r = $this->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => 'starter', 'cycle' => 'monthly', 'source' => 'gateway'])->assertOk()->json('data');
        $this->assertSame('scheduled', $r['status']);
        $this->assertSame(1, Payment::where('user_id', $u->id)->count(), 'Downgrade tidak membuat tagihan');

        $this->travel(31)->days();
        Billing::renewals();
        $this->assertSame('starter', $this->planCode());
        $this->assertSame(500000 - 59000, Wallet::balance($u->id, 'topup'), 'Periode berikutnya dibayar dari saldo top-up');
    }

    /* ================= Perpanjangan (PRD 55.5.1, 55.5.2) ================= */

    public function test_reminders_h7_h3_h1_are_sent_once_each(): void
    {
        $u = $this->user();
        $this->actingUser($u);
        $o = $this->buy('plan', 'starter');
        $this->payAtJualKirim($o['payment']['id']);
        $count = fn () => $u->notifications()->where('data->title', 'Paket segera berakhir')->count();

        $this->travel(23)->days();
        Billing::renewals();
        Billing::renewals();
        $this->assertSame(1, $count());
        $this->travel(4)->days();
        Billing::renewals();
        $this->assertSame(2, $count());
        $this->travel(2)->days();
        Billing::renewals();
        $this->assertSame(3, $count());
    }

    public function test_manual_renewal_via_gateway_extends_without_refilling_credits_or_double_charging(): void
    {
        $u = $this->user([], 'free', 500000);
        $u->forceFill(['auto_renew' => true])->save();
        $this->actingUser($u);
        $first = $this->buy('plan', 'starter');
        $this->payAtJualKirim($first['payment']['id']);
        $endsFirst = $u->fresh()->activeSubscription()->ends_at->copy();

        $this->travel(25)->days();
        Billing::renewals(); // pengingat H-7/H-3 sebelum diperpanjang
        Credits::consume($u->fresh(), 50, 'test', null, 'Pemakaian uji');
        $creditsBefore = $this->credits();
        $this->assertSame(250, $creditsBefore);

        // Perpanjang manual 5 hari sebelum habis
        $renew = $this->buy('plan', 'starter');
        $this->payAtJualKirim($renew['payment']['id']);

        $cur = $u->fresh()->activeSubscription();
        $this->assertTrue($cur->ends_at->equalTo($endsFirst), 'Periode berjalan tetap yang lama sampai habis');
        $this->assertSame($creditsBefore, $this->credits(), 'Kredit tidak diisi ulang sebelum periode baru mulai');
        $remindersAfterRenew = $u->notifications()->where('data->title', 'Paket segera berakhir')->count();
        $info = $this->json_('GET', '/app/subscription')->json('data.current');
        $this->assertTrue(Carbon::parse($info['ends_at'])->equalTo($endsFirst));
        $this->assertTrue(Carbon::parse($info['paid_until'])->equalTo($endsFirst->copy()->addDays(30)), 'UI menampilkan masa aktif s.d. akhir periode yang sudah dibayar');

        // Lewat akhir periode pertama: periode kedua aktif, TIDAK ada potongan saldo auto-renew
        $this->travel(6)->days();
        Billing::renewals();
        $this->assertSame(500000, Wallet::balance($u->id, 'topup'), 'Tidak dobel bayar: sudah diperpanjang manual');
        $this->assertSame($remindersAfterRenew, $u->notifications()->where('data->title', 'Paket segera berakhir')->count(), 'Tidak ada pengingat palsu setelah diperpanjang');
        $now = $u->fresh()->activeSubscription();
        $this->assertSame('starter', $now->plan_code);
        $this->assertTrue($now->starts_at->equalTo($endsFirst), 'Periode baru lanjut dari akhir periode lama');
        $this->assertEqualsWithDelta(30, $now->starts_at->diffInDays($now->ends_at), 0.01);
        $this->assertSame(300, $this->credits(), 'Kredit periode baru diberikan saat periode baru mulai');
    }

    public function test_renewing_twice_in_advance_stacks_periods_without_overlap(): void
    {
        $u = $this->user();
        $this->actingUser($u);
        foreach (range(1, 3) as $i) {
            $o = $this->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => 'starter', 'cycle' => 'monthly', 'source' => 'gateway'], ['Idempotency-Key' => "renew-{$i}"])->json('data');
            $this->payAtJualKirim($o['payment']['id']);
        }
        $subs = Subscription::where('user_id', $u->id)->where('plan_code', 'starter')->orderBy('starts_at')->get();
        $this->assertCount(3, $subs);
        $this->assertTrue($subs[1]->starts_at->equalTo($subs[0]->ends_at));
        $this->assertTrue($subs[2]->starts_at->equalTo($subs[1]->ends_at));
        $this->assertSame($subs[0]->id, $u->fresh()->activeSubscription()->id);
    }

    public function test_upgrade_after_advance_renewal_credits_prepaid_period_and_cancels_it(): void
    {
        $u = $this->user();
        $this->actingUser($u);
        foreach (['a', 'b'] as $k) {
            $o = $this->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => 'starter', 'cycle' => 'monthly', 'source' => 'gateway'], ['Idempotency-Key' => "s-{$k}"])->json('data');
            $this->payAtJualKirim($o['payment']['id']);
        }
        $this->travel(10)->days();

        $q = $this->json_('POST', '/app/orders/quote', ['kind' => 'plan', 'code' => 'pro', 'cycle' => 'monthly'])->json('data');
        $this->assertGreaterThan(59000 + 30000, $q['prorata'], 'Sisa periode berjalan + periode prabayar dihitung');
        $o = $this->buy('plan', 'pro');
        $this->payAtJualKirim($o['payment']['id']);

        $this->assertSame('pro', $this->planCode());
        $this->assertSame(0, Subscription::where('user_id', $u->id)->where('plan_code', 'starter')->where('status', 'active')->count(), 'Periode Starter prabayar dibatalkan');
    }

    public function test_downgrade_after_advance_renewal_applies_after_last_paid_period(): void
    {
        $u = $this->user([], 'free', 500000);
        $this->actingUser($u);
        foreach (['a', 'b'] as $k) {
            $o = $this->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => 'pro', 'cycle' => 'monthly', 'source' => 'gateway'], ['Idempotency-Key' => "p-{$k}"])->json('data');
            $this->payAtJualKirim($o['payment']['id']);
        }
        $r = $this->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => 'starter', 'cycle' => 'monthly', 'source' => 'gateway'])->json('data');
        $this->assertSame('scheduled', $r['status']);

        $this->travel(31)->days();
        Billing::renewals();
        $this->assertSame('pro', $this->planCode(), 'Periode Pro kedua (prabayar) tetap dipakai');
        $this->travel(30)->days();
        Billing::renewals();
        $this->assertSame('starter', $this->planCode(), 'Downgrade berlaku setelah periode terakhir');
    }

    public function test_auto_renew_from_balance_and_past_due_then_pay_via_gateway_during_grace(): void
    {
        $u = $this->user([], 'free', 60000);
        $u->forceFill(['auto_renew' => true])->save();
        $this->actingUser($u);
        $o = $this->buy('plan', 'starter');
        $this->payAtJualKirim($o['payment']['id']);

        // Periode 1 habis → saldo 60.000 cukup → diperpanjang otomatis
        $this->travel(30)->days();
        $this->travel(1)->minutes();
        Billing::renewals();
        $this->assertSame(1000, Wallet::balance($u->id, 'topup'));
        $this->assertSame('starter', $this->planCode());

        // Periode 2 habis → saldo kurang → past_due + notifikasi, fitur masih dalam grace
        $this->travel(30)->days();
        Billing::renewals();
        $this->assertSame('past_due', Subscription::where('user_id', $u->id)->latest('starts_at')->value('status'));
        $this->assertTrue($u->notifications()->where('data->title', 'Perpanjangan gagal')->exists());

        // Bayar lewat JualKirim di masa grace → aktif lagi
        $this->travel(1)->days();
        $p = $this->buy('plan', 'starter');
        $this->payAtJualKirim($p['payment']['id']);
        $this->assertSame('starter', $this->planCode());
        $this->assertSame('active', $u->fresh()->activeSubscription()->status);
    }

    public function test_without_renewal_user_falls_back_to_free_after_grace_period(): void
    {
        $u = $this->user();
        $this->actingUser($u);
        $o = $this->buy('plan', 'starter');
        $this->payAtJualKirim($o['payment']['id']);

        $this->travel(32)->days(); // berakhir + 2 hari (masih grace)
        Billing::renewals();
        $this->assertNotSame('free', Subscription::where('user_id', $u->id)->latest('id')->value('plan_code'));

        $this->travel(2)->days(); // lewat grace 3 hari
        Billing::renewals();
        $this->assertSame('free', $this->planCode());
    }
}
