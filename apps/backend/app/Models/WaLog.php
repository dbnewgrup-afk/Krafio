<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaLog extends Model
{
    protected $table = 'wa_logs';

    protected $guarded = ['id'];

    public const UPDATED_AT = null;
}
