<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $table = 'tickets';

    protected $guarded = ['id'];

    public function user() { return $this->belongsTo(User::class); }
    public function assignee() { return $this->belongsTo(User::class, 'assignee_id'); }
    public function messages() { return $this->hasMany(TicketMessage::class); }
    public function lastMessage() { return $this->hasOne(TicketMessage::class)->latestOfMany(); }
    public function notes() { return $this->hasMany(TicketNote::class); }
    public function activities() { return $this->hasMany(TicketActivity::class); }

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'last_message_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
