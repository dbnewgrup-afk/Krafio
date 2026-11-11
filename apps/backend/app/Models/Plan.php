<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'plans';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'quotas' => 'array',
            'limits' => 'array',
            'is_recommended' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
