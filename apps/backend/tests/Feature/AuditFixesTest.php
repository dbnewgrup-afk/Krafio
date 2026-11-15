<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\User;
use App\Services\Settings;
use App\Services\Wallet;
use Illuminate\Support\Facades\RateLimiter;

/** Regresi temuan audit 14 Nov 2026 (report/AUDIT_2026-11-14.md). */
class AuditFixesTest extends KrafioTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('admin-login:127.0.0.1');
    }

    private function adminLogin(string $ip, array $headers = [])
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->json('POST', '/api/auth/admin/login', ['email' => config('krafio.admin_email'), 'password' => 'salah-total'], $headers + ['Accept' => 'application/json']);
    }

    /** A1: header X-Forwarded-For palsu tidak boleh mengganti IP klien (rate limit tetap berlaku). */
    public function test_spoofed_forwarded_for_does_not_bypass_admin_login_limit(): void
    {
        foreach (range(1, 5) as $i) {
            $this->adminLogin('203.0.113.7', ['X-Forwarded-For' => "10.0.0.{$i}"])->assertStatus(422);
        }
        $this->adminLogin('203.0.113.7', ['X-Forwarded-For' => '10.0.0.99'])->assertStatus(429);
    }

    /** A1: brute force terdistribusi (banyak IP asli) tetap terkunci per akun. */
    public function test_admin_login_locks_per_account_across_ips(): void
    {
        foreach (range(1, 10) as $i) {
            $this->adminLogin("198.51.100.{$i}")->assertStatus(422);
        }
        $this->adminLogin('198.51.100.200')->assertStatus(429);
    }

    public function test_user_login_locks_per_account_across_ips(): void
    {
        Settings::set('security.login_max_attempts', 2);
        $u = $this->user();
        foreach (range(1, 8) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "192.0.2.{$i}"])
                ->json('POST', '/api/auth/login', ['login' => $u->email, 'password' => 'salah'], ['Accept' => 'application/json'])->assertStatus(422);
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.250'])
            ->json('POST', '/api/auth/login', ['login' => $u->email, 'password' => 'RahasiaKuat123'], ['Accept' => 'application/json'])->assertStatus(429);
    }

    /** A2: approval withdrawal yang diputuskan dua kali tidak mengembalikan dana dua kali. */
    public function test_withdrawal_reject_cannot_refund_twice(): void
    {
        $u = $this->user([], 'free', 0, 200000);
        $this->actingUser($u);
        $d = $this->json_('POST', '/app/wallet/destinations', ['type' => 'bank', 'provider' => 'BCA', 'holder' => 'Uji', 'account' => '1234567890'])->json('data');
        $this->json_('POST', '/app/wallet/withdrawals', ['amount' => 150000, 'destination_id' => $d['id']])->assertCreated();
        $ap = Approval::where('type', 'withdrawal')->firstOrFail();

        $this->admin();
        $this->json_('POST', "/admin/approvals/{$ap->id}/decide", ['decision' => 'reject', 'reason' => 'Rekening tidak sesuai'])->assertNoContent();
        $this->assertSame(200000, Wallet::balance($u->id, 'commission'));

        // Permintaan kedua yang membaca status "pending" sebelum permintaan pertama selesai (klik ganda)
        $ap->forceFill(['status' => 'pending'])->save();
        $this->json_('POST', "/admin/approvals/{$ap->id}/decide", ['decision' => 'reject', 'reason' => 'Rekening tidak sesuai'])->assertStatus(422);
        $this->assertSame(200000, Wallet::balance($u->id, 'commission'), 'Dana tidak boleh dikembalikan dua kali');
    }

    /** A3: per_page negatif/nol tidak boleh menghasilkan error 500. */
    public function test_negative_per_page_is_clamped(): void
    {
        $this->actingUser($this->user());
        foreach (['/app/credits/history', '/app/jobs/history', '/app/wallet/ledger', '/app/affiliate/commissions', '/me/notifications', '/app/jobs'] as $uri) {
            $this->json_('GET', $uri.'?per_page=-5')->assertOk();
        }
    }

    /** A4: pengaturan keamanan/uang wajib re-autentikasi bila nilainya berubah. */
    public function test_sensitive_settings_require_reauth(): void
    {
        $this->admin();
        $this->withSession(['admin_mfa_passed' => true, 'admin_last_seen' => time(), 'admin_reauth_at' => time() - 3600]);
        $this->json_('PUT', '/admin/settings', ['values' => ['security.admin_mfa_required' => false]])->assertStatus(403)->assertJsonPath('code', 'reauth_required');
        $this->assertTrue((bool) Settings::get('security.admin_mfa_required'));
        // Nilai sensitif yang tidak berubah + pengaturan umum tetap bisa disimpan tanpa konfirmasi
        $this->json_('PUT', '/admin/settings', ['values' => ['security.admin_mfa_required' => true, 'general.app_name' => 'Krafio QA']])->assertOk();
        $this->withSession(['admin_mfa_passed' => true, 'admin_last_seen' => time(), 'admin_reauth_at' => time()]);
        $this->json_('PUT', '/admin/settings', ['values' => ['security.admin_mfa_required' => false]])->assertOk();
    }

    /** A5: lampiran inbox hanya boleh media Krafio (bukan URL eksternal/pelacak). */
    public function test_inbox_attachments_must_be_krafio_media(): void
    {
        $this->actingUser($this->user());
        $t = $this->json_('POST', '/app/inbox', ['subject' => 'Tanya paket', 'body' => 'Halo admin'])->assertCreated()->json('data');
        $this->json_('POST', "/app/inbox/{$t['id']}/messages", ['body' => 'lihat', 'attachments' => [['url' => 'https://evil.example/pixel.png']]])->assertStatus(422);
        $this->json_('POST', "/app/inbox/{$t['id']}/messages", ['body' => 'lihat', 'attachments' => [['url' => '/api/media/1/abcDEF123.png']]])->assertCreated();
    }

    /** A6: kunci idempotensi withdrawal milik user lain tidak membocorkan data. */
    public function test_withdrawal_idempotency_key_is_scoped_to_user(): void
    {
        $a = $this->user([], 'free', 0, 300000);
        $this->actingUser($a);
        $d = $this->json_('POST', '/app/wallet/destinations', ['type' => 'bank', 'provider' => 'BCA', 'holder' => 'A', 'account' => '1234567890'])->json('data');
        $this->json_('POST', '/app/wallet/withdrawals', ['amount' => 100000, 'destination_id' => $d['id']], ['Idempotency-Key' => 'k-123'])->assertCreated();

        $b = $this->user([], 'free', 0, 300000);
        $this->actingUser($b);
        $d2 = $this->json_('POST', '/app/wallet/destinations', ['type' => 'bank', 'provider' => 'BNI', 'holder' => 'B', 'account' => '9876543210'])->json('data');
        $res = $this->json_('POST', '/app/wallet/withdrawals', ['amount' => 100000, 'destination_id' => $d2['id']], ['Idempotency-Key' => 'k-123'])->assertCreated();
        $this->assertSame('****3210', $res->json('data.destination.account_masked'), 'Data withdrawal user lain tidak boleh dikembalikan');
        // Key sangat panjang tidak boleh memicu error database
        $this->json_('POST', '/app/wallet/withdrawals', ['amount' => 100000, 'destination_id' => $d2['id']], ['Idempotency-Key' => str_repeat('x', 300)])->assertCreated();
        // Key yang sama dari user yang sama tetap idempoten
        $this->json_('POST', '/app/wallet/withdrawals', ['amount' => 100000, 'destination_id' => $d2['id']], ['Idempotency-Key' => 'k-123'])->assertOk();
    }

    /** B8: admin yang baru login dengan kata sandi (MFA belum lolos) tidak boleh mengubah akun. */
    public function test_admin_session_before_mfa_is_read_only(): void
    {
        Settings::set('security.admin_mfa_required', true);
        $admin = User::where('role', 'admin')->first();
        $admin->forceFill(['password' => 'AdminKuat12345'])->save();
        $this->json('POST', '/api/auth/admin/login', ['email' => $admin->email, 'password' => 'AdminKuat12345'], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.mfa', 'setup');
        $this->json('GET', '/api/me', [], ['Accept' => 'application/json'])->assertOk();
        $this->json('PUT', '/api/me/password', ['current_password' => 'AdminKuat12345', 'password' => 'BaruKuat123456', 'password_confirmation' => 'BaruKuat123456'], ['Accept' => 'application/json'])->assertStatus(403)->assertJsonPath('code', 'mfa_required');
        $this->json('POST', '/api/me/sessions/revoke-others', [], ['Accept' => 'application/json'])->assertStatus(403);
        $this->json('GET', '/api/admin/dashboard', [], ['Accept' => 'application/json'])->assertStatus(403);
        // Setelah MFA lolos, semuanya normal
        $this->withSession(['admin_mfa_passed' => true, 'admin_last_seen' => time()]);
        $this->json('POST', '/api/me/sessions/revoke-others', [], ['Accept' => 'application/json'])->assertSuccessful();
    }
}
