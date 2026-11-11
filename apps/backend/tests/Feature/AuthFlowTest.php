<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\MailLog;
use App\Models\User;
use App\Services\Settings;

/** Alur A — Registrasi, OTP, login, status akun, MFA admin (PRD 44, Design Spec §9). */
class AuthFlowTest extends KrafioTestCase
{
    public function test_a1_register_verify_otp_activates_account_with_free_plan_and_50_credits(): void
    {
        $r = $this->json_('POST', '/auth/register', ['name' => 'Budi Kreator', 'email' => 'budi@uji.test', 'phone' => '+6281234567890', 'password' => 'KopiSusu2026x', 'password_confirmation' => 'KopiSusu2026x', 'agree_terms' => true]);
        $r->assertCreated()->assertJsonPath('data.status', 'pending_verification');
        $u = User::where('email', 'budi@uji.test')->first();
        $this->assertDatabaseHas('user_consents', ['user_id' => $u->id, 'document' => 'terms']);
        $this->assertTrue(MailLog::where('to', 'budi@uji.test')->where('template', 'otp')->where('status', 'sent')->exists(), 'Email OTP harus terkirim & tercatat');

        $this->knownOtp($u, '654321');
        $this->json_('POST', '/auth/otp/verify', ['code' => '000000'])->assertStatus(422)->assertJsonPath('code', 'otp_invalid');
        $v = $this->json_('POST', '/auth/otp/verify', ['code' => '654321']);
        $v->assertOk()->assertJsonPath('data.status', 'active')->assertJsonPath('data.plan.code', 'free')->assertJsonPath('data.entitlements.credits.remaining', 50);
    }

    public function test_a2_register_rejects_weak_or_personal_password_and_duplicate_email(): void
    {
        $this->user(['email' => 'ada@uji.test']);
        $this->json_('POST', '/auth/register', ['name' => 'X', 'email' => 'ada@uji.test', 'password' => 'abc', 'password_confirmation' => 'abc', 'agree_terms' => false])
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'email', 'password', 'agree_terms']);
        $this->json_('POST', '/auth/register', ['name' => 'Sinta Rahma', 'email' => 'sinta@uji.test', 'password' => 'sinta12345', 'password_confirmation' => 'sinta12345', 'agree_terms' => true])
            ->assertStatus(422)->assertJsonPath('code', 'validation_failed');
    }

    public function test_a3_manual_registration_mode_requires_admin_approval(): void
    {
        Settings::set('registration.mode', 'manual');
        $this->json_('POST', '/auth/register', ['name' => 'Rina Manual', 'email' => 'rina@uji.test', 'password' => 'KopiSusu2026x', 'password_confirmation' => 'KopiSusu2026x', 'agree_terms' => true])->assertCreated();
        $u = User::where('email', 'rina@uji.test')->first();
        $this->knownOtp($u);
        $this->json_('POST', '/auth/otp/verify', ['code' => '123456'])->assertOk()->assertJsonPath('data.status', 'pending_approval');
        $ap = Approval::where('type', 'account')->where('user_id', $u->id)->firstOrFail();

        $this->admin();
        $this->json_('POST', "/admin/approvals/{$ap->id}/decide", ['decision' => 'approve'])->assertNoContent();
        $this->assertSame('active', $u->fresh()->status);
    }

    public function test_a4_login_generic_error_lockout_and_admin_separation(): void
    {
        $u = $this->user(['email' => 'login@uji.test']);
        $this->json_('POST', '/auth/login', ['login' => 'login@uji.test', 'password' => 'salah'])->assertStatus(422)->assertJsonPath('code', 'invalid_credentials');
        $this->json_('POST', '/auth/login', ['login' => 'tidakada@uji.test', 'password' => 'salah'])->assertStatus(422)->assertJsonPath('code', 'invalid_credentials');
        for ($i = 0; $i < 4; $i++) {
            $this->json_('POST', '/auth/login', ['login' => 'login@uji.test', 'password' => 'salah']);
        }
        $this->json_('POST', '/auth/login', ['login' => 'login@uji.test', 'password' => 'RahasiaKuat123'])->assertStatus(429)->assertJsonPath('code', 'too_many_attempts');

        $this->json_('POST', '/auth/login', ['login' => config('krafio.admin_email'), 'password' => 'apapun'])->assertStatus(422);
        $this->assertNotNull($u);
    }

    public function test_a5_login_success_by_phone_and_status_routing(): void
    {
        $u = $this->user(['email' => 'hp@uji.test', 'phone' => '+6281299998888']);
        $this->json_('POST', '/auth/login', ['login' => '081299998888', 'password' => 'RahasiaKuat123'])->assertOk()->assertJsonPath('data.email', 'hp@uji.test');
        $u->forceFill(['status' => 'suspended'])->save();
        $this->actingUser($u->fresh())->json_('GET', '/app/dashboard')->assertStatus(403)->assertJsonPath('code', 'account_suspended');
    }

    public function test_a6_admin_requires_mfa_and_user_cannot_access_admin(): void
    {
        $this->actingUser($this->user())->json_('GET', '/admin/dashboard')->assertStatus(403)->assertJsonPath('code', 'forbidden');
        $a = User::where('role', 'admin')->first();
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs($a, 'web');
        $this->json_('GET', '/admin/dashboard')->assertStatus(403)->assertJsonPath('code', 'mfa_required');
        $this->admin();
        $this->json_('GET', '/admin/dashboard')->assertOk()->assertJsonStructure(['data' => ['users', 'finance', 'ai', 'system']]);
    }

    public function test_a7_admin_totp_setup_and_verify(): void
    {
        $a = User::where('role', 'admin')->first();
        $a->forceFill(['password' => 'AdminKuat2026x', 'mfa_enabled' => false, 'mfa_secret' => null])->save();
        $r = $this->json_('POST', '/auth/admin/login', ['email' => $a->email, 'password' => 'AdminKuat2026x'])->assertOk()->assertJsonPath('data.mfa', 'setup');
        $secret = $r->json('data.secret');
        $this->json_('POST', '/auth/admin/mfa', ['code' => '000000'])->assertStatus(422)->assertJsonPath('code', 'mfa_invalid');
        $this->json_('POST', '/auth/admin/mfa', ['code' => \App\Services\Totp::code($secret)])->assertOk()->assertJsonPath('data.mfa_passed', true);
        $this->assertTrue($a->fresh()->mfa_enabled);
    }
}
