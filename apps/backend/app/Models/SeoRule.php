<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoRule extends Model
{
    protected $table = 'seo_rules';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
