<?php

namespace App\Services\JualKirim\Resources;

use InvalidArgumentException;

/**
 * Tag Disbursement — kirim saldo wallet ke banyak rekening sekaligus.
 *
 * @phpstan-type DisbursementItemInput array{bank_code:'BCA'|'BNI'|'BRI'|'MANDIRI'|'CIMB'|'SEABANK'|'UOB'|'OCBC',account_number:string,account_holder:string,amount:int,row?:int,email?:string,phone?:string,note?:string}
 * @phpstan-type ValidationSummary array{total_items:int,valid_items:int,invalid_items:int,total_amount:int|float,fee_per_item:int|float,total_fee:int|float,total_deduct:int|float,balance:int|float,balance_after:int|float,sufficient:bool}
 * @phpstan-type ValidationResult array{items:list<array<string,mixed>>,summary:ValidationSummary,errors:list<string>}
 * @phpstan-type DisbursementBatch array{id:string,code:string,source:string,status:'scheduled'|'processing'|'success'|'partial'|'failed'|'cancelled',status_label:string,scheduled_at:?string,executed_at:?string,completed_at:?string,total_items:int,total_amount:int|float,total_fee:int|float,total_deduct:int|float,total_refunded:int|float,counts:array{total:int,success:int,failed:int,processing:int},cancellable:bool,created_at:string}
 */
class DisbursementResource extends Resource
{
    /**
     * POST /api/v1/disbursement-batches/validate (apiValidateDisbursementBatch) — JSON.
     * Hanya memeriksa rekening & menghitung total terhadap saldo; TIDAK memotong saldo.
     * Permission: `multi-disbursement-create`.
     *
     * @param  list<DisbursementItemInput>  $items  maks 100
     * @return array{message:string,data:ValidationResult}
     */
    public function validate(array $items): array
    {
        self::assertBatchSize($items);

        return $this->client->request('POST', '/api/v1/disbursement-batches/validate', ['json' => ['items' => array_values($items)]]);
    }

    /**
     * POST /api/v1/disbursement-batches/validate (apiValidateDisbursementBatch) — upload CSV
     * (multipart, field `file`). Header wajib: bank_code,account_number,account_holder,amount,email,phone,note;
     * maks 100 baris, maks 1 MB. TIDAK memotong saldo.
     *
     * @return array{message:string,data:ValidationResult}
     */
    public function validateCsv(string $csvContents, string $filename = 'disbursement.csv'): array
    {
        if (strlen($csvContents) > 1024 * 1024) {
            throw new InvalidArgumentException('File CSV disbursement maksimal 1 MB.');
        }

        return $this->client->request('POST', '/api/v1/disbursement-batches/validate', ['multipart' => [['name' => 'file', 'contents' => $csvContents, 'filename' => $filename]]]);
    }

    /**
     * GET /api/v1/disbursement-batches (apiListDisbursementBatches) — permission `multi-disbursement-read`.
     *
     * @param  array{page?:int,per_page?:int,search?:string,status?:string,start_date?:string,end_date?:string}  $filters
     * @return array{message:string,data:array{batches:list<DisbursementBatch>,pagination:array{page:int,per_page:int,total:int,total_filtered:int}}}
     */
    public function list(array $filters = []): array
    {
        return $this->client->request('GET', '/api/v1/disbursement-batches', ['query' => self::query($filters)]);
    }

    /**
     * POST /api/v1/disbursement-batches (apiCreateDisbursementBatch)
     *
     * ⚠ PEMINDAH UANG: saldo wallet LANGSUNG dipotong total_amount + total_fee, termasuk untuk batch
     * terjadwal. Permission `multi-disbursement-create`. Spec tidak menyediakan idempotency key →
     * TIDAK di-retry otomatis; bila hasil tidak pasti, cek list() dulu sebelum mengirim ulang.
     * Sebaiknya panggil validate() lebih dulu dan pastikan summary.sufficient & invalid_items = 0.
     * Terkunci kecuali JUALKIRIM_MONEY_ENABLED=true.
     *
     * @param  list<DisbursementItemInput>  $items  1–100 rekening
     * @param  string|null  $scheduledAt  kosong = kirim sekarang; RFC3339 atau YYYY-MM-DDTHH:mm (WIB), min 5 menit, maks 90 hari
     * @param  'manual'|'csv'|'saved'  $source
     * @return array{message:string,data:DisbursementBatch}
     */
    public function create(array $items, ?string $scheduledAt = null, string $source = 'manual'): array
    {
        self::assertBatchSize($items);

        return $this->client->request('POST', '/api/v1/disbursement-batches', ['json' => self::query(['source' => $source, 'scheduled_at' => $scheduledAt]) + ['items' => array_values($items)]], money: true);
    }

    /**
     * GET /api/v1/disbursement-batches/{id} (apiDisbursementBatchDetail) — status tiap rekening.
     * Permission `multi-disbursement-read`.
     *
     * @return array{message:string,data:array<string,mixed>}
     */
    public function find(string $id): array
    {
        return $this->client->request('GET', '/api/v1/disbursement-batches/'.self::id($id));
    }

    /**
     * POST /api/v1/disbursement-batches/{id}/cancel (apiCancelDisbursementBatch) — hanya status `scheduled`;
     * seluruh saldo dikembalikan. Permission `multi-disbursement-cancel`.
     * Menggerakkan saldo (refund) sehingga ikut dikunci JUALKIRIM_MONEY_ENABLED; tidak di-retry otomatis.
     *
     * @return array{message:string,data:null}
     */
    public function cancel(string $id): array
    {
        return $this->client->request('POST', '/api/v1/disbursement-batches/'.self::id($id).'/cancel', [], money: true);
    }

    private static function assertBatchSize(array $items): void
    {
        if (count($items) < 1 || count($items) > 100) {
            throw new InvalidArgumentException('Batch disbursement berisi 1–100 rekening.');
        }
    }
}
