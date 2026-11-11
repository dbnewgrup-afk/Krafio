<?php

namespace App\Services\JualKirim\Resources;

/**
 * Tag Orders.
 *
 * @phpstan-type OrderItem array{qty:int,product_id?:string,variant_id?:string,product_name?:string,sku?:string,price?:int|float,weight?:int|float,category?:string,subcategory?:string,length?:int|float,width?:int|float,height?:int|float,is_new?:bool,save_to_db?:bool}
 * @phpstan-type SaveOrderRequest array{customer_name:string,customer_phone:string,customer_address:string,postal_code_id:string,postal_code_text:string,payment_method:'COD'|'NON_COD',courier_code:string,services_type:string,services_name:string,customer_landmark?:string,shipping_instruction?:string,use_insurance?:bool,total_price?:string,total_weight?:string,items?:list<OrderItem>,custom_courier_id?:string,shipping_cost?:string,insurance_amount?:string,cod_fee?:string}
 * @phpstan-type SaveCheckoutRequest array{pickup:array{type:'scheduled'|'dropoff',volume:string,is_sameday?:bool,datetime?:string,address_id?:string,address_data?:array<string,mixed>},orders:non-empty-list<SaveOrderRequest>}
 * @phpstan-type OrderListItem array{id:string,order_code:string,transaction_id:string,courier:string,address:string,status:string,created_at:string}
 */
class OrderResource extends Resource
{
    /**
     * POST /api/v1/orders (apiCreateOrders) — checkout pickup + daftar order.
     *
     * ⚠ PEMINDAH UANG: order NON_COD memotong saldo wallet organisasi (422 bila saldo kurang).
     * Spec tidak menyediakan idempotency key / reference ID, jadi request ini TIDAK PERNAH di-retry
     * otomatis. Bila koneksi putus setelah request terkirim, cek dulu lewat list()/search sebelum
     * mengirim ulang agar tidak terjadi order & potongan saldo ganda.
     * Order yang semuanya COD tidak memotong saldo dan tidak terkena kunci JUALKIRIM_MONEY_ENABLED.
     *
     * @param  SaveCheckoutRequest  $data
     * @return array{message:string,data:array{pickup_order_id:string}}
     */
    public function create(array $data): array
    {
        $deductsWallet = collect($data['orders'] ?? [])->contains(fn ($o) => ($o['payment_method'] ?? null) !== 'COD');

        return $this->client->request('POST', '/api/v1/orders', ['json' => $data], money: $deductsWallet);
    }

    /**
     * GET /api/v1/orders (apiListOrders)
     *
     * @param  array{status?:string,start_date?:string,end_date?:string,search?:string,page?:int,per_page?:int,sort_dir?:'asc'|'desc'}  $filters
     * @return array{message:string,data:array{orders:list<OrderListItem>,pagination:array{page:int,per_page:int,total:int,total_filtered:int}}}
     */
    public function list(array $filters = []): array
    {
        return $this->client->request('GET', '/api/v1/orders', ['query' => self::query($filters)]);
    }

    /**
     * GET /api/v1/orders/{id} (apiOrderDetail) — detail order + pickup order.
     *
     * @return array{message:string,data:array{order:array<string,mixed>,status:string,pickup_order:array<string,mixed>}}
     */
    public function find(string $id): array
    {
        return $this->client->request('GET', '/api/v1/orders/'.self::id($id));
    }

    /**
     * POST /api/v1/orders/{id}/cancel (apiCancelOrder)
     * 422 bila order belum punya transaction ID, sudah dibatalkan, atau ditolak kurir.
     *
     * @return array{message:string,data:null}
     */
    public function cancel(string $id, ?string $remark = null): array
    {
        return $this->client->request('POST', '/api/v1/orders/'.self::id($id).'/cancel', ['json' => self::query(['remark' => $remark])]);
    }
}
