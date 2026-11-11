<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CouponRedemption extends Model
{
    protected $table = 'coupon_redemptions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }
}
