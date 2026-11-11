<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Website extends Model
{
    protected $table = 'websites';

    protected $guarded = ['id'];

    public function user() { return $this->belongsTo(User::class); }

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'published_content' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
