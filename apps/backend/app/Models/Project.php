<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    protected $table = 'projects';

    protected $guarded = ['id'];

    use SoftDeletes;

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function jobs()
    {
        return $this->hasMany(AiJob::class);
    }

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
