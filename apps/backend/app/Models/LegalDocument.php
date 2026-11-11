<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalDocument extends Model
{
    protected $table = 'legal_documents';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sections_id' => 'array',
            'sections_en' => 'array',
            'effective_at' => 'date',
            'requires_reconsent' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
