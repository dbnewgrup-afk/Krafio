<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $table = 'media';

    protected $guarded = ['id'];

    public function url(): string
    {
        return '/api/media/'.$this->id.'/'.rawurlencode(basename($this->path));
    }
}
