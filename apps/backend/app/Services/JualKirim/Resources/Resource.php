<?php

namespace App\Services\JualKirim\Resources;

use App\Services\JualKirim\JualKirimClient;

/**
 * Basis resource per tag OpenAPI. Semua response mengikuti amplop
 * `{ "message": string, "data": mixed }` kecuali dicatat lain di method.
 *
 * @phpstan-type Pagination array{page:int,per_page:int,total:int,total_filtered:int}
 * @phpstan-type MessageResponse array{message:string,data:null}
 */
abstract class Resource
{
    public function __construct(protected JualKirimClient $client) {}

    /** Buang filter kosong agar tidak terkirim sebagai `?status=` dsb. */
    protected static function query(array $q): array
    {
        return array_filter($q, fn ($v) => $v !== null && $v !== '');
    }

    protected static function id(string $id): string
    {
        return JualKirimClient::id($id);
    }
}
