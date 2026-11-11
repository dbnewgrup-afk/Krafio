<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketMessage extends Model
{
    protected $table = 'ticket_messages';

    protected $guarded = ['id'];

    public function user() { return $this->belongsTo(User::class); }

    protected function casts(): array
    {
        return [

        ];
    }
}
