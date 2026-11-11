<?php

namespace App\Facades;

use App\Services\JualKirim\JualKirimClient;
use Illuminate\Support\Facades\Facade;

/**
 * Akses JualKirim API v1. Contoh: JualKirim::orders()->list(['status' => 'PENDING']).
 *
 * @method static \App\Services\JualKirim\Data\MeData me()
 * @method static string getToken()
 * @method static void forgetToken()
 * @method static \App\Services\JualKirim\Data\LoginResult login()
 * @method static \App\Services\JualKirim\Resources\AuthResource auth()
 * @method static \App\Services\JualKirim\Resources\AddressResource addresses()
 * @method static \App\Services\JualKirim\Resources\WalletResource wallet()
 * @method static \App\Services\JualKirim\Resources\CourierResource couriers()
 * @method static \App\Services\JualKirim\Resources\ShippingResource shipping()
 * @method static \App\Services\JualKirim\Resources\OrderResource orders()
 * @method static \App\Services\JualKirim\Resources\ProductResource products()
 * @method static \App\Services\JualKirim\Resources\CategoryResource categories()
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
