<?php

namespace App\Services\JualKirim\Resources;

use App\Services\JualKirim\JualKirimClient;

/**
 * Tag Shipping — kode pos & cek ongkir.
 *
 * @phpstan-type PostalCode array{id:string,text:string,postal_code:string,province:string,city:string,sub_district:string,urban:string}
 * @phpstan-type CheckCostRequest array{weight:int|float,postal_code_id:string,pickup_address_id?:string,send_postal_id?:string,courier_code?:string,length?:int|float,width?:int|float,height?:int|float}
 * @phpstan-type CourierRate array{courier_code:string,courier_name:string,service_code:string,service_name:string,service_price:int|float,original_price:int|float,service_insurance:float,etd:string,discount_type:string,discount_value:int|float,discount_amount:int|float}
 */
class ShippingResource extends Resource
{
    /**
     * GET /api/v1/shipping/postal-codes (apiSearchPostalCodes) — tanpa autentikasi.
     * PERHATIAN (spec): response memakai key `results`, bukan `data`. Nilai yang dipakai
     * sebagai `postal_code_id` / `send_postal_id` adalah `id` (UUID), bukan `postal_code`.
     * Maksimal 20 hasil, tanpa paging.
     *
     * @return array{success:bool,results:list<PostalCode>}
     */
    public function postalCodes(?string $q = null): array
    {
        return $this->client->request('GET', '/api/v1/shipping/postal-codes', ['query' => self::query(['q' => $q])], JualKirimClient::AUTH_NONE);
    }

    /**
     * POST /api/v1/shipping/check-cost (apiCheckShippingCost) — cek ongkir semua kurir.
     * Asal wajib salah satu: `pickup_address_id` atau `send_postal_id`.
     * Autentikasi opsional: bila kredensial tersedia, header dikirim sehingga diskon organisasi berlaku.
     * Hasil dipakai untuk `courier_code`, `services_type` (= service_code), `services_name`, `shipping_cost` (= service_price).
     *
     * Bukan endpoint pemindah uang dan hanya membaca harga; tetap POST sehingga tidak di-retry otomatis.
     *
     * @param  CheckCostRequest  $data
     * @return array{success:bool,data:list<CourierRate>}
     */
    public function checkCost(array $data, bool $withOrganizationDiscount = true): array
    {
        // postalCodes() mengembalikan id integer, tetapi check-cost menolak id numerik ("Invalid request format").
        // Spec mendefinisikan field ini sebagai string → kirim sebagai string.
        foreach (['postal_code_id', 'send_postal_id', 'pickup_address_id'] as $key) {
            if (isset($data[$key]) && is_int($data[$key])) {
                $data[$key] = (string) $data[$key];
            }
        }

        return $this->client->request('POST', '/api/v1/shipping/check-cost', ['json' => $data], $withOrganizationDiscount ? JualKirimClient::AUTH_OPTIONAL : JualKirimClient::AUTH_NONE);
    }
}
