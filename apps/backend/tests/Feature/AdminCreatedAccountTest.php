<?php

namespace Tests\Feature;

use App\Models\User;

/** Akun dibuat dari Admin Panel: tipe user/admin, langsung aktif, verifikasi email ATAU HP, pengingat ganti kata sandi. */
class AdminCreatedAccountTest extends KrafioTestCase
{
    public function test_g1_admin_creates_active_user_with_generated_password(): void
    {
        $this->admin();
        $r = $this->json_('POST', '/admin/users', ['type' => 'user', 'name' => 'Nadia Pramesti', 'email' => 'Nadia@KopiSenja.id', 'phone' => '0812 3456 7890'])->assertCreated();
        $pw = $r->json('data.password');
        $this->assertSame(12, strlen($pw));
        $u = User::where('email', 'nadia@kopisenja.id')->firstOrFail();
        $this->assertSame('active', $u->status);
        $this->assertSame('user', $u->role);
        $this->assertSame('+6281234567890', $u->phone);
        $this->assertTrue($u->must_change_password);
        $this->assertTrue($u->needsContactVerification());
        $this->assertNotNull($u->activeSubscription(), 'Paket Gratis diberikan');

        $list = $this->json_('GET', '/admin/users?search=Nadia')->assertOk();
        $this->assertTrue($list->json('data.0.must_change_password'));
        $this->assertTrue($list->json('data.0.needs_verification'));
    }

    public function test_g2_user_must_verify_email_or_phone_before_using_panel(): void
    {
        $this->admin();
        $this->json_('POST', '/admin/users', ['type' => 'user', 'name' => 'Raka', 'email' => 'raka@uji.test', 'password' => 'AwalRaka123'])->assertCreated();
        $u = User::where('email', 'raka@uji.test')->firstOrFail();

        $this->actingUser($u);
        $this->json_('GET', '/me')->assertOk()->assertJsonPath('data.needs_verification', true)->assertJsonPath('data.must_change_password', true);
        $this->json_('GET', '/app/dashboard')->assertStatus(403)->assertJsonPath('code', 'contact_verification_required');
        $this->json_('POST', '/me/contact-verification/send', ['channel' => 'whatsapp'])->assertStatus(422);

        $this->json_('POST', '/me/contact-verification/send', ['channel' => 'email'])->assertOk()->assertJsonPath('data.channel', 'email');
        $this->knownOtp($u, '246810');
        $this->json_('POST', '/me/contact-verification/confirm', ['code' => '111111'])->assertStatus(422);
        $this->knownOtp($u, '246810');
        $this->json_('POST', '/me/contact-verification/confirm', ['code' => '246810'])->assertOk()->assertJsonPath('data.needs_verification', false);
        $this->assertNotNull($u->fresh()->email_verified_at);
        $this->json_('GET', '/app/dashboard')->assertOk();

        // Ganti kata sandi → tanda "wajib ganti" hilang
        $this->json_('PUT', '/me/password', ['current_password' => 'AwalRaka123', 'password' => 'BaruRaka2026', 'password_confirmation' => 'BaruRaka2026'])->assertNoContent();
        $this->assertFalse($u->fresh()->must_change_password);
    }

    public function test_g3_admin_type_account_can_log_into_admin_panel_and_validation(): void
    {
        $this->admin();
        $this->json_('POST', '/admin/users', ['type' => 'admin', 'name' => 'Admin Dua', 'email' => 'admin2@krafio.local', 'password' => 'AdminDua2026'])->assertCreated()->assertJsonPath('data.password', null)->assertJsonPath('data.login_url', '/admin/login');
        $this->json_('POST', '/admin/users', ['type' => 'admin', 'name' => 'Dobel', 'email' => 'admin2@krafio.local'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->json_('POST', '/admin/users', ['type' => 'superuser', 'name' => 'X', 'email' => 'x@uji.test'])->assertStatus(422);
        $this->json_('GET', '/admin/users?role=admin')->assertOk()->assertJsonFragment(['name' => 'Admin Dua']);

        $a2 = User::where('email', 'admin2@krafio.local')->firstOrFail();
        $this->assertFalse($a2->needsContactVerification(), 'Verifikasi kontak hanya untuk akun user');

        $u = $this->user();
        $this->actingUser($u)->json_('POST', '/admin/users', ['type' => 'admin', 'name' => 'Penyusup', 'email' => 'p@uji.test'])->assertForbidden();
    }

    public function test_g4_self_registered_users_are_not_gated(): void
    {
        $u = $this->user();
        $this->actingUser($u)->json_('GET', '/app/dashboard')->assertOk();
        $this->json_('GET', '/me')->assertJsonPath('data.needs_verification', false)->assertJsonPath('data.must_change_password', false);
    }
}
