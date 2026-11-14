<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoAudit extends Model
{
    protected $table = 'seo_audits';

    protected $guarded = ['id'];

    /** Isi form (termasuk konten penuh) tidak ikut di daftar/ekspor Admin; pemilik membacanya lewat SeoController. */
    protected $hidden = ['input'];

    public function findings() { return $this->hasMany(SeoFinding::class, 'audit_id'); }

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'stats' => 'array',
            'score' => 'integer',
            'readability_score' => 'integer',
        ];
    }
}
