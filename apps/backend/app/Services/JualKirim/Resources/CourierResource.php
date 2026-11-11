<?php

namespace App\Services\JualKirim\Resources;

/** Tag Courier — daftar kurir aktif. */
class CourierResource extends Resource
{
    /**
     * GET /api/v1/couriers (apiListCouriers)
     *
     * @return array{message:string,data:list<array{code:string,name:string,logo:string}>}
     */
    public function list(): array
    {
        return $this->client->request('GET', '/api/v1/couriers');
    }
}
