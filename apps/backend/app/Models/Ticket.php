<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $table = 'tickets';

    protected $guarded = ['id'];

    public function user() { return $this->belongsTo(User::class); }
    public function messages() { return $this->hasMany(TicketMessage::class); }

    protected function casts(): array
    {
        return [

        ];
    }
}
