<?php

namespace App\Services;

use App\Models\CreditLedgerEntry;
use App\Models\CreditPack;
use App\Models\User;
use App\Support\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * Kredit Krafio (PRD Bab 55.1, 55.3.1).
 * - BR-027: kredit paket dipakai lebih dulu, lalu trial/bonus, lalu add-on (yang paling cepat kedaluwarsa);
 *   kredit paket tidak di-rollover (pack per periode bulanan, kedaluwarsa akhir bulan).
 * - BR-028: ledger kredit terpisah dari ledger Rupiah.
 * - BR-029: paket tahunan → kredit dicairkan per bulan (pack dibuat per periode saat dibutuhkan).
 */
class Credits
{
    public const ORDER = ['plan' => 0, 'trial' => 1, 'bonus' => 2, 'addon' => 3];

    /** Biaya kredit per aksi (55.1.1) — nilai awal; dapat diubah Admin di Settings `credits.costs`. */
    public const DEFAULT_COSTS = [
        'photo_standard' => 8, 'photo_hd' => 15, 'remove_bg' => 2, 'upscale' => 4, 'inpaint' => 8,
        'video_720_per_5s' => 80, 'video_1080_per_5s' => 150, 'clipper_per_min' => 10, 'video_edit_per_min' => 5,
    ];

    public static function costs(): array
    {
        return array_merge(self::DEFAULT_COSTS, (array) Settings::get('credits.costs', []));
    }

    /** Pastikan pack kredit paket untuk periode berjalan tersedia (pencairan bulanan). */
    public static function ensurePlanPack(User $u): void
    {
        $sub = $u->activeSubscription();
        if (! $sub) {
            return;
        }
        $monthly = (int) ($sub->snapshot['quotas']['credits'] ?? 0);
        if ($monthly <= 0) {
            return;
        }
        $period = now()->format('Y-m');
        $exists = CreditPack::where(['user_id' => $u->id, 'source' => 'plan', 'period' => $period, 'subscription_id' => $sub->id])->exists();
        if ($exists) {
            return;
        }
        DB::transaction(function () use ($u, $sub, $monthly, $period) {
            // Upgrade: kredit diganti penuh dengan kredit paket baru (55.5.3) → pack paket lama periode ini ditutup.
            CreditPack::where(['user_id' => $u->id, 'source' => 'plan', 'period' => $period])->where('subscription_id', '!=', $sub->id)->update(['expires_at' => now()]);
            $end = now()->endOfMonth();
            if ($sub->ends_at && $sub->ends_at->lt($end)) {
                $end = $sub->ends_at->copy();
            }
            $p = CreditPack::create(['user_id' => $u->id, 'source' => 'plan', 'period' => $period, 'subscription_id' => $sub->id, 'credits_total' => $monthly, 'label' => 'Kredit paket '.$sub->plan_name.' '.$period, 'expires_at' => $end]);
            CreditLedgerEntry::create(['user_id' => $u->id, 'pack_id' => $p->id, 'direction' => 'credit', 'amount' => $monthly, 'ref_type' => 'subscription', 'ref_id' => $sub->id, 'description' => $p->label]);
        });
    }

    private static function activePacks(int $userId)
    {
        return CreditPack::where('user_id', $userId)
            ->whereColumn('credits_used', '<', 'credits_total')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** @return array{total:int, used:int, remaining:int, plan_remaining:int, extra_remaining:int, reset_at:?string} */
    public static function summary(User $u): array
    {
        self::ensurePlanPack($u);
        $packs = CreditPack::where('user_id', $u->id)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->get();
        $plan = $packs->where('source', 'plan');

        return [
            'total' => (int) $packs->sum('credits_total'),
            'used' => (int) $packs->sum('credits_used'),
            'remaining' => (int) $packs->sum(fn ($p) => $p->credits_total - $p->credits_used),
            'plan_remaining' => (int) $plan->sum(fn ($p) => $p->credits_total - $p->credits_used),
            'extra_remaining' => (int) $packs->where('source', '!=', 'plan')->sum(fn ($p) => $p->credits_total - $p->credits_used),
            'reset_at' => $plan->first()?->expires_at?->toIso8601String(),
        ];
    }

    public static function balance(User $u): int
    {
        return self::summary($u)['remaining'];
    }

    /** Debit atomik sesuai urutan BR-027. @return array<int,int> alokasi pack_id → kredit */
    public static function consume(User $u, int $amount, string $refType, ?int $refId, string $desc): array
    {
        if ($amount <= 0) {
            return [];
        }
        self::ensurePlanPack($u);

        return DB::transaction(function () use ($u, $amount, $refType, $refId, $desc) {
            $packs = self::activePacks($u->id)->lockForUpdate()->get()
                ->sortBy(fn ($p) => sprintf('%d-%s', self::ORDER[$p->source] ?? 9, $p->expires_at?->timestamp ?? PHP_INT_MAX));
            $left = $amount;
            $alloc = [];
            foreach ($packs as $p) {
                if ($left <= 0) {
                    break;
                }
                $take = min($left, $p->credits_total - $p->credits_used);
                if ($take <= 0) {
                    continue;
                }
                $p->increment('credits_used', $take);
                $alloc[$p->id] = $take;
                $left -= $take;
            }
            if ($left > 0) {
                throw new ApiException('quota_exceeded', 'Kredit tidak mencukupi.', 403, ['feature' => 'credits', 'needed' => $amount]);
            }
            foreach ($alloc as $packId => $n) {
                CreditLedgerEntry::create(['user_id' => $u->id, 'pack_id' => $packId, 'direction' => 'debit', 'amount' => $n, 'ref_type' => $refType, 'ref_id' => $refId, 'description' => $desc]);
            }

            return $alloc;
        });
    }

    /** Kembalikan kredit ke pack asal (job gagal karena sistem/provider — BR-017). */
    public static function refund(User $u, array $alloc, string $refType, ?int $refId, string $desc): void
    {
        DB::transaction(function () use ($u, $alloc, $refType, $refId, $desc) {
            foreach ($alloc as $packId => $n) {
                $p = CreditPack::whereKey($packId)->lockForUpdate()->first();
                if (! $p) {
                    continue;
                }
                $p->update(['credits_used' => max(0, $p->credits_used - (int) $n)]);
                CreditLedgerEntry::create(['user_id' => $u->id, 'pack_id' => $p->id, 'direction' => 'credit', 'amount' => (int) $n, 'ref_type' => $refType, 'ref_id' => $refId, 'description' => $desc]);
            }
        });
    }

    public static function grant(User $u, string $source, int $credits, ?\DateTimeInterface $expires, string $label, ?string $refType = null, ?int $refId = null): CreditPack
    {
        $p = CreditPack::create(['user_id' => $u->id, 'source' => $source, 'credits_total' => $credits, 'label' => $label, 'expires_at' => $expires]);
        CreditLedgerEntry::create(['user_id' => $u->id, 'pack_id' => $p->id, 'direction' => 'credit', 'amount' => $credits, 'ref_type' => $refType ?? $source, 'ref_id' => $refId, 'description' => $label]);

        return $p;
    }
}
