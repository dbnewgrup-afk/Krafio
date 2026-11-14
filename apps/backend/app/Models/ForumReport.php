<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForumReport extends Model
{
    protected $guarded = ['id'];

    public function user() { return $this->belongsTo(User::class); }

    public function thread() { return $this->belongsTo(ForumThread::class, 'thread_id'); }

    public function post() { return $this->belongsTo(ForumPost::class, 'post_id'); }

    public function reportedUser() { return $this->belongsTo(User::class, 'reported_user_id'); }

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }
}
