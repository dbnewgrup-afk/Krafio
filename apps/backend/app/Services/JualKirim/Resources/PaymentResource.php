<?php

namespace App\Services\JualKirim\Resources;

/**
 * Tag Payment — pembayaran ke vendor.
 *
 * @phpstan-type SavePaymentRequest array{from_name:string,from_email:string,vendor_id:string,admin_fee_paid_by:'merchant'|'vendor',payment_date:string,due_date:string,items:non-empty-list<array{description:string,qty:int|float,unit?:string,price?:int|float,discount?:int|float,tax_percent?:int|float}>,logo_url?:string,from_description?:string,from_phone?:string,notes?:string,tax_deductions?:list<array{name:string,rate_percent?:int|float}>}
 * @phpstan-type PaymentListItem array{id:string,payment_number:string,vendor_name:string,grand_total:int|float,status:string,status_label:string,settlement_status:string,settlement_status_label:string,payment_date:string,due_date:string,is_editable:bool,is_deletable:bool}
 * @phpstan-type CheckoutResponse array{message:string,data:array{paid:bool,payment_method:string,qr_string:string,payment_code:string,payment_link:string,total_amount:int|float,admin_fee:int|float}}
 */
class PaymentResource extends Resource
{
    /**
     * GET /api/v1/payments (apiListPayments)
     *
     * @param  array{page?:int,per_page?:int,search?:string,status?:'pending'|'processing'|'paid'|'cancelled',start_date?:string,end_date?:string,sort_dir?:'asc'|'desc'}  $filters
     * @return array{message:string,data:array{payments:list<PaymentListItem>,pagination:array{page:int,per_page:int,total:int,total_filtered:int}}}
     */
    public function list(array $filters = []): array
    {
        return $this->client->request('GET', '/api/v1/payments', ['query' => self::query($filters)]);
    }

    /**
     * POST /api/v1/payments (apiCreatePayment) — membuat tagihan pembayaran ke vendor + email ke vendor.
     * Belum memindahkan uang; uang baru bergerak saat checkout(). Tidak di-retry otomatis.
     *
     * @param  SavePaymentRequest  $data
     * @return array{message:string,data:array{id:string,payment_number:string,status:string,grand_total:int|float,merchant_total:int|float}}
     */
    public function create(array $data): array
    {
        return $this->client->request('POST', '/api/v1/payments', ['json' => $data]);
    }

    /**
     * GET /api/v1/payments/{id} (apiPaymentDetail)
     *
     * @return array{message:string,data:array{payment:array<string,mixed>,status_label:string,merchant_total:int|float,settlement_status:array{code:string,label:string}}}
     */
    public function find(string $id): array
    {
        return $this->client->request('GET', '/api/v1/payments/'.self::id($id));
    }

    /**
     * PUT /api/v1/payments/{id} (apiUpdatePayment) — hanya status `pending`.
     *
     * @param  SavePaymentRequest  $data
     * @return array{message:string,data:null}
     */
    public function update(string $id, array $data): array
    {
        return $this->client->request('PUT', '/api/v1/payments/'.self::id($id), ['json' => $data]);
    }

    /**
     * DELETE /api/v1/payments/{id} (apiDeletePayment) — hanya status `pending` atau `cancelled`.
     *
     * @return array{message:string,data:null}
     */
    public function delete(string $id): array
    {
        return $this->client->request('DELETE', '/api/v1/payments/'.self::id($id));
    }

    /**
     * POST /api/v1/payments/{id}/checkout (apiPaymentCheckout) — mulai pembayaran.
     *
     * ⚠ PEMINDAH UANG: metode `wallet` langsung memotong saldo (`paid: true`); metode lain membuat
     * billing VA/QRIS di payment gateway. Butuh permission role `payment-read` (403 bila tidak).
     * Spec tidak menyediakan idempotency key → TIDAK di-retry otomatis. Bila hasil tidak pasti
     * (timeout), panggil resumeCheckout() / status() sebelum mencoba lagi.
     * Terkunci kecuali JUALKIRIM_MONEY_ENABLED=true.
     *
     * @param  string  $paymentMethod  kode dari methods(), mis. QRIS atau wallet
     * @return CheckoutResponse
     */
    public function checkout(string $id, string $paymentMethod): array
    {
        return $this->client->request('POST', '/api/v1/payments/'.self::id($id).'/checkout', ['json' => ['payment_method' => $paymentMethod]], money: true);
    }

    /**
     * GET /api/v1/payments/{id}/checkout (apiPaymentResumeCheckout) — ambil ulang detail billing aktif.
     * 422 bila tidak ada billing aktif.
     *
     * @return CheckoutResponse
     */
    public function resumeCheckout(string $id): array
    {
        return $this->client->request('GET', '/api/v1/payments/'.self::id($id).'/checkout');
    }

    /**
     * GET /api/v1/payments/{id}/status (apiPaymentStatus) — untuk polling.
     *
     * @return array{message:string,data:array{status:string,paid:bool}}
     */
    public function status(string $id): array
    {
        return $this->client->request('GET', '/api/v1/payments/'.self::id($id).'/status');
    }

    /**
     * GET /api/v1/payment-methods (apiListPaymentMethods) — termasuk metode `wallet` + saldo saat ini.
     *
     * @return array{message:string,data:array{methods:list<array<string,mixed>>,wallet_balance:int|float}}
     */
    public function methods(): array
    {
        return $this->client->request('GET', '/api/v1/payment-methods');
    }
}
