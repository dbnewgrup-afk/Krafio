<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $table = 'coupons';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['plan_codes' => 'array', 'cycles' => 'array', 'applies_to_addons' => 'boolean', 'applies_to_custom' => 'boolean', 'first_purchase_only' => 'boolean', 'auto_apply' => 'boolean', 'is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }
}
