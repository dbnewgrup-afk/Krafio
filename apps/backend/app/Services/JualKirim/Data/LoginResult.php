<?php

namespace App\Services\JualKirim\Data;

use App\Services\JualKirim\Exceptions\JualKirimException;
use Carbon\CarbonImmutable;

/** Schema `LoginResponse.data`: token (JWT HS256, 24 jam), expires_at, organizations[]. */
final class LoginResult
{
    /** @param  list<Organization>  $organizations */
    public function __construct(
        public readonly string $token,
        public readonly ?CarbonImmutable $expiresAt,
        public readonly array $organizations,
    ) {}

    /** @param  array{message?:string,data?:array{token?:string,expires_at?:string,organizations?:list<array<string,string>>}}  $body */
    public static function fromArray(array $body): self
    {
        $d = $body['data'] ?? [];
        $token = (string) ($d['token'] ?? '');
        if ($token === '') {
            throw new JualKirimException('Login JualKirim berhasil tetapi response tidak memuat token.', 200, 'POST /api/v1/auth/login');
        }
        $exp = null;
        if (! empty($d['expires_at'])) {
            try {
                $exp = CarbonImmutable::parse($d['expires_at']);
            } catch (\Throwable) {
                $exp = null;
            }
        }

        return new self($token, $exp, array_map(fn ($o) => Organization::fromArray((array) $o), $d['organizations'] ?? []));
    }

    public function __debugInfo(): array
    {
        // Jangan pernah membocorkan token lewat dump/log
        return ['token' => '[jwt]', 'expiresAt' => $this->expiresAt?->toIso8601String(), 'organizations' => count($this->organizations)];
    }
}
