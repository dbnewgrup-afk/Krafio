<?php

namespace App\Services\JualKirim\Exceptions;

/**
 * Permintaan ditolak karena isinya:
 *  - 400 validasi field (spec: ValidationErrorResponse, `data` = { field: pesan }),
 *  - 422 gagal di aturan bisnis (mis. saldo wallet tidak cukup, SKU duplikat, status tidak bisa diubah);
 *    untuk disbursement `data` berisi hasil validasi per rekening.
 */
class JualKirimValidationException extends JualKirimException
{
    /**
     * Pesan per field (400) — kosong bila error berupa aturan bisnis (422).
     *
     * @return array<string, string>
     */
    public function errors(): array
    {
        $d = $this->responseData ?? [];

        return array_filter($d, fn ($v, $k) => is_string($k) && is_string($v), ARRAY_FILTER_USE_BOTH);
    }
}
