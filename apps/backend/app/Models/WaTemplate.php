<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaTemplate extends Model
{
    protected $table = 'wa_templates';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['variables' => 'array', 'is_active' => 'boolean'];
    }
}
