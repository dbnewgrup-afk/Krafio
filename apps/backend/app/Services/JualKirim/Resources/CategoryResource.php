<?php

namespace App\Services\JualKirim\Resources;

/** Tag Category — kategori & subkategori barang. Spec tidak merinci schema item (type: object). */
class CategoryResource extends Resource
{
    /**
     * GET /api/v1/categories (apiListCategories)
     *
     * @param  int|null  $limit  default 50, maks 200
     * @return array{message:string,data:list<array<string,mixed>>}
     */
    public function list(?string $search = null, ?int $limit = null): array
    {
        return $this->client->request('GET', '/api/v1/categories', ['query' => self::query(['search' => $search, 'limit' => $limit])]);
    }

    /**
     * GET /api/v1/subcategories (apiListSubcategories)
     *
     * @param  array{search?:string,category_code?:string,code?:string,limit?:int}  $filters
     * @return array{message:string,data:list<array<string,mixed>>}
     */
    public function subcategories(array $filters = []): array
    {
        return $this->client->request('GET', '/api/v1/subcategories', ['query' => self::query($filters)]);
    }
}
