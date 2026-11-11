<?php

namespace App\Support;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Error API dengan kode mesin (Design Spec §5.2: server mengembalikan kode error, klien memetakan ke teks
 * terlokalisasi). Tidak pernah memuat stack trace, SQL, atau secret (§17.4).
 */
class ApiException extends Exception
{
    public function __construct(
        public string $errorCode,
        string $message = '',
        public int $status = 400,
        public array $extra = [],
        public array $fields = [],
    ) {
        parent::__construct($message ?: $errorCode);
    }

    public function render(): JsonResponse
    {
        return response()->json(array_merge([
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
            'errors' => (object) $this->fields,
        ], $this->extra), $this->status);
    }
}
