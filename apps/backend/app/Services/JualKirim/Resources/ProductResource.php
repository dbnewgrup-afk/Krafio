<?php

namespace App\Services\JualKirim\Resources;

/** Tag Product. Spec tidak merinci schema produk (type: object). */
class ProductResource extends Resource
{
    /**
     * GET /api/v1/products (apiListProducts)
     *
     * @return array{message:string,data:list<array<string,mixed>>}
     */
    public function list(?string $search = null): array
    {
        return $this->client->request('GET', '/api/v1/products', ['query' => self::query(['search' => $search])]);
    }

    /**
     * POST /api/v1/products (apiCreateProduct) — `organization_id` diambil dari konteks X-API-KEY
     * (nilai di payload diabaikan). 422 mis. SKU duplikat.
     *
     * @param  array<string, mixed>  $data  field produk; spec tidak mendefinisikan schema-nya
     * @return array{message:string,data:array<string,mixed>}
     */
    public function create(array $data): array
    {
        return $this->client->request('POST', '/api/v1/products', ['json' => $data]);
    }
}
