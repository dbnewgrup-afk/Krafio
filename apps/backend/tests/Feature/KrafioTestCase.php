<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AuthController;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\Wallet;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Basis test Krafio: database MySQL `krafio_test` di-refresh, seeder katalog dijalankan, helper user/admin. */
abstract class KrafioTestCase extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        // Permintaan SPA dari origin stateful (Sanctum) seperti browser sungguhan
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
    }

    protected function user(array $attrs = [], ?string $plan = 'free', int $topup = 0, int $commission = 0): User
    {
        $u = new User(array_merge(['name' => 'Uji Pengguna', 'email' => Str::random(8).'@uji.test', 'password' => 'RahasiaKuat123', 'phone' => '+62812'.random_int(10000000, 99999999)], $attrs));
        $u->forceFill(['role' => 'user', 'status' => 'active', 'email_verified_at' => now(), 'referral_code' => strtoupper(Str::random(8)), 'ai_consent_at' => now()])->save();
        if ($plan === 'free') {
            AuthController::grantFreePlan($u);
        }
        if ($topup > 0) {
            Wallet::post($u->id, 'topup', 'credit', $topup, 'Saldo uji', ['test', null]);
        }
        if ($commission > 0) {
            Wallet::post($u->id, 'commission', 'credit', $commission, 'Komisi uji', ['test', null]);
        }

        return $u->fresh();
    }

    protected function actingUser(User $u): static
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs($u, 'web');

        return $this;
    }

    protected function admin(): User
    {
        $a = User::where('role', 'admin')->first();
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs($a, 'web');
        $this->withSession(['admin_mfa_passed' => true, 'admin_last_seen' => time(), 'admin_reauth_at' => time()]);

        return $a;
    }

    /** Ganti OTP aktif user dengan kode yang diketahui (kode asli tersimpan sebagai hash). */
    protected function knownOtp(User $u, string $code = '123456'): void
    {
        OtpCode::where('user_id', $u->id)->update(['consumed_at' => now()]);
        OtpCode::create(['user_id' => $u->id, 'purpose' => 'verify', 'channel' => 'email', 'destination' => $u->email, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(5)]);
    }

    protected function json_(string $method, string $uri, array $data = [], array $headers = [])
    {
        return $this->json($method, '/api'.$uri, $data, $headers + ['Accept' => 'application/json']);
    }
}
