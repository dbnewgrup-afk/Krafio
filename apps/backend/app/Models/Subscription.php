<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $table = 'subscriptions';

    protected $guarded = ['id'];

    public function user() { return $this->belongsTo(User::class); }

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'promo' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
