<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    protected $table = 'wallet_ledger';

    protected $guarded = ['id'];

    public const UPDATED_AT = null;

    public function user() { return $this->belongsTo(User::class); }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
