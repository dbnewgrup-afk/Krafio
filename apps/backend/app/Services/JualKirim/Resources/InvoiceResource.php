<?php

namespace App\Services\JualKirim\Resources;

/**
 * Tag Invoice — penagihan ke customer (uang MASUK lewat link pembayaran publik; tidak memotong saldo).
 *
 * @phpstan-type SaveInvoiceItem array{description:string,qty:int|float,unit?:string,price?:int|float,discount?:int|float,tax_percent?:int|float}
 * @phpstan-type SaveTaxDeduction array{name:string,rate_percent?:int|float}
 * @phpstan-type SaveInvoiceRequest array{customer_name:string,customer_phone:string,customer_email:string,admin_fee_paid_by:'merchant'|'customer',invoice_date:string,due_date:string,items:non-empty-list<SaveInvoiceItem>,customer_address?:string,notes?:string,settlement_type?:'bank'|'wallet',bank_account_id?:string,logo_url?:string,tax_deductions?:list<SaveTaxDeduction>}
 * @phpstan-type InvoiceListItem array{id:string,invoice_number:string,customer_name:string,customer_email:string,grand_total:int|float,status:string,status_label:string,settlement_status:string,settlement_status_label:string,invoice_date:string,due_date:string,is_editable:bool,is_deletable:bool}
 */
class InvoiceResource extends Resource
{
    /**
     * GET /api/v1/invoices (apiListInvoices)
     *
     * @param  array{page?:int,per_page?:int,search?:string,status?:'draft'|'sent'|'paid'|'cancelled',start_date?:string,end_date?:string,sort_dir?:'asc'|'desc'}  $filters
     * @return array{message:string,data:array{invoices:list<InvoiceListItem>,pagination:array{page:int,per_page:int,total:int,total_filtered:int}}}
     */
    public function list(array $filters = []): array
    {
        return $this->client->request('GET', '/api/v1/invoices', ['query' => self::query($filters)]);
    }

    /**
     * POST /api/v1/invoices (apiCreateInvoice) — membuat tagihan DAN langsung mengirim email
     * berisi link pembayaran ke customer. Tidak di-retry otomatis agar customer tidak menerima tagihan ganda.
     * `bank_account_id` wajib bila settlement_type=bank (default).
     *
     * @param  SaveInvoiceRequest  $data
     * @return array{message:string,data:array{id:string,invoice_number:string,status:string,grand_total:int|float,payment_link:string}}
     */
    public function create(array $data): array
    {
        return $this->client->request('POST', '/api/v1/invoices', ['json' => $data]);
    }

    /**
     * GET /api/v1/invoices/{id} (apiInvoiceDetail)
     *
     * @return array{message:string,data:array{invoice:array<string,mixed>,status_label:string,customer_total:int|float,payment_link:string,settlement_status:array{code:string,label:string}}}
     */
    public function find(string $id): array
    {
        return $this->client->request('GET', '/api/v1/invoices/'.self::id($id));
    }

    /**
     * PUT /api/v1/invoices/{id} (apiUpdateInvoice) — hanya status `draft`.
     *
     * @param  SaveInvoiceRequest  $data
     * @return array{message:string,data:null}
     */
    public function update(string $id, array $data): array
    {
        return $this->client->request('PUT', '/api/v1/invoices/'.self::id($id), ['json' => $data]);
    }

    /**
     * DELETE /api/v1/invoices/{id} (apiDeleteInvoice) — hanya status `draft` atau `cancelled`.
     *
     * @return array{message:string,data:null}
     */
    public function delete(string $id): array
    {
        return $this->client->request('DELETE', '/api/v1/invoices/'.self::id($id));
    }
}
