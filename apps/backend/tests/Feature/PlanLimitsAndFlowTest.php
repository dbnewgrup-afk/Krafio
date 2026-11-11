<?php

namespace Tests\Feature;

use App\Facades\JualKirim;
use App\Models\Addon;
use App\Models\Approval;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\CustomPackage;
use App\Models\Payment;
use App\Models\SocialAccount;
use App\Models\Subscription;
use App\Models\Upload;
use App\Services\Billing;
use App\Services\Integrations;
use App\Services\JualKirim\JualKirimClient;
use App\Services\Payment\JualKirimInvoices;
use App\Services\Wallet;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Batas paket per akun (PRD 55.2 / 55.2.1), Enterprise via custom package (55.2.3),
 * dan perapihan flow pembayaran (tagihan ganda, satu tagihan paket terbuka, pembatalan,
 * gateway gagal, kompensasi aktivasi gagal, scheduler perpanjangan).
 */
class PlanLimitsAndFlowTest extends KrafioTestCase
{
    /** @var array<string, array{amount:int,status:string}> */
    private array $invoices = [];

    private function useJualKirim(): void
    {
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

                return Http::response(['data' => ['id' => $id, 'invoice_number' => 'BILL/'.$id, 'status' => 'draft', 'grand_total' => $r['items'][0]['price'], 'payment_link' => "https://pay.test/{$id}"]], 201);
            }
            if (preg_match('#^/api/v1/invoices/(inv-\d+)$#', $path, $m) && isset($this->invoices[$m[1]])) {
                if ($r->method() === 'DELETE') {
                    $this->invoices[$m[1]]['status'] = 'deleted';

                    return Http::response(['message' => 'Berhasil dihapus', 'data' => null]);
                }

                return Http::response(['data' => ['invoice' => ['id' => $m[1], 'status' => $this->invoices[$m[1]]['status'], 'grand_total' => $this->invoices[$m[1]]['amount']]]]);
            }

            return Http::response(['message' => 'tidak ada'], 404);
        });
    }

    private function pay(int $paymentId): void
    {
        $this->invoices[Payment::findOrFail($paymentId)->gateway_payload['gateway_ref']]['status'] = 'paid';
        JualKirimInvoices::syncPending();
    }

    private function gatewayOrder(string $kind, string $code, ?string $cycle = 'monthly', array $headers = [])
    {
        return $this->json_('POST', '/app/orders', array_filter(['kind' => $kind, 'code' => $code, 'cycle' => $cycle, 'source' => 'gateway']), $headers);
    }

    /* ===================== Batas paket per akun ===================== */

    public function test_project_limit_follows_plan_including_duplicate_and_restore(): void
    {
        $u = $this->user([], 'free', 500000); // Free: 1 proyek
        $this->actingUser($u);
        $p1 = $this->json_('POST', '/app/projects', ['name' => 'Satu', 'type' => 'photo'])->assertCreated()->json('data');
        $this->json_('POST', '/app/projects', ['name' => 'Dua', 'type' => 'photo'])->assertStatus(403)->assertJsonPath('code', 'quota_exceeded')->assertJsonPath('max', 1);
        $this->json_('POST', "/app/projects/{$p1['id']}/duplicate")->assertStatus(403);

        // Hapus lalu buat baru; restore yang lama ditolak karena slot penuh
        $this->json_('DELETE', "/app/projects/{$p1['id']}")->assertNoContent();
        $this->json_('POST', '/app/projects', ['name' => 'Baru', 'type' => 'photo'])->assertCreated();
        $this->json_('POST', "/app/projects/{$p1['id']}/restore")->assertStatus(403);

        // Upgrade ke Starter (3 proyek) → bisa menambah
        $this->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => 'starter', 'cycle' => 'monthly', 'source' => 'balance'])->assertOk();
        $this->json_('POST', "/app/projects/{$p1['id']}/restore")->assertOk();
        $this->json_('POST', '/app/projects', ['name' => 'Tiga', 'type' => 'video'])->assertCreated();
        $this->json_('POST', '/app/projects', ['name' => 'Empat', 'type' => 'video'])->assertStatus(403);
    }

    public function test_website_limit_counts_addon_and_free_plan_cannot_publish(): void
    {
        $u = $this->user([], 'free', 500000);
        $this->actingUser($u);
        $site = fn ($s) => ['name' => 'Toko '.$s, 'business' => 'Usaha kopi rumahan di Bandung', 'subdomain' => 'toko-'.$s.'-'.strtolower(Str::random(4))];

        $w = $this->json_('POST', '/app/websites', $site('a'))->assertCreated()->json('data');
        $this->json_('POST', "/app/websites/{$w['id']}/publish", [])->assertStatus(403)->assertJsonPath('code', 'plan_limit'); // Free = pratinjau

        $this->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => 'starter', 'cycle' => 'monthly', 'source' => 'balance'])->assertOk();
        $this->json_('POST', '/app/websites', $site('b'))->assertStatus(403)->assertJsonPath('code', 'quota_exceeded'); // Starter: 1 website
        $this->json_('POST', '/app/orders', ['kind' => 'addon', 'code' => 'website-extra', 'source' => 'balance'])->assertOk();
        $this->json_('POST', '/app/websites', $site('c'))->assertCreated(); // add-on +1 kini terhitung
    }

    public function test_storage_limit_blocks_uploads_beyond_plan(): void
    {
        $u = $this->user(); // Free: 500 MB
        $this->actingUser($u);
        Upload::create(['user_id' => $u->id, 'disk' => 'local', 'path' => 'x', 'original_name' => 'lama.mp4', 'mime' => 'video/mp4', 'size' => 499 * 1048576]);

        $this->json_('POST', '/app/uploads', ['file' => UploadedFile::fake()->create('besar.mp4', 3 * 1024, 'video/mp4')])->assertStatus(403)->assertJsonPath('limit', 'storage_mb');
        $this->json_('POST', '/app/uploads', ['file' => UploadedFile::fake()->image('kecil.png', 10, 10)])->assertCreated();
    }

    public function test_social_account_limit_and_free_plan_locked(): void
    {
        $u = $this->user([], 'free', 500000);
        $this->actingUser($u);
        $this->json_('POST', '/app/social/facebook/connect')->assertStatus(403)->assertJsonPath('code', 'feature_locked');

        $this->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => 'starter', 'cycle' => 'monthly', 'source' => 'balance'])->assertOk(); // 2 akun
        foreach (['facebook', 'instagram'] as $pf) {
            SocialAccount::create(['user_id' => $u->id, 'platform' => $pf, 'account_name' => $pf, 'status' => 'connected']);
        }
        $this->json_('POST', '/app/social/tiktok/connect')->assertStatus(403)->assertJsonPath('code', 'quota_exceeded');
        // Menyambung ulang platform yang sudah ada tidak dihitung sebagai akun baru (lanjut ke cek integrasi admin)
        $this->json_('POST', '/app/social/facebook/connect')->assertStatus(422)->assertJsonPath('code', 'integration_not_available');
    }

    /* ===================== Enterprise / custom package ===================== */

    public function test_enterprise_custom_package_has_admin_chosen_features_and_limits(): void
    {
        $this->useJualKirim();
        $u = $this->user();
        $this->admin();
        $base = ['code' => 'ENT-ACME', 'name' => 'Enterprise ACME', 'price' => 2500000, 'duration_days' => 30, 'quotas' => ['credits' => 8000], 'offered_to' => $u->id, 'status' => 'published'];
        $this->json_('POST', '/admin/r/custom-packages', $base + ['features' => ['photo.generate', 'fitur.palsu']])->assertStatus(422);
        $this->json_('POST', '/admin/r/custom-packages', $base + ['features' => ['photo.generate', 'website.builder', 'chatbot.basic'], 'limits' => ['projects' => 100, 'websites' => 20, 'storage_mb' => 204800]])->assertCreated();

        // Enterprise tidak bisa dibeli dari katalog paket
        $this->actingUser($u);
        $this->gatewayOrder('plan', 'enterprise')->assertStatus(422)->assertJsonPath('code', 'custom_plan');

        $o = $this->gatewayOrder('custom', 'ENT-ACME', null)->assertOk()->json('data');
        $this->assertSame(2500000, $o['payment']['amount']);
        $this->pay($o['payment']['id']);

        $me = $this->json_('GET', '/me')->json('data');
        $this->assertSame(100, $me['limits']['projects']);
        $this->assertSame(20, $me['limits']['websites']);
        $this->assertTrue($me['entitlements']['website.builder']['enabled']);
        $this->assertFalse($me['entitlements']['video.generate']['enabled'], 'Fitur yang tidak dipilih admin tetap terkunci');
        $this->assertSame(8000, $me['entitlements']['credits']['remaining']);
    }

    public function test_custom_package_package_for_other_user_is_not_purchasable(): void
    {
        $owner = $this->user();
        CustomPackage::create(['code' => 'ENT-X', 'name' => 'Ent X', 'price' => 1000000, 'duration_days' => 30, 'features' => ['photo.generate'], 'quotas' => [], 'offered_to' => $owner->id, 'status' => 'published']);
        $this->actingUser($this->user());
        $this->json_('POST', '/app/orders', ['kind' => 'custom', 'code' => 'ENT-X', 'source' => 'gateway'])->assertNotFound();
    }

    /* ===================== Flow pembayaran ===================== */

    public function test_repeat_checkout_reuses_open_invoice_instead_of_creating_duplicates(): void
    {
        $this->useJualKirim();
        $this->actingUser($this->user());
        $a = $this->gatewayOrder('plan', 'starter', 'monthly', ['Idempotency-Key' => 'k1'])->json('data.payment');
        $b = $this->gatewayOrder('plan', 'starter', 'monthly', ['Idempotency-Key' => 'k2'])->assertJsonPath('data.reused', true)->json('data.payment');
        $this->assertSame($a['id'], $b['id']);
        $this->assertCount(1, $this->invoices, 'Hanya satu invoice JualKirim');

        $t1 = $this->json_('POST', '/app/wallet/topups', ['amount' => 100000, 'method' => 'qris'], ['Idempotency-Key' => 't1'])->json('data');
        $t2 = $this->json_('POST', '/app/wallet/topups', ['amount' => 100000, 'method' => 'qris'], ['Idempotency-Key' => 't2'])->json('data');
        $this->assertSame($t1['id'], $t2['id']);
        $this->assertCount(2, $this->invoices);
    }

    public function test_only_one_open_plan_invoice_cancel_deletes_gateway_invoice_then_new_plan_allowed(): void
    {
        $this->useJualKirim();
        Coupon::create(['code' => 'HEMAT20', 'name' => 'Hemat', 'type' => 'percent', 'value' => 20, 'duration' => 'once', 'max_uses_per_user' => 1, 'is_active' => true]);
        $u = $this->user();
        $this->actingUser($u);
        $starter = $this->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => 'starter', 'cycle' => 'monthly', 'source' => 'gateway', 'coupon' => 'HEMAT20'])->json('data.payment');

        $this->gatewayOrder('plan', 'pro')->assertStatus(409)->assertJsonPath('code', 'pending_payment_exists')->assertJsonPath('payment.id', $starter['id']);
        // Add-on tetap boleh dibeli terpisah
        $this->gatewayOrder('addon', 'credits-200', null)->assertOk();
        $this->assertCount(2, $this->json_('GET', '/app/payments')->json('data'));

        $this->json_('POST', "/app/payments/{$starter['id']}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/api/v1/invoices/inv-1'));
        $this->assertSame('released', CouponRedemption::where('payment_id', $starter['id'])->value('status'));
        $this->json_('POST', "/app/payments/{$starter['id']}/cancel")->assertStatus(422)->assertJsonPath('code', 'not_cancellable');

        $this->gatewayOrder('plan', 'pro')->assertOk();
    }

    public function test_cancel_after_customer_already_paid_activates_instead(): void
    {
        $this->useJualKirim();
        $u = $this->user();
        $this->actingUser($u);
        $p = $this->gatewayOrder('plan', 'starter')->json('data.payment');
        $this->invoices['inv-1']['status'] = 'paid'; // dibayar, tapi sinkron belum jalan

        $this->json_('POST', "/app/payments/{$p['id']}/cancel")->assertOk()->assertJsonPath('data.status', 'paid');
        $this->assertSame('starter', $this->json_('GET', '/me')->json('data.plan.code'));
        Http::assertNotSent(fn (Request $r) => $r->method() === 'DELETE');
    }

    public function test_gateway_failure_leaves_no_orphan_pending_payment_or_reserved_coupon(): void
    {
        $this->useJualKirim();
        Coupon::create(['code' => 'HEMAT20', 'name' => 'Hemat', 'type' => 'percent', 'value' => 20, 'duration' => 'once', 'max_uses_per_user' => 1, 'is_active' => true]);
        $u = $this->user();
        $u->forceFill(['phone' => null])->save();
        $this->actingUser($u);

        $this->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => 'starter', 'cycle' => 'monthly', 'source' => 'gateway', 'coupon' => 'HEMAT20'])->assertStatus(422)->assertJsonPath('code', 'phone_required');
        $this->assertSame(0, Payment::where('user_id', $u->id)->where('status', 'pending')->count());
        $this->assertSame('released', CouponRedemption::where('user_id', $u->id)->value('status'));
        $this->assertSame(0, (int) Coupon::where('code', 'HEMAT20')->value('reserved'));
    }

    public function test_activation_failure_after_payment_is_compensated_to_topup_balance(): void
    {
        $this->useJualKirim();
        $u = $this->user();
        $this->actingUser($u);
        $o = $this->gatewayOrder('addon', 'credits-200', null)->json('data.payment');
        Addon::where('code', 'credits-200')->update(['is_active' => false]); // admin menonaktifkan sebelum pelanggan membayar

        $this->pay($o['id']);

        $this->assertSame('paid', Payment::find($o['id'])->status);
        $this->assertSame(49000, Wallet::balance($u->id, 'topup'), 'Dana masuk saldo top-up, tidak hilang');
        $this->assertTrue($u->notifications()->where('data->title', 'Pembayaran dialihkan ke saldo')->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.activation_failed']);
    }

    public function test_renewal_scheduler_handles_custom_packages_and_keeps_going_after_a_failure(): void
    {
        $ent = $this->user([], 'free', 3000000);
        $ent->forceFill(['auto_renew' => true])->save();
        CustomPackage::create(['code' => 'ENT-R', 'name' => 'Ent R', 'price' => 1000000, 'duration_days' => 30, 'features' => ['photo.generate'], 'quotas' => ['credits' => 5000], 'limits' => ['projects' => 50], 'offered_to' => $ent->id, 'status' => 'published']);
        $this->actingUser($ent);
        $this->json_('POST', '/app/orders', ['kind' => 'custom', 'code' => 'ENT-R', 'source' => 'balance'])->assertOk();

        $gone = $this->user([], 'free', 3000000);
        $gone->forceFill(['auto_renew' => true])->save();
        CustomPackage::create(['code' => 'ENT-G', 'name' => 'Ent G', 'price' => 900000, 'duration_days' => 30, 'features' => ['photo.generate'], 'quotas' => [], 'offered_to' => $gone->id, 'status' => 'published']);
        $this->actingUser($gone);
        $this->json_('POST', '/app/orders', ['kind' => 'custom', 'code' => 'ENT-G', 'source' => 'balance'])->assertOk();
        CustomPackage::where('code', 'ENT-G')->update(['status' => 'archived']); // tidak lagi ditawarkan

        $this->travel(30)->days();
        $this->travel(1)->minutes();
        Billing::renewals(); // tidak boleh berhenti karena ENT-G

        $this->assertSame('ENT-R', $ent->fresh()->activeSubscription()->plan_code);
        $this->assertSame(3000000 - 2000000, Wallet::balance($ent->id, 'topup'), 'Custom package diperpanjang dari saldo');
        $this->assertSame('past_due', Subscription::where('user_id', $gone->id)->where('plan_code', 'ENT-G')->latest('id')->value('status'));
        $this->assertTrue($gone->notifications()->where('data->title', 'Perpanjangan gagal')->exists());
    }

    public function test_admin_accounts_cannot_buy_plans_or_topup(): void
    {
        $this->admin();
        $this->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => 'starter', 'cycle' => 'monthly', 'source' => 'balance'])->assertStatus(403);
        $this->json_('POST', '/app/wallet/topups', ['amount' => 100000, 'method' => 'qris'])->assertStatus(403);
        $this->assertSame(0, Approval::count() + Payment::count());
    }
}
