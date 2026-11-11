<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralClick extends Model
{
    protected $table = 'referral_clicks';

    protected $guarded = ['id'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
