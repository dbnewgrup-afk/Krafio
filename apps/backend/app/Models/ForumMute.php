<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForumMute extends Model
{
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['until' => 'datetime'];
    }
}
