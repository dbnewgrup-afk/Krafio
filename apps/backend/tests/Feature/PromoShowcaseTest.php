<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Plan;
use App\Services\Wallet;

/** Promo: admin membuat & menayangkan, publik melihat, user memakai kode di checkout. */
class PromoShowcaseTest extends KrafioTestCase
{
    private function promo(array $attrs): Coupon
    {
        return Coupon::create($attrs + ['type' => 'percent', 'value' => 10, 'duration' => 'once', 'is_active' => true, 'is_public' => true, 'max_uses_per_user' => 1]);
    }

    public function test_public_list_shows_only_running_public_promos_without_internal_numbers(): void
    {
        Coupon::query()->update(['is_public' => false]); // abaikan contoh bawaan migration
        $this->promo(['code' => 'TAMPIL', 'headline' => 'Hemat 10%', 'sort' => 1]);
        $this->promo(['code' => 'RAHASIA', 'is_public' => false]);
        $this->promo(['code' => 'MATI', 'is_active' => false]);
        $this->promo(['code' => 'LEWAT', 'ends_at' => now()->subDay()]);
        $this->promo(['code' => 'NANTI', 'starts_at' => now()->addDay()]);
        $this->promo(['code' => 'HABIS', 'max_uses' => 5, 'used' => 5]);
        $this->promo(['code' => 'OTOMATIS', 'auto_apply' => true, 'sort' => 2]);
        $this->promo(['code' => 'MENIPIS', 'max_uses' => 100, 'used' => 90, 'sort' => 3]);

        $r = $this->json_('GET', '/public/promos')->assertOk();
        $rows = collect($r->json('data'));
        $this->assertSame(['TAMPIL', null, 'MENIPIS'], $rows->pluck('code')->all(), 'Kode promo otomatis tidak dibocorkan');
        $this->assertSame('Hemat 10%', $rows[0]['headline']);
        $this->assertTrue($rows[1]['auto']);
        $this->assertSame(10, $rows[2]['remaining'], 'Sisa kuota hanya tampil saat menipis');
        $this->assertNull($rows[0]['remaining']);
        $this->assertArrayNotHasKey('used', $rows[0]);
        $this->assertArrayNotHasKey('reserved', $rows[0]);
    }

    public function test_admin_creates_public_promo_with_uppercase_code_and_safe_image(): void
    {
        $this->admin();
        $c = $this->json_('POST', '/admin/r/coupons', ['code' => 'lebaran25', 'type' => 'percent', 'value' => 25, 'duration' => 'once', 'is_active' => true, 'is_public' => true, 'headline' => 'Diskon Lebaran 25%', 'image' => '/assets/img/pages/pricing.jpg'])->assertCreated();
        $this->assertSame('LEBARAN25', $c->json('data.code'));
        $this->json_('POST', '/admin/r/coupons', ['code' => 'XSS1', 'type' => 'fixed', 'value' => 1000, 'duration' => 'once', 'image' => 'javascript:alert(1)'])->assertStatus(422)->assertJsonValidationErrors('image');
        $this->json_('POST', '/admin/r/coupons', ['code' => 'TGL', 'type' => 'fixed', 'value' => 1000, 'duration' => 'once', 'starts_at' => '2026-12-10', 'ends_at' => '2026-12-01'])->assertStatus(422)->assertJsonValidationErrors('ends_at');
        $this->assertContains('LEBARAN25', collect($this->json_('GET', '/public/promos')->json('data'))->pluck('code')->all());

        // Matikan tayang → hilang dari etalase tanpa menghapus kupon
        $this->admin();
        $this->json_('PUT', '/admin/r/coupons/'.$c->json('data.id'), ['is_public' => false])->assertOk();
        $this->assertNotContains('LEBARAN25', collect($this->json_('GET', '/public/promos')->json('data'))->pluck('code')->all());
    }

    public function test_checkout_lists_only_promos_valid_for_the_item_sorted_by_savings(): void
    {
        Coupon::query()->update(['is_public' => false]);
        $this->promo(['code' => 'PRO10', 'plan_codes' => ['pro'], 'cycles' => ['monthly']]);
        $this->promo(['code' => 'POTONG50', 'type' => 'fixed', 'value' => 50000]);
        $this->promo(['code' => 'TAHUNAN', 'cycles' => ['yearly']]);
        $this->promo(['code' => 'BISNIS', 'plan_codes' => ['business']]);
        $this->promo(['code' => 'MINBESAR', 'min_purchase' => 10000000]);

        $u = $this->user();
        $rows = collect($this->actingUser($u)->json_('POST', '/app/orders/promos', ['kind' => 'plan', 'code' => 'pro', 'cycle' => 'monthly'])->assertOk()->json('data'));
        $this->assertSame(['POTONG50', 'PRO10'], $rows->pluck('code')->all());
        $this->assertSame(50000, $rows[0]['discount']);
        $this->assertSame((int) floor(Plan::where('code', 'pro')->value('price_monthly') / 10), $rows[1]['discount'], '10% dari harga Pro bulanan');

        $this->json_('POST', '/app/orders/promos', ['kind' => 'custom', 'code' => 'x'])->assertStatus(404);
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->json_('POST', '/app/orders/promos', ['kind' => 'plan', 'code' => 'pro', 'cycle' => 'monthly'])->assertUnauthorized();
    }

    public function test_user_pays_with_public_code_typed_in_any_case_and_quota_is_enforced(): void
    {
        $this->promo(['code' => 'HEMATQA', 'type' => 'fixed', 'value' => 20000, 'plan_codes' => ['starter']]);
        $u = $this->user([], 'free', 500000);
        $this->actingUser($u);
        $q = $this->json_('POST', '/app/orders/quote', ['kind' => 'plan', 'code' => 'starter', 'cycle' => 'monthly', 'coupon' => 'hematqa'])->assertOk();
        $q->assertJsonPath('data.discount', 20000)->assertJsonPath('data.coupon.code', 'HEMATQA')->assertJsonPath('data.coupon_error', null);
        $final = $q->json('data.final');

        $this->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => 'starter', 'cycle' => 'monthly', 'source' => 'balance', 'coupon' => 'HEMATQA'])->assertOk();
        $this->assertSame(500000 - $final, Wallet::balance($u->id, 'topup'));
        $this->assertSame(1, (int) Coupon::where('code', 'HEMATQA')->value('used'));
        $this->assertSame('used', CouponRedemption::where('user_id', $u->id)->value('status'));

        // Batas 1x per user: promo hilang dari daftar checkout & kode ditolak
        $this->assertSame([], collect($this->json_('POST', '/app/orders/promos', ['kind' => 'plan', 'code' => 'starter', 'cycle' => 'monthly'])->json('data'))->where('code', 'HEMATQA')->values()->all());
        $this->json_('POST', '/app/orders/quote', ['kind' => 'plan', 'code' => 'starter', 'cycle' => 'monthly', 'coupon' => 'HEMATQA'])
            ->assertJsonPath('data.discount', 0)->assertJsonPath('data.coupon_error', 'Kode promo tidak berlaku untuk pembelian ini.');
    }

    public function test_promo_does_not_apply_to_topup_and_seeded_examples_exist(): void
    {
        $codes = collect($this->json_('GET', '/public/promos')->json('data'))->pluck('code')->all();
        $this->assertContains('MULAI20', $codes);
        $this->assertContains('TAHUNAN50K', $codes);
        $this->assertContains('BONUS100', $codes);

        $u = $this->user();
        $this->actingUser($u)->json_('POST', '/app/wallet/topups', ['amount' => 100000, 'method' => 'va', 'coupon' => 'MULAI20'])->assertSuccessful();
        $this->assertSame(0, CouponRedemption::where('user_id', $u->id)->count(), 'Kupon tidak pernah dipakai untuk top-up');
    }
}
