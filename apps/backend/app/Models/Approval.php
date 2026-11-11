<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    protected $table = 'approvals';

    protected $guarded = ['id'];

    public function user() { return $this->belongsTo(User::class); }
    public function decider() { return $this->belongsTo(User::class, 'decided_by'); }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'decided_at' => 'datetime',
        ];
    }
}
