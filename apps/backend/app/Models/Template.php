<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    protected $table = 'templates';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'palette' => 'array',
            'structure' => 'array',
            'focus' => 'array',
            'badges' => 'array',
            'is_premium' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }
}
