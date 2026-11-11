<?php

namespace App\Services;

use App\Models\Addon;
use App\Models\Commission;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\CustomPackage;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Trial;
use App\Models\User;
use App\Models\UserAddon;
use App\Notifications\Notice;
use App\Support\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Aktivasi paket/add-on, harga akhir, kupon, prorata, dan pembayaran.
 * PRD 4.3, 4.4, 5.3–5.6 dan Bab 55 (kredit, kupon & promo, perpanjangan, upgrade/downgrade).
 */
class Billing
{
    /** Data item yang dibeli + snapshot harga & entitlement (PRD 4.3 butir 7). */
    public static function resolve(User $u, string $kind, string $code, ?string $cycle): array
    {
        if ($kind === 'plan') {
            $p = Plan::where('code', $code)->where('is_active', true)->firstOrFail();
            if ($p->code === 'enterprise' || ($p->limits['custom'] ?? false)) {
                throw new ApiException('custom_plan', 'Paket Enterprise melalui pengajuan Custom Package.', 422);
            }
            $cycle = $cycle === 'yearly' ? 'yearly' : 'monthly';
            $price = (int) ($cycle === 'yearly' ? $p->price_yearly : $p->price_monthly);

            return ['kind' => 'plan', 'code' => $code, 'name' => $p->name_id, 'price' => $price, 'cycle' => $cycle, 'snapshot' => ['features' => $p->features, 'quotas' => $p->quotas, 'limits' => $p->limits, 'price' => $price, 'cycle' => $cycle]];
        }
        if ($kind === 'addon') {
            $a = Addon::where('code', $code)->where('is_active', true)->firstOrFail();
            $sub = $u->activeSubscription();
            // Konflik add-on (PRD 4.3 butir 2 & 4; 55.3 "minimal Starter"/"khusus Starter")
            if ($a->plan_codes && (! $sub || ! in_array($sub->plan_code, $a->plan_codes, true))) {
                throw new ApiException('addon_conflict', 'Add-on ini hanya untuk paket: '.implode(', ', $a->plan_codes).'.', 422);
            }

            return ['kind' => 'addon', 'code' => $code, 'name' => $a->name_id, 'price' => (int) $a->price, 'cycle' => null, 'snapshot' => ['grants' => $a->grants, 'duration_days' => $a->duration_days, 'billing' => $a->billing, 'price' => (int) $a->price]];
        }
        $c = CustomPackage::where('code', $code)->where('status', 'published')->where(fn ($q) => $q->whereNull('offered_to')->orWhere('offered_to', $u->id))->firstOrFail();

        return ['kind' => 'custom', 'code' => $code, 'name' => $c->name, 'price' => (int) $c->price, 'cycle' => null, 'snapshot' => ['features' => $c->features, 'quotas' => $c->quotas, 'limits' => $c->limits ?? [], 'duration_days' => $c->duration_days, 'price' => (int) $c->price]];
    }

    /** Nilai sisa paket lama, prorata harian (55.5.3). */
    public static function prorataCredit(User $u, array $item): int
    {
        if ($item['kind'] !== 'plan') {
            return 0;
        }
        $cur = $u->activeSubscription();
        if (! $cur || $cur->plan_code === $item['code'] || $cur->source === 'trial' || ! $cur->ends_at || $cur->price <= 0) {
            return 0;
        }
        $total = max(1, $cur->starts_at->diffInDays($cur->ends_at));
        $left = max(0, now()->diffInDays($cur->ends_at, false));

        return (int) floor($cur->price * $left / $total);
    }

    public static function isUpgrade(User $u, array $item): bool
    {
        $cur = $u->activeSubscription();
        if (! $cur || $item['kind'] !== 'plan' || $cur->plan_code === $item['code']) {
            return false;
        }
        $curPlan = Plan::where('code', $cur->plan_code)->first();
        $new = Plan::where('code', $item['code'])->first();

        return ! $curPlan || ($new && $new->sort > $curPlan->sort);
    }

    /** Validasi kupon (55.4.2–55.4.3). */
    public static function couponDiscount(User $u, Coupon $c, array $item, int $base): int
    {
        $now = now();
        $ok = $c->is_active && (! $c->starts_at || $c->starts_at <= $now) && (! $c->ends_at || $c->ends_at > $now)
            && ($c->max_uses === null || $c->used + $c->reserved < $c->max_uses)
            && $base >= (int) $c->min_purchase;
        if ($ok && $item['kind'] === 'plan' && $c->plan_codes && ! in_array($item['code'], $c->plan_codes, true)) {
            $ok = false;
        }
        if ($ok && $item['kind'] === 'plan' && $c->cycles && ! in_array($item['cycle'], $c->cycles, true)) {
            $ok = false;
        }
        if ($ok && $item['kind'] === 'addon' && ! $c->applies_to_addons) {
            $ok = false;
        }
        if ($ok && $item['kind'] === 'custom' && ! $c->applies_to_custom) {
            $ok = false;
        }
        if ($ok && $c->first_purchase_only && Payment::where('user_id', $u->id)->where('status', 'paid')->where('purpose', '!=', 'topup')->exists()) {
            $ok = false;
        }
        if ($ok && CouponRedemption::where('coupon_id', $c->id)->where('user_id', $u->id)->whereIn('status', ['reserved', 'used'])->count() >= max(1, (int) $c->max_uses_per_user)) {
            $ok = false;
        }
        if (! $ok) {
            return -1;
        }

        return match ($c->type) {
            'percent' => min((int) floor($base * $c->value / 100), $c->max_discount ? (int) $c->max_discount : PHP_INT_MAX),
            'fixed' => min($base, (int) $c->value),
            default => 0, // bonus_credit / bonus_days tidak mengurangi harga
        };
    }

    /**
     * Rincian harga akhir: harga normal − prorata − diskon terbaik (1 kupon ATAU promo otomatis; 55.4.3 butir 1).
     * Kupon tidak berlaku untuk top-up (BR-030).
     */
    public static function quote(User $u, array $item, ?string $code = null): array
    {
        $base = $item['price'];
        $prorata = self::isUpgrade($u, $item) ? min($base, self::prorataCredit($u, $item)) : 0;
        $after = $base - $prorata;
        $best = null;
        $bestDiscount = 0;
        $codeError = null;
        if ($code) {
            $c = Coupon::where('code', strtoupper(trim($code)))->first();
            $d = $c ? self::couponDiscount($u, $c, $item, $after) : -1;
            if ($d < 0) {
                $codeError = 'Kode promo tidak berlaku untuk pembelian ini.';
            } else {
                [$best, $bestDiscount] = [$c, $d];
            }
        }
        // Kode yang diketik pengguna & valid diutamakan (tidak ditumpuk); promo otomatis hanya bila tanpa kode valid
        foreach ($best ? [] : Coupon::where('auto_apply', true)->where('is_active', true)->get() as $auto) {
            if (! self::autoEligible($u, $auto, $item)) {
                continue;
            }
            $d = self::couponDiscount($u, $auto, $item, $after);
            if ($d > $bestDiscount) {
                [$best, $bestDiscount] = [$auto, $d];
            }
        }
        $final = max(0, $after - $bestDiscount);
        $ppnRate = (float) Settings::get('billing.ppn_percent', 11);
        $inclusive = (bool) Settings::get('billing.price_includes_ppn', true);

        return [
            'normal' => $base, 'prorata' => $prorata, 'discount' => $bestDiscount, 'final' => $final,
            'coupon' => $best ? ['id' => $best->id, 'code' => $best->auto_apply ? null : $best->code, 'name' => $best->name ?? $best->code, 'type' => $best->type, 'value' => (int) $best->value, 'auto' => (bool) $best->auto_apply] : null,
            'coupon_error' => $codeError,
            'ppn' => $inclusive ? (int) round($final - $final / (1 + $ppnRate / 100)) : (int) round($final * $ppnRate / 100),
            'ppn_inclusive' => $inclusive,
            'is_upgrade' => self::isUpgrade($u, $item),
        ];
    }

    private static function autoEligible(User $u, Coupon $c, array $item): bool
    {
        return match ($c->campaign) {
            'referral' => (bool) $u->referred_by && ! Payment::where('user_id', $u->id)->where('status', 'paid')->where('purpose', 'subscription')->exists(),
            'winback' => ($last = $u->latestSubscription()) && $last->ends_at && $last->ends_at->lt(now()->subDays(14)) && ! $u->activeSubscription(),
            'upgrade' => self::isUpgrade($u, $item),
            default => true, // early_bird, flash_sale, annual
        };
    }

    public static function reserveCoupon(User $u, ?array $coupon, int $discount, ?int $paymentId): ?CouponRedemption
    {
        if (! $coupon) {
            return null;
        }

        return DB::transaction(function () use ($u, $coupon, $discount, $paymentId) {
            $c = Coupon::whereKey($coupon['id'])->lockForUpdate()->first();
            if ($c->max_uses !== null && $c->used + $c->reserved >= $c->max_uses) {
                throw new ApiException('coupon_exhausted', 'Kuota kode promo sudah habis.', 422);
            }
            $c->increment('reserved');

            return CouponRedemption::create(['coupon_id' => $c->id, 'user_id' => $u->id, 'payment_id' => $paymentId, 'status' => 'reserved', 'discount' => $discount]);
        });
    }

    public static function finalizeCoupon(?CouponRedemption $r, bool $success): void
    {
        if (! $r || $r->status !== 'reserved') {
            return;
        }
        DB::transaction(function () use ($r, $success) {
            $c = Coupon::whereKey($r->coupon_id)->lockForUpdate()->first();
            $c->decrement('reserved');
            if ($success) {
                $c->increment('used');
            }
            $r->update(['status' => $success ? 'used' : 'released']);
        });
    }

    /** Aktivasi setelah pembayaran sah. `$paid` = nominal yang benar-benar dibayar (BR-032). */
    public static function activate(User $u, array $item, int $paid, ?int $paymentId = null, ?array $coupon = null): void
    {
        if ($item['kind'] === 'addon') {
            $grants = $item['snapshot']['grants'] ?? [];
            $days = $item['snapshot']['duration_days'] ?? null;
            $ends = $days ? now()->addDays($days) : null;
            if (isset($grants['credits'])) {
                // Add-on kredit: berlaku 90 hari, dipakai setelah kredit paket (BR-027)
                Credits::grant($u, 'addon', (int) $grants['credits'], $ends, 'Add-on '.$item['name'], 'addon', $paymentId);
            }
            if (array_diff(array_keys($grants), ['credits'])) {
                UserAddon::create(['user_id' => $u->id, 'addon_code' => $item['code'], 'name' => $item['name'], 'snapshot' => $item['snapshot'], 'status' => 'active', 'ends_at' => $ends]);
            }
            $u->notify(new Notice('Add-on aktif', $item['name'].' sudah dapat dipakai.', 'subscription', '/app/addons'));
        } else {
            $cur = $u->activeSubscription();
            $days = $item['kind'] === 'custom' ? ($item['snapshot']['duration_days'] ?? 30) : ($item['cycle'] === 'yearly' ? 365 : 30);
            $start = now();
            if ($cur && $cur->plan_code === $item['code'] && $cur->ends_at && $cur->ends_at->isFuture()) {
                $start = $cur->ends_at->copy(); // perpanjangan melanjutkan masa aktif
            } elseif ($cur) {
                $cur->update(['status' => 'cancelled', 'ends_at' => now()]); // upgrade berlaku langsung (55.5.3)
            }
            $promo = null;
            if ($coupon && ($c = Coupon::find($coupon['id'])) && $c->duration !== 'once') {
                $promo = ['coupon_id' => $c->id, 'type' => $c->type, 'value' => (int) $c->value, 'max_discount' => $c->max_discount, 'duration' => $c->duration, 'months_left' => $c->duration === 'first_n_months' ? max(0, (int) $c->duration_months - 1) : null];
            }
            Subscription::create([
                'user_id' => $u->id, 'source' => $item['kind'] === 'custom' ? 'custom' : 'plan', 'plan_code' => $item['code'], 'plan_name' => $item['name'],
                'cycle' => $item['cycle'] ?? 'custom', 'price' => $paid, 'snapshot' => $item['snapshot'] + ['normal_price' => $item['price']], 'status' => 'active',
                'starts_at' => $start, 'ends_at' => $start->copy()->addDays($days), 'promo' => $promo,
            ]);
            Messenger::notify($u, 'subscription_active', ['plan' => $item['name'], 'ends_at' => $start->copy()->addDays($days)->format('d-m-Y')], 'Paket aktif', 'Paket '.$item['name'].' aktif.', '/app/subscription');
        }
        if ($coupon && in_array($coupon['type'], ['bonus_credit', 'bonus_days'], true)) {
            if ($coupon['type'] === 'bonus_credit') {
                Credits::grant($u, 'bonus', (int) $coupon['value'], now()->addDays(90), 'Bonus kredit promo', 'coupon', $coupon['id']);
            } elseif ($s = $u->activeSubscription()) {
                $s->update(['ends_at' => $s->ends_at?->copy()->addDays((int) $coupon['value'])]);
            }
        }
        self::commission($u, $paid, $paymentId);
    }

    /** Komisi dari nominal yang dibayar setelah diskon (BR-032); bukan dari top-up. */
    public static function commission(User $u, int $amount, ?int $paymentId): void
    {
        if (! $u->referred_by || $u->referred_by === $u->id || $amount <= 0 || ! Settings::get('affiliate.active')) {
            return;
        }
        $value = (int) floor($amount * (float) Settings::get('affiliate.rate_percent') / 100);
        if ($value > 0) {
            Commission::create(['referrer_id' => $u->referred_by, 'referred_id' => $u->id, 'payment_id' => $paymentId, 'base_amount' => $amount, 'amount' => $value, 'status' => 'pending', 'available_at' => now()->addDays((int) Settings::get('affiliate.hold_days'))]);
        }
    }

    public static function releaseCommissions(?int $referrerId = null): void
    {
        $q = Commission::whereIn('status', ['pending', 'approved'])->where('available_at', '<=', now());
        if ($referrerId) {
            $q->where('referrer_id', $referrerId);
        }
        foreach ($q->get() as $c) {
            DB::transaction(function () use ($c) {
                $fresh = Commission::whereKey($c->id)->lockForUpdate()->first();
                if (! in_array($fresh->status, ['pending', 'approved'], true)) {
                    return;
                }
                Wallet::post($fresh->referrer_id, 'commission', 'credit', $fresh->amount, 'Komisi affiliate #'.$fresh->id, ['commission', $fresh->id]);
                $fresh->update(['status' => 'available']);
            });
        }
    }

    public static function newPayment(User $u, string $purpose, ?string $code, ?string $cycle, int $amount, ?string $method, ?string $idem, ?array $pricing = null): Payment
    {
        if ($idem && ($p = Payment::where('idempotency_key', $idem)->where('user_id', $u->id)->first())) {
            return $p;
        }

        return Payment::create([
            'user_id' => $u->id, 'reference' => 'KRF'.now()->format('ymd').strtoupper(Str::random(6)), 'purpose' => $purpose, 'purpose_code' => $code,
            'cycle' => $cycle, 'amount' => $amount, 'method' => $method, 'gateway' => (string) Settings::get('payment.gateway'), 'status' => 'pending',
            'idempotency_key' => $idem, 'expires_at' => now()->addDay(), 'pricing' => $pricing, 'coupon_id' => $pricing['coupon']['id'] ?? null,
        ]);
    }

    /** Dipanggil webhook terverifikasi / simulasi lokal. Idempoten (webhook duplikat tidak memproses ulang). */
    public static function markPaid(int $paymentId, array $payload = []): void
    {
        DB::transaction(function () use ($paymentId, $payload) {
            $p = Payment::whereKey($paymentId)->lockForUpdate()->first();
            if (! $p || $p->status !== 'pending') {
                return;
            }
            $redemption = CouponRedemption::where('payment_id', $p->id)->where('status', 'reserved')->first();
            if ($p->expires_at && $p->expires_at->isPast()) {
                $p->update(['status' => 'expired']);
                self::finalizeCoupon($redemption, false); // BR-031: lepas reservasi kupon

                return;
            }
            $p->update(['status' => 'paid', 'paid_at' => now(), 'gateway_payload' => $payload ?: $p->gateway_payload]);
            $u = User::find($p->user_id);
            if ($p->purpose === 'topup') {
                Wallet::post($u->id, 'topup', 'credit', (int) $p->amount, 'Top-up '.$p->reference, ['payment', $p->id]);
                Messenger::notify($u, 'topup_success', ['amount' => 'Rp'.number_format($p->amount, 0, ',', '.'), 'reference' => $p->reference], 'Top-up berhasil', 'Saldo top-up bertambah Rp'.number_format($p->amount, 0, ',', '.').'.', '/app/wallet');
            } else {
                $kind = $p->purpose === 'subscription' ? 'plan' : $p->purpose;
                $item = self::resolve($u, $kind, $p->purpose_code, $p->cycle);
                self::activate($u, $item, (int) $p->amount, $p->id, $p->pricing['coupon'] ?? null);
                self::finalizeCoupon($redemption, true);
            }
            Audit::log('payment.paid', $p, ['amount' => $p->amount], $u->id);
        });
    }

    public static function releaseExpiredReservations(): void
    {
        foreach (Payment::where('status', 'pending')->where('expires_at', '<', now())->get() as $p) {
            $p->update(['status' => 'expired']);
            self::finalizeCoupon(CouponRedemption::where('payment_id', $p->id)->where('status', 'reserved')->first(), false);
        }
    }

    /** Trial Pro 7 hari: 100 kredit, sekali per nomor HP (BR-033), tanpa auto-charge (55.4.1). */
    public static function startTrial(User $u): void
    {
        $plan = Plan::where('code', (string) Settings::get('trial.plan_code', 'pro'))->firstOrFail();
        if (! $u->phone) {
            throw new ApiException('phone_required', 'Tambahkan nomor WhatsApp di profil untuk memulai trial.', 422);
        }
        $hash = hash('sha256', $u->phone);
        if ($u->trial_used_at || Trial::where('phone_hash', $hash)->exists()) {
            throw new ApiException('trial_used', 'Trial hanya dapat dipakai sekali per nomor.', 422);
        }
        $cur = $u->activeSubscription();
        if ($cur && $cur->price > 0) {
            throw new ApiException('trial_not_eligible', 'Trial hanya untuk pengguna tanpa paket berbayar aktif.', 422);
        }
        $days = (int) Settings::get('trial.days', 7);
        DB::transaction(function () use ($u, $plan, $hash, $cur, $days) {
            $cur?->update(['status' => 'cancelled', 'ends_at' => now()]);
            $snap = ['features' => $plan->features, 'quotas' => array_merge($plan->quotas, ['credits' => 0]), 'limits' => $plan->limits, 'price' => 0, 'trial' => true];
            Subscription::create(['user_id' => $u->id, 'source' => 'trial', 'plan_code' => $plan->code, 'plan_name' => $plan->name_id.' (Trial)', 'cycle' => 'trial', 'price' => 0, 'snapshot' => $snap, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addDays($days)]);
            Credits::grant($u, 'trial', (int) Settings::get('trial.credits', 100), now()->addDays($days), 'Kredit trial '.$plan->name_id);
            Trial::create(['user_id' => $u->id, 'phone_hash' => $hash, 'plan_code' => $plan->code, 'ends_at' => now()->addDays($days)]);
            $u->forceFill(['trial_used_at' => now()])->save();
        });
        $u->notify(new Notice('Trial dimulai', 'Trial '.$plan->name_id.' aktif '.$days.' hari dengan '.Settings::get('trial.credits', 100).' kredit.', 'subscription', '/app/subscription'));
    }

    /**
     * Scheduler harian: pengingat H-7/H-3/H-1, perpanjangan otomatis dari saldo (past_due bila gagal),
     * downgrade terjadwal, dan kembali ke paket Gratis setelah grace period (55.5).
     */
    public static function renewals(): void
    {
        foreach (Subscription::where('status', 'active')->whereNotNull('ends_at')->where('ends_at', '<=', now()->addDays(7))->where('source', '!=', 'trial')->get() as $s) {
            $u = $s->user;
            if (! $u || $s->price <= 0) {
                continue;
            }
            $days = (int) ceil(now()->diffInHours($s->ends_at, false) / 24);
            foreach ([7 => 1, 3 => 2, 1 => 3] as $d => $flag) {
                if ($days <= $d && $days > 0 && $s->reminders_sent < $flag) {
                    $u->notify(new Notice('Paket segera berakhir', 'Paket '.$s->plan_name.' berakhir dalam '.$days.' hari.', 'subscription', '/app/subscription'));
                    $s->update(['reminders_sent' => $flag]);
                }
            }
            if ($s->ends_at->isPast() || $days <= 0) {
                $targetCode = $s->scheduled_plan_code ?: $s->plan_code;
                $cycle = $s->scheduled_cycle ?: $s->cycle;
                if (! $u->auto_renew && ! $s->scheduled_plan_code) {
                    continue;
                }
                try {
                    $item = self::resolve($u, 'plan', $targetCode, $cycle);
                    $price = $item['price'];
                    if ($s->promo && ($s->promo['duration'] === 'forever' || ($s->promo['months_left'] ?? 0) > 0)) {
                        $disc = $s->promo['type'] === 'percent' ? min((int) floor($price * $s->promo['value'] / 100), $s->promo['max_discount'] ?: PHP_INT_MAX) : ($s->promo['type'] === 'fixed' ? min($price, $s->promo['value']) : 0);
                        $price -= $disc;
                    }
                    DB::transaction(function () use ($u, $item, $price, $s) {
                        if ($price > 0) {
                            Wallet::post($u->id, 'topup', 'debit', $price, 'Perpanjangan otomatis '.$item['name'], ['renewal', $s->id]);
                        }
                        $s->update(['status' => 'expired']);
                        self::activate($u, $item, $price);
                        if ($s->promo && $s->promo['duration'] === 'first_n_months') {
                            $u->activeSubscription()?->update(['promo' => array_merge($s->promo, ['months_left' => max(0, ($s->promo['months_left'] ?? 0) - 1)])]);
                        }
                    });
                } catch (ApiException $e) {
                    $s->update(['status' => 'past_due']);
                    $u->notify(new Notice('Perpanjangan gagal', 'Saldo top-up tidak cukup untuk memperpanjang '.$s->plan_name.'.', 'transaction', '/app/wallet/topup'));
                }
            }
        }
        // Setelah grace period: kembali ke paket Gratis (data & aset tetap tersimpan — PRD 4.4)
        $grace = (int) Settings::get('subscription.grace_days', 3);
        foreach (Subscription::whereIn('status', ['active', 'past_due'])->whereNotNull('ends_at')->where('ends_at', '<', now()->subDays($grace))->get() as $s) {
            $s->update(['status' => 'expired']);
            if ($s->user && ! $s->user->activeSubscription()) {
                \App\Http\Controllers\Api\AuthController::grantFreePlan($s->user, true);
            }
        }
    }
}
