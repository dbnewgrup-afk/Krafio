<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAgent extends Model
{
    protected $table = 'ai_agents';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [

        ];
    }
}
