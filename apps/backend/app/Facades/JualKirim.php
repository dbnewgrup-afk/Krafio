<?php

namespace App\Facades;

use App\Services\JualKirim\JualKirimClient;
use Illuminate\Support\Facades\Facade;

/**
 * Akses JualKirim API v1. Contoh: JualKirim::invoices()->find($id).
 *
 * @method static \App\Services\JualKirim\Data\MeData me()
 * @method static string getToken()
 * @method static void forgetToken()
 * @method static \App\Services\JualKirim\Data\LoginResult login()
 * @method static \App\Services\JualKirim\Resources\AuthResource auth()
 * @method static \App\Services\JualKirim\Resources\WalletResource wallet()
 * @method static \App\Services\JualKirim\Resources\InvoiceResource invoices()
 * @method static \App\Services\JualKirim\Resources\PaymentResource payments()
 * @method static \App\Services\JualKirim\Resources\DisbursementResource disbursements()
 *
 * @see JualKirimClient
 */
class JualKirim extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return JualKirimClient::class;
    }
}
