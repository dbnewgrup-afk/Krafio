<?php

namespace App\Services\JualKirim\Resources;

/**
 * Tag Address — alamat pickup organisasi.
 *
 * @phpstan-type Address array{id:string,name:string,responsible_name?:string,responsible_phone?:string,email:string,postal_code?:string,postal_code_id?:int|string,address?:string,latitude?:string,longitude?:string,is_default?:bool}
 * @phpstan-type SaveAddressRequest array{name:string,email:string,responsible_name?:string,responsible_phone?:string,postal_code?:string,postal_code_id?:int|string,address?:string,latitude?:string,longitude?:string,is_default?:bool}
 */
class AddressResource extends Resource
{
    /**
     * GET /api/v1/addresses (apiListAddresses)
     *
     * @return array{message:string,data:list<Address>}
     */
    public function list(): array
    {
        return $this->client->request('GET', '/api/v1/addresses');
    }

    /**
     * POST /api/v1/addresses (apiCreateAddress) — alamat pertama otomatis default;
     * juga didaftarkan sebagai pickup point di AutoKirim.
     *
     * @param  SaveAddressRequest  $data
     * @return array{message:string,data:Address}
     */
    public function create(array $data): array
    {
        return $this->client->request('POST', '/api/v1/addresses', ['json' => $data]);
    }

    /**
     * PUT /api/v1/addresses/{id} (apiUpdateAddress)
     *
     * @param  SaveAddressRequest  $data
     * @return array{message:string,data:Address}
     */
    public function update(string $id, array $data): array
    {
        return $this->client->request('PUT', '/api/v1/addresses/'.self::id($id), ['json' => $data]);
    }

    /**
     * DELETE /api/v1/addresses/{id} (apiDeleteAddress) — soft delete.
     *
     * @return array{message:string,data:null}
     */
    public function delete(string $id): array
    {
        return $this->client->request('DELETE', '/api/v1/addresses/'.self::id($id));
    }
}
