<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForumThread extends Model
{
    protected $guarded = ['id'];

    public function category() { return $this->belongsTo(ForumCategory::class, 'category_id'); }

    public function user() { return $this->belongsTo(User::class); }

    public function posts() { return $this->hasMany(ForumPost::class, 'thread_id'); }

    public function lastReplyUser() { return $this->belongsTo(User::class, 'last_reply_user_id'); }

    protected function casts(): array
    {
        return ['is_pinned' => 'boolean', 'is_locked' => 'boolean', 'replies_count' => 'integer', 'last_reply_at' => 'datetime', 'edited_at' => 'datetime'];
    }
}
