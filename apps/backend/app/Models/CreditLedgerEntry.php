<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditLedgerEntry extends Model
{
    protected $table = 'credit_ledger';

    protected $guarded = ['id'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
