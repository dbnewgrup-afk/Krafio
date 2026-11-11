<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserConsent extends Model
{
    protected $table = 'user_consents';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'agreed_at' => 'datetime',
        ];
    }
}
