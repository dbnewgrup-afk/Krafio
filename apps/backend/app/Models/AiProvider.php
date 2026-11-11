<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProvider extends Model
{
    protected $table = 'ai_providers';

    protected $guarded = ['id'];

    protected $hidden = ['api_key'];
    public function models() { return $this->hasMany(AiModel::class, 'provider_id'); }

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'capabilities' => 'array',
            'key_rotated_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }
}
