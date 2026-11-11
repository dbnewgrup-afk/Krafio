<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoAudit extends Model
{
    protected $table = 'seo_audits';

    protected $guarded = ['id'];

    public function findings() { return $this->hasMany(SeoFinding::class, 'audit_id'); }

    protected function casts(): array
    {
        return [

        ];
    }
}
