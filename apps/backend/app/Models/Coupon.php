<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $table = 'coupons';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['plan_codes' => 'array', 'cycles' => 'array', 'applies_to_addons' => 'boolean', 'applies_to_custom' => 'boolean', 'first_purchase_only' => 'boolean', 'auto_apply' => 'boolean', 'is_active' => 'boolean', 'is_public' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** Kode selalu disimpan kapital agar pencarian kode yang diketik pengguna konsisten. */
    protected function code(): Attribute
    {
        return Attribute::make(set: fn ($v) => is_string($v) ? strtoupper(trim($v)) : $v);
    }

    /** Masih bisa dipakai sekarang (aktif, dalam periode, kuota belum habis). */
    public function scopeRunning($q)
    {
        return $q->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn ($w) => $w->whereNull('max_uses')->orWhereRaw('used + reserved < max_uses'));
    }
}
