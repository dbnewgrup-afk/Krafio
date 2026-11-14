<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForumPost extends Model
{
    protected $guarded = ['id'];

    public function thread() { return $this->belongsTo(ForumThread::class, 'thread_id'); }

    public function user() { return $this->belongsTo(User::class); }

    protected function casts(): array
    {
        return ['edited_at' => 'datetime'];
    }
}
