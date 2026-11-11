<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Upload extends Model
{
    protected $table = 'uploads';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [

        ];
    }
}
