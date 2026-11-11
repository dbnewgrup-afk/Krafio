<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomPackage extends Model
{
    protected $table = 'custom_packages';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'quotas' => 'array',
        ];
    }
}
