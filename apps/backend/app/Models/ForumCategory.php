<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForumCategory extends Model
{
    protected $guarded = ['id'];

    public function threads()
    {
        return $this->hasMany(ForumThread::class, 'category_id');
    }

    protected function casts(): array
    {
        return ['plan_codes' => 'array', 'admin_only_posting' => 'boolean', 'is_active' => 'boolean', 'sort' => 'integer'];
    }
}
