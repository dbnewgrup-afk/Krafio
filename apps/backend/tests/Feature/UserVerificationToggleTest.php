<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Models\User;
use App\Services\Settings;

/** Verifikasi akun user bisa dimatikan sementara (Settings security.user_verification_required) — default tetap wajib. */
class UserVerificationToggleTest extends KrafioTestCase
{
    private function register(string $email)
    {
        return $this->json('POST', '/api/auth/register', [
            'name' => 'Uji Daftar', 'email' => $email, 'password' => 'Kopi12345Senja', 'password_confirmation' => 'Kopi12345Senja', 'agree_terms' => true,
        ], ['Accept' => 'application/json']);
    }

    public function test_default_requires_otp(): void
    {
        $this->register('wajib@uji.test')->assertCreated()->assertJsonPath('data.status', 'pending_verification');
        $this->assertSame(1, OtpCode::count());
    }

    public function test_disabled_activates_new_account_without_otp_and_grants_free_plan(): void
    {
        Settings::set('security.user_verification_required', false);
        $this->register('langsung@uji.test')->assertCreated()->assertJsonPath('data.status', 'active');
        $u = User::where('email', 'langsung@uji.test')->first();
        $this->assertNotNull($u->email_verified_at);
        $this->assertSame(0, OtpCode::count(), 'Tidak ada OTP dikirim');
        $this->assertSame('free', $u->activeSubscription()?->plan_code);
        $this->json_('GET', '/app/dashboard')->assertOk();
    }

    public function test_disabled_unblocks_pending_and_admin_created_accounts(): void
    {
        $pending = $this->user();
        $pending->forceFill(['status' => 'pending_verification', 'email_verified_at' => null])->save();
        $byAdmin = $this->user();
        $byAdmin->forceFill(['created_by' => User::where('role', 'admin')->value('id'), 'email_verified_at' => null, 'phone_verified_at' => null])->save();
        $this->assertTrue($byAdmin->fresh()->needsContactVerification());

        Settings::set('security.user_verification_required', false);
        $this->assertFalse($byAdmin->fresh()->needsContactVerification());
        $this->json('POST', '/api/auth/login', ['login' => $pending->email, 'password' => 'RahasiaKuat123'], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.status', 'active');
        $this->actingUser($byAdmin->fresh())->json_('GET', '/app/dashboard')->assertOk();

        // Dinyalakan lagi → aturan kembali berlaku
        Settings::set('security.user_verification_required', true);
        $this->assertTrue($byAdmin->fresh()->needsContactVerification());
    }
}
