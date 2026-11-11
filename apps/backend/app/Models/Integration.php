<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Integration extends Model
{
    protected $table = 'integrations';

    protected $guarded = ['id'];

    protected $hidden = ['config'];

    protected function casts(): array
    {
        return ['config' => 'encrypted:array', 'is_active' => 'boolean', 'last_tested_at' => 'datetime'];
    }
}
