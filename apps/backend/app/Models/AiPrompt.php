<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiPrompt extends Model
{
    protected $table = 'ai_prompts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [

        ];
    }
}
