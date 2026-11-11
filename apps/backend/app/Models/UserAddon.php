<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAddon extends Model
{
    protected $table = 'user_addons';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'ends_at' => 'datetime',
        ];
    }
}
