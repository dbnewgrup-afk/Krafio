<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AuthController;
use App\Models\AuditLog;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing;
use App\Services\Entitlements;
use App\Services\Wallet;
use App\Support\ApiException;

/** Admin mengatur paket/fitur akun user; akun admin (pengelola) tidak pernah punya paket/fitur. */
class AdminUserFeaturesTest extends KrafioTestCase
{
    public function test_admin_can_create_user_and_admin_accounts_but_admin_gets_no_plan(): void
    {
        $this->admin();
        $u = $this->json_('POST', '/admin/users', ['type' => 'user', 'name' => 'Pelanggan Baru', 'email' => 'pelanggan@uji.test'])->assertCreated();
        $a = $this->json_('POST', '/admin/users', ['type' => 'admin', 'name' => 'Admin Baru', 'email' => 'adminbaru@uji.test'])->assertCreated();

        $this->assertSame('free', User::find($u->json('data.id'))->activeSubscription()?->plan_code);
        $newAdmin = User::find($a->json('data.id'));
        $this->assertNull($newAdmin->activeSubscription(), 'Akun admin tidak mendapat paket');
        AuthController::grantFreePlan($newAdmin, true);
        $this->assertSame(0, Subscription::where('user_id', $newAdmin->id)->count(), 'grantFreePlan menolak akun admin');
    }

    public function test_admin_target_is_rejected(): void
    {
        $admin = $this->admin();
        $this->json_('GET', "/admin/users/{$admin->id}/features")->assertStatus(422)->assertJsonPath('code', 'admin_no_features');
        $this->json_('POST', "/admin/users/{$admin->id}/features", ['mode' => 'plan', 'plan_code' => 'business', 'reason' => 'coba kasih paket'])
            ->assertStatus(422)->assertJsonPath('code', 'admin_no_features');
        $this->assertSame(0, Subscription::where('user_id', $admin->id)->count());
    }

    public function test_grant_catalog_plan_for_days_replaces_current_subscription(): void
    {
        $u = $this->user();
        $this->admin();
        $r = $this->json_('POST', "/admin/users/{$u->id}/features", ['mode' => 'plan', 'plan_code' => 'pro', 'days' => 30, 'reason' => 'Kompensasi gangguan'])->assertOk();
        $r->assertJsonPath('data.current.plan_code', 'pro')->assertJsonPath('data.current.granted_by_admin', true)->assertJsonPath('data.current.price', 0);

        $sub = $u->fresh()->activeSubscription();
        $this->assertSame('admin', $sub->source);
        $this->assertTrue($sub->ends_at->between(now()->addDays(29), now()->addDays(31)));
        $this->assertSame('cancelled', Subscription::where('user_id', $u->id)->where('plan_code', 'free')->value('status'));
        $this->assertSame(15, Entitlements::limit($u->fresh(), 'projects'));
        $this->assertTrue(Entitlements::forUser($u->fresh())['seo.advanced']['enabled']);
        $this->assertSame(1, AuditLog::where('action', 'admin.users.features.set')->count());

        // Sisi user melihat paket pemberian admin
        $this->actingUser($u->fresh())->json_('GET', '/app/subscription')->assertOk()->assertJsonPath('data.current.granted_by_admin', true);
    }

    public function test_custom_features_are_enforced(): void
    {
        $u = $this->user();
        $this->admin();
        $this->json_('POST', "/admin/users/{$u->id}/features", [
            'mode' => 'custom', 'name' => 'Paket Khusus Toko A', 'base_plan' => 'starter',
            'features' => ['website.builder', 'chatbot.basic'],
            'quotas' => ['chatbot.basic' => 7, 'credits' => 0, 'ngawur' => 9],
            'limits' => ['websites' => 4, 'projects' => 0],
            'reason' => 'Permintaan khusus klien',
        ])->assertOk()->assertJsonPath('data.current.plan_name', 'Paket Khusus Toko A');

        $fresh = $u->fresh();
        $g = Entitlements::grants($fresh);
        $this->assertEqualsCanonicalizing(['website.builder', 'chatbot.basic'], $g['features']);
        $this->assertEquals(['chatbot.basic' => 7, 'credits' => 0], $g['quotas'], 'Kuota tak dikenal dibuang');
        $this->assertSame(4, $g['limits']['websites']);
        $this->assertSame('720p', $g['limits']['video_resolution'], 'Limit lain diwarisi dari paket dasar');
        $this->assertNull($fresh->activeSubscription()->ends_at, 'Tanpa durasi = tanpa batas waktu');

        $this->actingUser($fresh);
        $this->json_('POST', '/app/projects', ['name' => 'X', 'type' => 'photo'])->assertStatus(403);
        try {
            Entitlements::ensure($fresh, 'content.brainstorm');
            $this->fail('Fitur di luar racikan harus terkunci');
        } catch (ApiException $e) {
            $this->assertSame('feature_locked', $e->errorCode);
        }
    }

    public function test_custom_validation_rejects_unknown_feature_and_bad_limits(): void
    {
        $u = $this->user();
        $this->admin();
        $this->json_('POST', "/admin/users/{$u->id}/features", ['mode' => 'custom', 'name' => 'X1', 'features' => ['hack.all'], 'reason' => 'alasan uji'])->assertStatus(422);
        $this->json_('POST', "/admin/users/{$u->id}/features", ['mode' => 'custom', 'name' => 'X1', 'features' => [], 'limits' => ['projects' => -1], 'reason' => 'alasan uji'])->assertStatus(422);
        $this->json_('POST', "/admin/users/{$u->id}/features", ['mode' => 'plan', 'plan_code' => 'enterprise', 'reason' => 'alasan uji'])->assertStatus(422);
        $this->json_('POST', "/admin/users/{$u->id}/features", ['mode' => 'plan', 'plan_code' => 'pro'])->assertStatus(422);
        $this->assertSame('free', $u->fresh()->activeSubscription()->plan_code);
    }

    public function test_grant_cancels_prepaid_periods_and_revoke_returns_to_free(): void
    {
        $u = $this->user([], 'free', 500000);
        $this->actingUser($u)->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => 'starter', 'cycle' => 'monthly', 'source' => 'balance'])->assertOk();
        $this->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => 'starter', 'cycle' => 'monthly', 'source' => 'balance'])->assertOk();
        $this->assertSame(1, Billing::upcomingPeriods($u->fresh())->count());

        $this->admin();
        $this->json_('POST', "/admin/users/{$u->id}/features", ['mode' => 'plan', 'plan_code' => 'business', 'reason' => 'Uji coba enterprise'])->assertOk()
            ->assertJsonPath('data.upcoming', 0);
        $this->assertSame(0, Subscription::where('user_id', $u->id)->where('plan_code', 'starter')->where('status', 'active')->count());

        $this->json_('DELETE', "/admin/users/{$u->id}/features", ['reason' => 'Masa uji selesai'])->assertOk()->assertJsonPath('data.current.plan_code', 'free');
        $this->json_('DELETE', "/admin/users/{$u->id}/features", ['reason' => 'Masa uji selesai'])->assertStatus(422)->assertJsonPath('code', 'not_admin_grant');
    }

    public function test_expired_admin_grant_falls_back_to_free_and_is_not_renewed(): void
    {
        $u = $this->user([], 'free', 500000);
        $this->admin();
        $this->json_('POST', "/admin/users/{$u->id}/features", ['mode' => 'plan', 'plan_code' => 'pro', 'days' => 1, 'reason' => 'Trial khusus'])->assertOk();
        $this->travel(5)->days();
        Billing::renewals();
        $this->assertSame('free', $u->fresh()->activeSubscription()?->plan_code);
        $this->assertSame(500000, Wallet::balance($u->id, 'topup'), 'Paket gratis dari admin tidak memotong saldo');
    }

    public function test_non_admin_cannot_use_endpoint(): void
    {
        $u = $this->user();
        $this->actingUser($u)->json_('POST', "/admin/users/{$u->id}/features", ['mode' => 'plan', 'plan_code' => 'business', 'reason' => 'naikin sendiri'])->assertForbidden();
    }
}
