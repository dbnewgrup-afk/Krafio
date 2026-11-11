<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoFinding extends Model
{
    protected $table = 'seo_findings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [

        ];
    }
}
