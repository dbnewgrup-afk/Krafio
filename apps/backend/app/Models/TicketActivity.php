<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketActivity extends Model
{
    protected $table = 'ticket_activities';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }
}
