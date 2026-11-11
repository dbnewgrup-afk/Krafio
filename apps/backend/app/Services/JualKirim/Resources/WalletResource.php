<?php

namespace App\Services\JualKirim\Resources;

/** Tag Wallet — saldo wallet organisasi (order NON_COD memotong saldo ini). */
class WalletResource extends Resource
{
    /**
     * GET /api/v1/wallet/balance (apiWalletBalance)
     *
     * @return array{message:string,data:array{organization_id:string,balance:int|float}}
     */
    public function balance(): array
    {
        return $this->client->request('GET', '/api/v1/wallet/balance');
    }
}
