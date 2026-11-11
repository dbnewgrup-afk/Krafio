<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\User;

/**
 * Etalase promo: kupon yang ditandai admin "tampil publik" untuk halaman Promo & beranda,
 * serta daftar promo yang benar-benar berlaku untuk item yang sedang di-checkout (memakai validasi kupon yang sama).
 */
class Promos
{
    /** Bentuk publik — tanpa angka pemakaian internal; kode tidak dikirim untuk promo otomatis. */
    public static function present(Coupon $c): array
    {
        $remaining = $c->max_uses !== null ? max(0, (int) $c->max_uses - (int) $c->used - (int) $c->reserved) : null;

        return [
            'id' => $c->id,
            'code' => $c->auto_apply ? null : $c->code,
            'auto' => (bool) $c->auto_apply,
            'headline' => $c->headline ?: ($c->name ?: $c->code),
            'description' => $c->description,
            'image' => $c->image,
            'type' => $c->type,
            'value' => (int) $c->value,
            'max_discount' => $c->max_discount ? (int) $c->max_discount : null,
            'min_purchase' => (int) $c->min_purchase,
            'plan_codes' => $c->plan_codes ?: [],
            'cycles' => $c->cycles ?: [],
            'applies_to_addons' => (bool) $c->applies_to_addons,
            'first_purchase_only' => (bool) $c->first_purchase_only,
            'duration' => $c->duration,
            'duration_months' => $c->duration_months,
            'campaign' => $c->campaign,
            'ends_at' => $c->ends_at?->toIso8601String(),
            // Hanya ditampilkan bila sudah menipis (mendorong urgensi tanpa membocorkan angka total)
            'remaining' => $remaining !== null && $remaining <= 20 ? $remaining : null,
        ];
    }

    /** @return array<int, array> */
    public static function publicList(int $limit = 24): array
    {
        return Coupon::query()->running()->where('is_public', true)
            ->orderBy('sort')->orderByRaw('ends_at IS NULL')->orderBy('ends_at')->orderByDesc('id')
            ->limit($limit)->get()->map(fn ($c) => self::present($c))->all();
    }

    /**
     * Promo publik yang lolos validasi untuk user & item ini, diurutkan dari hemat terbesar.
     *
     * @return array<int, array>
     */
    public static function forItem(User $u, array $item): array
    {
        if ($item['kind'] === 'custom') {
            return [];
        }
        $base = $item['price'] - (Billing::isUpgrade($u, $item) ? min($item['price'], Billing::prorataCredit($u, $item)) : 0);
        $out = [];
        foreach (Coupon::query()->running()->where('is_public', true)->where('auto_apply', false)->orderBy('sort')->get() as $c) {
            $d = Billing::couponDiscount($u, $c, $item, $base);
            if ($d < 0) {
                continue;
            }
            $out[] = self::present($c) + ['discount' => $d];
        }
        usort($out, fn ($a, $b) => $b['discount'] <=> $a['discount']);

        return $out;
    }
}
