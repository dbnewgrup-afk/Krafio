<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiModel extends Model
{
    protected $table = 'ai_models';

    protected $guarded = ['id'];

    public function provider() { return $this->belongsTo(AiProvider::class, 'provider_id'); }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_fallback' => 'boolean',
        ];
    }
}
