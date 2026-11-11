<?php

namespace App\Services\JualKirim\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Error umum JualKirim API (status selain 401/403/400/422, gagal koneksi, response bukan JSON).
 * Response API memakai format { "message": string, "data": mixed|null }.
 */
class JualKirimException extends RuntimeException
{
    /**
     * @param  array<string, mixed>|null  $responseData  isi field "data" dari response (tanpa data rahasia)
     */
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $endpoint = null,
        public readonly ?array $responseData = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status ?? 0, $previous);
    }

    public static function describe(string $method, string $path, ?int $status, string $apiMessage): string
    {
        return trim(sprintf('JualKirim %s %s gagal%s: %s', $method, $path, $status ? " (HTTP {$status})" : '', $apiMessage !== '' ? $apiMessage : 'tanpa pesan dari server'));
    }
}
