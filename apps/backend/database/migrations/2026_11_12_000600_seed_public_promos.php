<?php

use App\Models\Coupon;
use App\Support\PromoShowcase;
use Illuminate\Database\Migrations\Migration;

/**
 * Instalasi yang sudah berjalan ikut mendapat contoh promo publik. Tidak menimpa kupon yang sudah
 * diatur admin: BONUS100 hanya diberi data etalase bila belum punya judul; kupon baru dibuat bila belum ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (PromoShowcase::SEED as $code => $attrs) {
            Coupon::where('code', $code)->whereNull('headline')->update($attrs);
        }
        foreach (PromoShowcase::extraCoupons() as $code => $attrs) {
            Coupon::firstOrCreate(['code' => $code], $attrs);
        }
    }

    public function down(): void
    {
        Coupon::whereIn('code', array_keys(PromoShowcase::extraCoupons()))->where('used', 0)->where('reserved', 0)->delete();
    }
};
