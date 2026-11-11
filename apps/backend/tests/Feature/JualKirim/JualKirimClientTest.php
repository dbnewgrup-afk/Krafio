<?php

namespace Tests\Feature\JualKirim;

use App\Facades\JualKirim;
use App\Services\JualKirim\Exceptions\JualKirimAuthException;
use App\Services\JualKirim\Exceptions\JualKirimException;
use App\Services\JualKirim\Exceptions\JualKirimForbiddenException;
use App\Services\JualKirim\Exceptions\JualKirimMoneyLockedException;
use App\Services\JualKirim\Exceptions\JualKirimValidationException;
use App\Services\JualKirim\JualKirimClient;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Integrasi JualKirim API v1 — seluruhnya memakai Http::fake(); tidak ada request ke API asli.
 */
class JualKirimClientTest extends TestCase
{
    private const BASE = 'https://jk.test';

    private const API_KEY = 'A1B2C3D4E5F6G7H8I9J0K1L';

    private const PASSWORD = 'rahasia-sangat-123';

    private int $logins = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'jualkirim.base_url' => self::BASE,
            'jualkirim.email' => 'ops@toko.test',
            'jualkirim.password' => self::PASSWORD,
            'jualkirim.api_key' => self::API_KEY,
            'jualkirim.money_enabled' => false,
            'jualkirim.retry.sleep_ms' => 0,
        ]);
        $this->app->forgetInstance(JualKirimClient::class);
        JualKirim::clearResolvedInstances();
        Cache::flush();
        $this->logins = 0;
    }

    private function jwt(int $n = 1): string
    {
        return "eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiJ1c2VyLTEiLCJuIjo{$n}fQ.sig{$n}";
    }

    /** Respon login palsu; menghitung berapa kali login dipanggil. */
    private function loginResponse(?string $expiresAt = null)
    {
        $this->logins++;

        return Http::response(['message' => 'Login berhasil', 'data' => ['token' => $this->jwt($this->logins), 'expires_at' => $expiresAt, 'organizations' => [['id' => 'org-1', 'name' => 'Toko A', 'api_key' => self::API_KEY]]]], 200);
    }

    /** @param  array<string, mixed>  $routes  path => response|callable */
    private function fake(array $routes, ?string $expiresAt = null): void
    {
        Http::fake(function (Request $r) use ($routes, $expiresAt) {
            $path = parse_url($r->url(), PHP_URL_PATH);
            if ($path === '/api/v1/auth/login') {
                return $this->loginResponse($expiresAt);
            }
            $route = $routes[$r->method().' '.$path] ?? $routes[$path] ?? null;
            if (is_callable($route)) {
                return $route($r);
            }

            return $route ?? Http::response(['message' => 'Tidak ditemukan', 'data' => null], 404);
        });
    }

    private function ok(mixed $data = null): PromiseInterface
    {
        return Http::response(['message' => 'Berhasil', 'data' => $data], 200);
    }

    /* ---------------- Token ---------------- */

    public function test_login_success_stores_token_in_cache(): void
    {
        $this->fake(['/api/v1/wallet/balance' => $this->ok(['organization_id' => 'org-1', 'balance' => 150000])]);

        $res = JualKirim::wallet()->balance();

        $this->assertSame(150000, $res['data']['balance']);
        $this->assertSame($this->jwt(1), Cache::get('jualkirim.jwt'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/v1/auth/login') && $r['email'] === 'ops@toko.test' && $r['password'] === self::PASSWORD && ! $r->hasHeader('X-API-KEY'));
    }

    public function test_token_is_reused_from_cache(): void
    {
        $this->fake(['/api/v1/invoices' => $this->ok([['code' => 'jne', 'name' => 'JNE', 'logo' => '/x.png']])]);

        JualKirim::invoices()->list();
        JualKirim::invoices()->list();
        JualKirim::wallet(); // resource lain memakai client yang sama

        $this->assertSame(1, $this->logins, 'Login hanya sekali; request berikutnya memakai token dari cache');
    }

    public function test_token_ttl_uses_expires_at_minus_five_minutes(): void
    {
        $this->travelTo(now()->startOfMinute());
        $this->fake(['/api/v1/invoices' => $this->ok([])], now()->addHours(2)->toIso8601String());

        JualKirim::invoices()->list();

        $this->travel(114)->minutes();
        $this->assertNotNull(Cache::get('jualkirim.jwt'), 'Masih berlaku sebelum expires_at − 5 menit');
        $this->travel(2)->minutes();
        $this->assertNull(Cache::get('jualkirim.jwt'), 'Kedaluwarsa 5 menit sebelum expires_at');
    }

    public function test_401_triggers_relogin_and_single_retry_then_success(): void
    {
        $calls = 0;
        $this->fake(['/api/v1/invoices' => function (Request $r) use (&$calls) {
            $calls++;

            return $calls === 1 ? Http::response(['message' => 'Token tidak valid atau sudah kedaluwarsa', 'data' => null], 401) : $this->ok([['id' => 'a1', 'name' => 'Gudang', 'email' => 'g@x.test']]);
        }]);

        $res = JualKirim::invoices()->list();

        $this->assertSame('Gudang', $res['data'][0]['name']);
        $this->assertSame(2, $calls, 'Request diulang tepat satu kali');
        $this->assertSame(2, $this->logins, 'Login ulang setelah 401');
        $this->assertSame($this->jwt(2), Cache::get('jualkirim.jwt'), 'Token baru menggantikan yang lama');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/v1/invoices') && $r->hasHeader('Authorization', 'Bearer '.$this->jwt(2)));
    }

    public function test_repeated_401_throws_auth_exception(): void
    {
        $calls = 0;
        $this->fake(['/api/v1/invoices' => function () use (&$calls) {
            $calls++;

            return Http::response(['message' => 'X-API-KEY tidak cocok', 'data' => null], 401);
        }]);

        try {
            JualKirim::invoices()->list();
            $this->fail('Seharusnya melempar JualKirimAuthException');
        } catch (JualKirimAuthException $e) {
            $this->assertSame(401, $e->status);
            $this->assertStringContainsString('X-API-KEY tidak cocok', $e->getMessage());
            $this->assertStringContainsString('JUALKIRIM_API_KEY', $e->getMessage());
        }
        $this->assertSame(2, $calls, 'Tidak ada loop login tanpa akhir');
    }

    public function test_wrong_credentials_on_login_gives_clear_auth_exception(): void
    {
        Http::fake(['*/api/v1/auth/login' => Http::response(['message' => 'Email atau kata sandi tidak valid', 'data' => null], 401)]);

        $this->expectException(JualKirimAuthException::class);
        $this->expectExceptionMessage('Periksa JUALKIRIM_EMAIL/JUALKIRIM_PASSWORD');
        JualKirim::wallet()->balance();
    }

    /* ---------------- Error mapping ---------------- */

    public function test_403_throws_forbidden_exception(): void
    {
        $this->fake(['/api/v1/disbursement-batches' => Http::response(['message' => 'Anda tidak memiliki akses untuk aksi ini', 'data' => null], 403)]);

        $this->expectException(JualKirimForbiddenException::class);
        $this->expectExceptionMessage('tidak memiliki permission');
        JualKirim::disbursements()->list();
    }

    public function test_422_throws_validation_exception_with_details(): void
    {
        $detail = ['items' => [['row' => 1, 'valid' => false, 'errors' => ['bank tidak didukung']]], 'summary' => ['invalid_items' => 1, 'sufficient' => true], 'errors' => []];
        $this->fake(['/api/v1/invoices' => Http::response(['message' => 'Gagal membuat alamat: nama duplikat', 'data' => $detail], 422)]);

        try {
            JualKirim::invoices()->create(['name' => 'Gudang', 'email' => 'g@x.test']);
            $this->fail('Seharusnya melempar JualKirimValidationException');
        } catch (JualKirimValidationException $e) {
            $this->assertSame(422, $e->status);
            $this->assertStringContainsString('nama duplikat', $e->getMessage());
            $this->assertSame($detail, $e->responseData);
            $this->assertSame('POST /api/v1/invoices', $e->endpoint);
        }
    }

    public function test_400_field_errors_are_exposed(): void
    {
        $this->fake(['/api/v1/invoices' => Http::response(['message' => 'Validasi gagal', 'data' => ['email' => 'Email wajib diisi']], 400)]);

        try {
            JualKirim::invoices()->create(['name' => 'Gudang', 'email' => '']);
            $this->fail();
        } catch (JualKirimValidationException $e) {
            $this->assertSame(['email' => 'Email wajib diisi'], $e->errors());
        }
    }

    public function test_server_error_is_generic_exception_and_get_is_retried(): void
    {
        $calls = 0;
        $this->fake(['/api/v1/invoices' => function () use (&$calls) {
            $calls++;

            return Http::response(['message' => 'Kesalahan server', 'data' => null], 500);
        }]);

        try {
            JualKirim::invoices()->list();
            $this->fail();
        } catch (JualKirimException $e) {
            $this->assertNotInstanceOf(JualKirimValidationException::class, $e);
            $this->assertSame(500, $e->status);
        }
        $this->assertSame(3, $calls, 'GET dicoba 1 + 2 retry');
    }

    public function test_post_is_never_auto_retried_on_server_error(): void
    {
        $calls = 0;
        $this->fake(['/api/v1/invoices' => function () use (&$calls) {
            $calls++;

            return Http::response(['message' => 'Kesalahan server', 'data' => null], 502);
        }]);

        $this->expectException(JualKirimException::class);
        try {
            JualKirim::invoices()->create(['customer_name' => 'Budi']);
        } finally {
            $this->assertSame(1, $calls, 'POST tidak diulang otomatis (tidak ada idempotency key di spec)');
        }
    }

    /* ---------------- Header ---------------- */

    public function test_authorization_and_api_key_headers_are_always_sent(): void
    {
        $this->fake([
            '/api/v1/me' => $this->ok(['user' => ['id' => 'u1', 'email' => 'ops@toko.test', 'first_name' => 'Budi', 'last_name' => 'S'], 'organizations' => []]),
            '/api/v1/invoices' => $this->ok(['invoices' => [], 'pagination' => ['page' => 1, 'per_page' => 20, 'total' => 0, 'total_filtered' => 0]]),
            '/api/v1/wallet/balance' => $this->ok(['balance' => 0]),
        ]);

        JualKirim::me();
        JualKirim::invoices()->list();
        JualKirim::wallet()->balance();

        $nonLogin = Http::recorded(fn (Request $r) => ! str_ends_with($r->url(), '/auth/login'));
        $this->assertCount(3, $nonLogin);
        foreach ($nonLogin as [$req]) {
            $this->assertTrue($req->hasHeader('Authorization', 'Bearer '.$this->jwt(1)), $req->url());
            $this->assertTrue($req->hasHeader('X-API-KEY', self::API_KEY), $req->url());
            $this->assertTrue($req->hasHeader('Accept', 'application/json'));
        }
    }

    /* ---------------- Keamanan ---------------- */

    public function test_failure_logs_never_contain_secrets(): void
    {
        $logged = [];
        Log::shouldReceive('channel')->with('jualkirim')->andReturnSelf();
        Log::shouldReceive('warning')->andReturnUsing(function ($msg, $ctx) use (&$logged) {
            $logged[] = json_encode([$msg, $ctx]);
        });
        $this->fake(['/api/v1/wallet/balance' => Http::response(['message' => 'Key '.self::API_KEY.' token '.$this->jwt(9).' pw '.self::PASSWORD, 'data' => ['x' => self::API_KEY]], 422)]);

        try {
            JualKirim::wallet()->balance();
        } catch (JualKirimValidationException) {
        }

        $this->assertNotEmpty($logged);
        $all = implode("\n", $logged);
        $this->assertStringNotContainsString(self::API_KEY, $all);
        $this->assertStringNotContainsString(self::PASSWORD, $all);
        $this->assertStringNotContainsString($this->jwt(9), $all);
        $this->assertStringNotContainsString('eyJ', $all);
    }

    public function test_money_endpoints_are_locked_by_default_and_send_nothing(): void
    {
        Http::fake();

        foreach ([
            fn () => JualKirim::payments()->checkout('pay-1', 'QRIS'),
            fn () => JualKirim::disbursements()->create([['bank_code' => 'BCA', 'account_number' => '1234567890', 'account_holder' => 'Budi', 'amount' => 50000]]),
            fn () => JualKirim::disbursements()->cancel('batch-1'),
        ] as $call) {
            try {
                $call();
                $this->fail('Endpoint uang seharusnya terkunci');
            } catch (JualKirimMoneyLockedException $e) {
                $this->assertStringContainsString('JUALKIRIM_MONEY_ENABLED', $e->getMessage());
            }
        }
        Http::assertNothingSent();
    }

    /* ---------------- Satu test per resource ---------------- */

    public function test_auth_resource_me_maps_user_and_organizations(): void
    {
        $this->fake(['/api/v1/me' => $this->ok(['user' => ['id' => 'u1', 'email' => 'ops@toko.test', 'first_name' => 'Budi', 'last_name' => 'Santoso'], 'organizations' => [['id' => 'org-1', 'name' => 'Toko A', 'api_key' => self::API_KEY]]])]);

        $me = JualKirim::auth()->me();

        $this->assertSame('Budi Santoso', $me->fullName());
        $this->assertSame('Toko A', $me->organizationForKey(self::API_KEY)?->name);
        $this->assertNull($me->organizationForKey('ZZZZZZZZZZZZZZZZZZZZZZZ'));
    }

    public function test_path_ids_are_encoded(): void
    {
        $this->fake(['/api/v1/invoices/..%2Fwallet%2Fbalance' => $this->ok([])]);

        JualKirim::invoices()->find('../wallet/balance');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/api/v1/invoices/..%2Fwallet%2Fbalance'));
    }

    public function test_wallet_resource_balance(): void
    {
        $this->fake(['/api/v1/wallet/balance' => $this->ok(['organization_id' => 'org-1', 'balance' => 250000])]);
        $this->assertSame(250000, JualKirim::wallet()->balance()['data']['balance']);
    }

    public function test_invoice_resource_crud(): void
    {
        $this->fake([
            'GET /api/v1/invoices' => $this->ok(['invoices' => [], 'pagination' => []]),
            'POST /api/v1/invoices' => Http::response(['message' => 'Tagihan berhasil dibuat', 'data' => ['id' => 'i1', 'payment_link' => 'https://pay.test/i1']], 201),
            'GET /api/v1/invoices/i1' => $this->ok(['invoice' => [], 'status_label' => 'Terkirim']),
            'PUT /api/v1/invoices/i1' => $this->ok(null),
            'DELETE /api/v1/invoices/i1' => $this->ok(null),
        ]);

        JualKirim::invoices()->list(['status' => 'sent']);
        $this->assertSame('https://pay.test/i1', JualKirim::invoices()->create(['customer_name' => 'Budi', 'items' => [['description' => 'Desain', 'qty' => 1]]])['data']['payment_link']);
        JualKirim::invoices()->find('i1');
        JualKirim::invoices()->update('i1', ['customer_name' => 'Budi S']);
        JualKirim::invoices()->delete('i1');

        $this->assertCount(5, Http::recorded(fn (Request $r) => str_contains($r->url(), '/api/v1/invoices')));
    }

    public function test_payment_resource_reads_and_checkout_when_money_enabled(): void
    {
        config(['jualkirim.money_enabled' => true]);
        $this->app->forgetInstance(JualKirimClient::class);
        JualKirim::clearResolvedInstances();
        $this->fake([
            'GET /api/v1/payments' => $this->ok(['payments' => [], 'pagination' => []]),
            'POST /api/v1/payments' => Http::response(['message' => 'Pembayaran berhasil dibuat', 'data' => ['id' => 'p1', 'status' => 'pending']], 201),
            'GET /api/v1/payments/p1' => $this->ok(['payment' => []]),
            'PUT /api/v1/payments/p1' => $this->ok(null),
            'DELETE /api/v1/payments/p1' => $this->ok(null),
            'POST /api/v1/payments/p1/checkout' => $this->ok(['paid' => false, 'payment_method' => 'QRIS', 'qr_string' => '000201...', 'total_amount' => 505000]),
            'GET /api/v1/payments/p1/checkout' => $this->ok(['paid' => false, 'payment_method' => 'QRIS']),
            'GET /api/v1/payments/p1/status' => $this->ok(['status' => 'processing', 'paid' => false]),
            'GET /api/v1/payment-methods' => $this->ok(['methods' => [['code' => 'wallet']], 'wallet_balance' => 150000]),
        ]);

        JualKirim::payments()->list();
        JualKirim::payments()->create(['from_name' => 'Toko A']);
        JualKirim::payments()->find('p1');
        JualKirim::payments()->update('p1', ['notes' => 'x']);
        JualKirim::payments()->delete('p1');
        $this->assertSame('000201...', JualKirim::payments()->checkout('p1', 'QRIS')['data']['qr_string']);
        JualKirim::payments()->resumeCheckout('p1');
        $this->assertFalse(JualKirim::payments()->status('p1')['data']['paid']);
        $this->assertSame(150000, JualKirim::payments()->methods()['data']['wallet_balance']);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/payments/p1/checkout') && $r['payment_method'] === 'QRIS');
    }

    public function test_payment_checkout_403_is_forbidden_and_not_retried(): void
    {
        config(['jualkirim.money_enabled' => true]);
        $this->app->forgetInstance(JualKirimClient::class);
        JualKirim::clearResolvedInstances();
        $calls = 0;
        $this->fake(['POST /api/v1/payments/p1/checkout' => function () use (&$calls) {
            $calls++;

            return Http::response(['message' => 'Anda tidak memiliki akses untuk aksi ini', 'data' => null], 403);
        }]);

        $this->expectException(JualKirimForbiddenException::class);
        try {
            JualKirim::payments()->checkout('p1', 'wallet');
        } finally {
            $this->assertSame(1, $calls);
        }
    }

    public function test_disbursement_resource_validate_json_csv_list_find(): void
    {
        $result = ['items' => [], 'summary' => ['total_items' => 1, 'invalid_items' => 0, 'sufficient' => true], 'errors' => []];
        $this->fake([
            'POST /api/v1/disbursement-batches/validate' => $this->ok($result),
            'GET /api/v1/disbursement-batches' => $this->ok(['batches' => [], 'pagination' => []]),
            'GET /api/v1/disbursement-batches/b1' => $this->ok(['id' => 'b1', 'items' => []]),
        ]);

        $items = [['bank_code' => 'BCA', 'account_number' => '1234567890', 'account_holder' => 'Budi', 'amount' => 150000]];
        $this->assertTrue(JualKirim::disbursements()->validate($items)['data']['summary']['sufficient']);
        JualKirim::disbursements()->validateCsv("bank_code,account_number,account_holder,amount,email,phone,note\nBCA,1234567890,Budi,150000,,,\n");
        JualKirim::disbursements()->list(['status' => 'processing']);
        JualKirim::disbursements()->find('b1');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/validate') && $r->isJson() && $r['items'][0]['bank_code'] === 'BCA');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/validate') && $r->isMultipart());
    }

    public function test_disbursement_create_when_enabled_sends_items_and_schedule(): void
    {
        config(['jualkirim.money_enabled' => true]);
        $this->app->forgetInstance(JualKirimClient::class);
        JualKirim::clearResolvedInstances();
        $this->fake(['POST /api/v1/disbursement-batches' => Http::response(['message' => 'Batch disbursement berhasil dibuat', 'data' => ['id' => 'b1', 'status' => 'scheduled']], 201)]);

        $res = JualKirim::disbursements()->create([['bank_code' => 'BNI', 'account_number' => '9876543210', 'account_holder' => 'Ani', 'amount' => 25000]], '2026-12-01T09:00:00+07:00');

        $this->assertSame('scheduled', $res['data']['status']);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/disbursement-batches') && $r['scheduled_at'] === '2026-12-01T09:00:00+07:00' && $r['source'] === 'manual' && count($r['items']) === 1);
    }

    /* ---------------- Artisan ---------------- */

    public function test_artisan_test_command_confirms_api_key(): void
    {
        $this->fake(['/api/v1/me' => $this->ok(['user' => ['id' => 'u1', 'email' => 'ops@toko.test', 'first_name' => 'Budi', 'last_name' => 'Santoso'], 'organizations' => [['id' => 'org-1', 'name' => 'Toko A', 'api_key' => self::API_KEY]]])]);

        $this->artisan('jualkirim:test')
            ->expectsOutputToContain('Login berhasil')
            ->expectsOutputToContain('Budi Santoso')
            ->expectsOutputToContain('API key cocok dengan organisasi "Toko A"')
            ->doesntExpectOutputToContain(self::API_KEY)
            ->assertSuccessful();
    }

    public function test_artisan_test_command_reports_mismatched_key(): void
    {
        config(['jualkirim.api_key' => 'ZZZZZZZZZZZZZZZZZZZZZZZ']);
        $this->app->forgetInstance(JualKirimClient::class);
        $this->fake(['/api/v1/me' => $this->ok(['user' => ['first_name' => 'Budi'], 'organizations' => [['id' => 'org-1', 'name' => 'Toko A', 'api_key' => self::API_KEY]]])]);
        $this->artisan('jualkirim:test')->expectsOutputToContain('tidak cocok')->assertFailed();
    }

    public function test_artisan_test_command_reports_bad_credentials(): void
    {
        Http::fake(['*/api/v1/auth/login' => Http::response(['message' => 'Email belum diverifikasi', 'data' => null], 401)]);
        $this->artisan('jualkirim:test')->expectsOutputToContain('Email belum diverifikasi')->assertFailed();
    }

    public function test_artisan_clear_token(): void
    {
        Cache::put('jualkirim.jwt', 'x', 60);
        $this->artisan('jualkirim:clear-token')->assertSuccessful();
        $this->assertNull(Cache::get('jualkirim.jwt'));
    }
}
