<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketNote extends Model
{
    protected $table = 'ticket_notes';

    protected $guarded = ['id'];

    public function author() { return $this->belongsTo(User::class, 'author_id'); }
}
