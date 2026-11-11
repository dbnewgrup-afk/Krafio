<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditPack extends Model
{
    protected $table = 'credit_packs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }
}
