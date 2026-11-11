<?php

namespace App\Services\JualKirim;

use App\Services\JualKirim\Data\LoginResult;
use App\Services\JualKirim\Exceptions\JualKirimAuthException;
use App\Services\JualKirim\Exceptions\JualKirimException;
use App\Services\JualKirim\Exceptions\JualKirimForbiddenException;
use App\Services\JualKirim\Exceptions\JualKirimMoneyLockedException;
use App\Services\JualKirim\Exceptions\JualKirimValidationException;
use App\Services\JualKirim\Resources\AuthResource;
use App\Services\JualKirim\Resources\DisbursementResource;
use App\Services\JualKirim\Resources\InvoiceResource;
use App\Services\JualKirim\Resources\PaymentResource;
use App\Services\JualKirim\Resources\WalletResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Client JualKirim API v1 (acuan tunggal: docs/jualkirim-openapi.yaml).
 *
 * Autentikasi dua lapis di setiap request (kecuali login & endpoint publik):
 *   Authorization: Bearer <JWT>  — dari POST /api/v1/auth/login, berlaku 24 jam, tanpa refresh token.
 *   X-API-KEY: <api key organisasi>
 *
 * Token disimpan di cache `jualkirim.jwt`; login dilindungi Cache::lock agar request paralel
 * hanya memicu satu login. 401 → token dihapus, login ulang, request diulang SATU kali.
 *
 * Retry otomatis (koneksi/5xx) hanya untuk metode idempoten GET/PUT/DELETE. Request POST
 * tidak pernah diulang otomatis karena spec tidak menyediakan idempotency key.
 */
class JualKirimClient
{
    public const AUTH_BOTH = 'both';

    public const AUTH_BEARER = 'bearer';

    public const AUTH_NONE = 'none';

    /** @var array<string, object> */
    private array $resources = [];

    /**
     * @param  array{base_url:string,email:?string,password:?string,api_key:?string,timeout:int,money_enabled:bool,retry:array{times:int,sleep_ms:int},cache:array{token_key:string,lock_key:string,token_ttl_seconds:int}}  $config
     */
    public function __construct(private array $config) {}

    /* ================= Resource per tag OpenAPI ================= */

    public function auth(): AuthResource
    {
        return $this->resource(AuthResource::class);
    }

    public function wallet(): WalletResource
    {
        return $this->resource(WalletResource::class);
    }

    public function invoices(): InvoiceResource
    {
        return $this->resource(InvoiceResource::class);
    }

    public function payments(): PaymentResource
    {
        return $this->resource(PaymentResource::class);
    }

    public function disbursements(): DisbursementResource
    {
        return $this->resource(DisbursementResource::class);
    }

    /** Pintasan GET /api/v1/me. */
    public function me(): Data\MeData
    {
        return $this->auth()->me();
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T
     */
    private function resource(string $class): object
    {
        return $this->resources[$class] ??= new $class($this);
    }

    /* ================= Token ================= */

    /** JWT dari cache; bila kosong login sekali (dilindungi lock) dan simpan dengan TTL aman. */
    public function getToken(): string
    {
        $key = $this->config['cache']['token_key'];
        if (is_string($t = Cache::get($key)) && $t !== '') {
            return $t;
        }

        return Cache::lock($this->config['cache']['lock_key'], 10)->block(10, function () use ($key) {
            // Request lain mungkin sudah login selama kita menunggu lock
            if (is_string($t = Cache::get($key)) && $t !== '') {
                return $t;
            }
            $login = $this->login();
            Cache::put($key, $login->token, $this->ttlFor($login));

            return $login->token;
        });
    }

    public function forgetToken(): void
    {
        Cache::forget($this->config['cache']['token_key']);
    }

    /** TTL token: expires_at − 5 menit bila tersedia, selain itu 23 jam. Minimal 60 detik. */
    private function ttlFor(LoginResult $login): int
    {
        if ($login->expiresAt) {
            $secs = $login->expiresAt->getTimestamp() - CarbonImmutable::now()->getTimestamp() - 300;

            return max(60, $secs);
        }

        return $this->config['cache']['token_ttl_seconds'];
    }

    /** POST /api/v1/auth/login dengan kredensial dari config (tanpa cache). */
    public function login(): LoginResult
    {
        $email = (string) ($this->config['email'] ?? '');
        $password = (string) ($this->config['password'] ?? '');
        if ($email === '' || $password === '') {
            throw new JualKirimAuthException('Kredensial JualKirim belum diatur. Isi JUALKIRIM_EMAIL dan JUALKIRIM_PASSWORD di .env.', null, 'POST /api/v1/auth/login');
        }

        $response = $this->send('POST', '/api/v1/auth/login', ['json' => ['email' => $email, 'password' => $password]], self::AUTH_NONE, retryAuth: false);

        return LoginResult::fromArray((array) $response);
    }

    /* ================= Request ================= */

    /**
     * Kirim request dan kembalikan body JSON sebagai array.
     *
     * @param  array{query?:array<string,mixed>,json?:array<mixed>,multipart?:array<int,array{name:string,contents:mixed,filename?:string}>}  $options
     * @param  bool  $money  endpoint pemindah uang: ditolak bila JUALKIRIM_MONEY_ENABLED=false
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, array $options = [], string $auth = self::AUTH_BOTH, bool $money = false): array
    {
        if ($money && ! $this->config['money_enabled']) {
            throw new JualKirimMoneyLockedException("Endpoint pemindah uang {$method} {$path} dikunci. Set JUALKIRIM_MONEY_ENABLED=true hanya di environment yang memang boleh bertransaksi.", null, "{$method} {$path}");
        }

        return $this->send($method, $path, $options, $auth, retryAuth: true);
    }

    /**
     * @param  array{query?:array<string,mixed>,json?:array<mixed>,multipart?:array<int,array{name:string,contents:mixed,filename?:string}>}  $options
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $options, string $auth, bool $retryAuth): array
    {
        $response = $this->dispatch($method, $path, $options, $auth);

        // 401 pada request ber-token: hapus token, login ulang, ulangi SATU kali.
        if ($response->status() === 401 && $retryAuth && $auth !== self::AUTH_NONE) {
            $this->forgetToken();
            $response = $this->dispatch($method, $path, $options, $auth);
        }

        if ($response->successful()) {
            $json = $response->json();
            if (! is_array($json)) {
                $this->logFailure($method, $path, $response->status(), 'Response bukan JSON');
                throw new JualKirimException(JualKirimException::describe($method, $path, $response->status(), 'response bukan JSON'), $response->status(), "{$method} {$path}");
            }

            return $json;
        }

        throw $this->toException($method, $path, $response);
    }

    /** @param  array{query?:array<string,mixed>,json?:array<mixed>,multipart?:array<int,array{name:string,contents:mixed,filename?:string}>}  $options */
    private function dispatch(string $method, string $path, array $options, string $auth): Response
    {
        $req = $this->http($method, ! empty($options['multipart']));
        $req = $this->withAuth($req, $auth);

        try {
            return match (true) {
                // Bagian multipart dikirim langsung lewat opsi 'multipart' (attach() saja tidak mengisi body pada send())
                ! empty($options['multipart']) => $req->send($method, $path, ['query' => $options['query'] ?? [], 'multipart' => $options['multipart']]),
                $method === 'GET' => $req->get($path, $options['query'] ?? []),
                default => $req->send($method, $path, array_filter(['query' => $options['query'] ?? null, 'json' => $options['json'] ?? null], fn ($v) => $v !== null)),
            };
        } catch (ConnectionException $e) {
            $this->logFailure($method, $path, null, 'Gagal terhubung: '.$this->mask($e->getMessage()));
            throw new JualKirimException(JualKirimException::describe($method, $path, null, 'tidak dapat terhubung ke JualKirim ('.$this->mask($e->getMessage()).')'), null, "{$method} {$path}", null, $e);
        } catch (RequestException $e) {
            // Muncul bila retry habis pada 5xx; kembalikan response agar dipetakan seperti biasa
            return $e->response;
        }
    }

    private function http(string $method, bool $multipart = false): PendingRequest
    {
        $req = Http::baseUrl($this->config['base_url'])
            ->timeout($this->config['timeout'])
            ->acceptJson()
            ->withUserAgent('Krafio-JualKirimClient/1.0');
        // Content-Type JSON hanya untuk body JSON; multipart (upload CSV) memakai boundary dari Guzzle
        $req = $multipart ? $req->asMultipart() : $req->asJson();

        // Retry hanya metode idempoten; POST tidak pernah diulang otomatis (tidak ada idempotency key di spec)
        if (in_array($method, ['GET', 'PUT', 'DELETE'], true) && $this->config['retry']['times'] > 0) {
            $req = $req->retry(
                $this->config['retry']['times'] + 1,
                $this->config['retry']['sleep_ms'],
                fn (Throwable $e) => $e instanceof ConnectionException || ($e instanceof RequestException && $e->response->serverError()),
                throw: false,
            );
        }

        return $req;
    }

    private function withAuth(PendingRequest $req, string $auth): PendingRequest
    {
        if ($auth === self::AUTH_NONE) {
            return $req;
        }
        $req = $req->withToken($this->getToken());
        if ($auth !== self::AUTH_BEARER || $this->config['api_key']) {
            $key = (string) ($this->config['api_key'] ?? '');
            if ($key === '' && $auth === self::AUTH_BOTH) {
                throw new JualKirimAuthException('JUALKIRIM_API_KEY belum diatur di .env.', null);
            }
            if ($key !== '') {
                $req = $req->withHeaders(['X-API-KEY' => $key]);
            }
        }

        return $req;
    }

    public function hasCredentials(): bool
    {
        return ! empty($this->config['email']) && ! empty($this->config['password']) && ! empty($this->config['api_key']);
    }

    public function configuredApiKey(): ?string
    {
        return $this->config['api_key'] ?: null;
    }

    /* ================= Error ================= */

    private function toException(string $method, string $path, Response $response): JualKirimException
    {
        $status = $response->status();
        $json = $response->json();
        $apiMessage = is_array($json) && isset($json['message']) && is_string($json['message']) ? $json['message'] : '';
        $data = is_array($json) && is_array($json['data'] ?? null) ? $json['data'] : null;
        $endpoint = "{$method} {$path}";
        $this->logFailure($method, $path, $status, $apiMessage, $status === 400 || $status === 422 ? $data : null);
        $msg = JualKirimException::describe($method, $path, $status, $apiMessage);

        return match (true) {
            $status === 401 => new JualKirimAuthException($path === '/api/v1/auth/login'
                ? "Login JualKirim ditolak: {$apiMessage}. Periksa JUALKIRIM_EMAIL/JUALKIRIM_PASSWORD dan pastikan email akun sudah diverifikasi."
                : "{$msg}. Token ditolak meski sudah login ulang — periksa JUALKIRIM_API_KEY (harus milik salah satu organisasi akun ini).", $status, $endpoint, $data),
            $status === 403 => new JualKirimForbiddenException("{$msg}. Role akun JualKirim ini tidak memiliki permission untuk aksi tersebut.", $status, $endpoint, $data),
            $status === 400 || $status === 422 => new JualKirimValidationException($msg, $status, $endpoint, $data),
            default => new JualKirimException($msg, $status, $endpoint, $data),
        };
    }

    /**
     * Log request gagal ke channel `jualkirim`. Tidak pernah memuat password, JWT, atau API key:
     * header dan body request tidak ditulis sama sekali; pesan dimask sebagai lapis kedua.
     *
     * @param  array<string, mixed>|null  $data
     */
    private function logFailure(string $method, string $path, ?int $status, string $message, ?array $data = null): void
    {
        Log::channel('jualkirim')->warning('JualKirim request gagal', array_filter([
            'method' => $method,
            'path' => $path,
            'status' => $status,
            'message' => $this->mask($message),
            'errors' => $data ? json_decode($this->mask((string) json_encode($data)), true) : null,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    /** Samarkan JWT, API key terkonfigurasi, dan password dari teks bebas. */
    public function mask(string $text): string
    {
        $text = (string) preg_replace('/eyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+/', '[jwt]', $text);
        foreach (['api_key' => '[api-key]', 'password' => '[password]'] as $k => $label) {
            $v = (string) ($this->config[$k] ?? '');
            if (strlen($v) >= 4) {
                $text = str_replace($v, $label, $text);
            }
        }

        return $text;
    }

    public static function maskKey(?string $key): string
    {
        if (! $key) {
            return '(kosong)';
        }

        return substr($key, 0, 4).str_repeat('•', max(0, strlen($key) - 7)).substr($key, -3);
    }

    /** Encode id path (UUID) agar tidak bisa menyisipkan path lain. */
    public static function id(string $id): string
    {
        return rawurlencode($id);
    }
}
