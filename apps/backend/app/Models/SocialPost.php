<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialPost extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['platforms' => 'array', 'media' => 'array', 'external' => 'array', 'scheduled_at' => 'datetime'];
    }
}
