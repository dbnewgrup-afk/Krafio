<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    protected $table = 'addons';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'grants' => 'array',
            'plan_codes' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
