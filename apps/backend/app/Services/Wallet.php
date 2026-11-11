<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * Ledger keuangan append-only (PRD 5.1, 11.4, 31.1). Dua dompet terpisah: `topup` (tidak dapat ditarik)
 * dan `commission` (dapat ditarik). Saldo dihitung dari ledger; setiap mutasi dikunci per user (lockForUpdate)
 * agar tidak terjadi race condition. Admin tidak boleh mengubah saldo tanpa entri ledger + alasan.
 */
class Wallet
{
    public const WALLETS = ['topup', 'commission'];

    public static function balance(int $userId, string $wallet): int
    {
        $last = LedgerEntry::where('user_id', $userId)->where('wallet', $wallet)->orderByDesc('id')->value('balance_after');

        return (int) ($last ?? 0);
    }

    public static function reserved(int $userId): int
    {
        return (int) Withdrawal::where('user_id', $userId)->whereIn('status', ['requested', 'under_review', 'approved', 'processing'])->sum('amount');
    }

    /** @param callable|null $inside dijalankan di dalam transaksi yang sama setelah mutasi */
    public static function post(int $userId, string $wallet, string $direction, int $amount, string $description, array $ref = [], ?string $reason = null, ?int $actorId = null, ?callable $inside = null): LedgerEntry
    {
        if (! in_array($wallet, self::WALLETS, true) || ! in_array($direction, ['credit', 'debit'], true) || $amount <= 0) {
            throw new ApiException('invalid_ledger_entry', 'Entri ledger tidak valid.', 422);
        }

        return DB::transaction(function () use ($userId, $wallet, $direction, $amount, $description, $ref, $reason, $actorId, $inside) {
            User::whereKey($userId)->lockForUpdate()->first();
            $current = self::balance($userId, $wallet);
            $next = $direction === 'credit' ? $current + $amount : $current - $amount;
            if ($next < 0) {
                throw new ApiException('insufficient_balance', 'Saldo tidak mencukupi.', 422);
            }
            $entry = LedgerEntry::create([
                'user_id' => $userId,
                'wallet' => $wallet,
                'direction' => $direction,
                'amount' => $amount,
                'balance_after' => $next,
                'ref_type' => $ref[0] ?? null,
                'ref_id' => $ref[1] ?? null,
                'description' => $description,
                'actor_id' => $actorId,
                'reason' => $reason,
            ]);
            if ($inside) {
                $inside($entry);
            }

            return $entry;
        });
    }
}
