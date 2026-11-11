<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityEvent extends Model
{
    protected $table = 'security_events';

    protected $guarded = ['id'];

    public const UPDATED_AT = null;

    public function user() { return $this->belongsTo(User::class); }

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
