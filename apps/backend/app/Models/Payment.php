<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments';

    protected $guarded = ['id'];

    public function user() { return $this->belongsTo(User::class); }

    protected function casts(): array
    {
        return [
            'gateway_payload' => 'array',
            'pricing' => 'array',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
