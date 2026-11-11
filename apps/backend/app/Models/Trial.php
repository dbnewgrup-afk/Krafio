<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trial extends Model
{
    protected $table = 'trials';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['ends_at' => 'datetime'];
    }
}
