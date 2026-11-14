<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Header Idempotency-Key dari klien → kunci tersimpan per user (sha256 user_id|key, 64 karakter).
 * Kolom idempotency_key bersifat unik global: tanpa ini, key yang sama dari dua user berbeda atau
 * key lebih dari 80 karakter memicu error 500, dan lookup bisa mengembalikan data milik user lain.
 */
class Idempotency
{
    public static function key(Request $r, int $userId): ?string
    {
        $raw = trim((string) $r->header('Idempotency-Key'));

        return $raw === '' ? null : hash('sha256', $userId.'|'.$raw);
    }
}
