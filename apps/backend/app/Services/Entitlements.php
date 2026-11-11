<?php

namespace App\Services;

use App\Models\UsageCounter;
use App\Models\User;
use App\Support\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * Entitlement terpusat (PRD 4.5) dengan satuan kuota hybrid (PRD 55.1, BR-026):
 * - Fitur media berat → Kredit Krafio (pool bersama, lihat Credits).
 * - Fitur teks → limit pesan/request (chatbot per hari, brainstorming & audit SEO per bulan).
 * - Fitur struktural → limit jumlah (website, proyek, akun sosial, storage) pada `limits` paket.
 * Validasi WAJIB di backend sebelum job/tindakan berbayar dijalankan.
 */
class Entitlements
{
    public const KEYS = [
        'photo.generate', 'video.generate', 'photo.edit', 'video.edit', 'content.brainstorm', 'templates.premium',
        'website.builder', 'seo.basic', 'seo.advanced', 'social.integrations', 'chatbot.basic', 'chatbot.premium',
        'video.clipper', 'exports.download',
    ];

    public const CREDIT_KEYS = ['photo.generate', 'photo.edit', 'video.generate', 'video.edit', 'video.clipper'];

    /** Limit teks: kunci → periode reset */
    public const TEXT_KEYS = ['chatbot.basic' => 'day', 'chatbot.premium' => 'day', 'content.brainstorm' => 'month', 'seo.basic' => 'month', 'seo.advanced' => 'month'];

    public static function period(string $unit = 'month'): string
    {
        return $unit === 'day' ? now()->format('Y-m-d') : now()->format('Y-m');
    }

    private static function resetAt(string $unit): string
    {
        return ($unit === 'day' ? now()->addDay()->startOfDay() : now()->startOfMonth()->addMonth())->toIso8601String();
    }

    /** Fitur & kuota gabungan dari langganan aktif (termasuk trial) + add-on aktif. */
    public static function grants(User $user): array
    {
        $sub = $user->activeSubscription();
        $features = $sub ? ($sub->snapshot['features'] ?? []) : [];
        $quotas = $sub ? ($sub->snapshot['quotas'] ?? []) : [];
        $limits = $sub ? ($sub->snapshot['limits'] ?? []) : [];
        foreach ($user->addons()->where('status', 'active')->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->get() as $ad) {
            foreach (($ad->snapshot['grants'] ?? []) as $key => $grant) {
                if (str_starts_with($key, 'limits.')) {
                    $lk = substr($key, 7);
                    $limits[$lk] = ($limits[$lk] ?? 0) + (int) $grant;

                    continue;
                }
                if ($key === 'credits') {
                    continue; // kredit add-on berupa pack (Credits::grant)
                }
                if (! in_array($key, $features, true)) {
                    $features[] = $key;
                }
                if (is_numeric($grant)) {
                    $quotas[$key] = ($quotas[$key] ?? 0) + (int) $grant;
                }
            }
        }

        return ['features' => $features, 'quotas' => $quotas, 'limits' => $limits, 'subscription' => $sub];
    }

    public static function limit(User $user, string $key, int $default = 0): int
    {
        return (int) (self::grants($user)['limits'][$key] ?? $default);
    }

    /** @return array<string, array{key:string, enabled:bool, unit:string, quota:?int, used:int, remaining:?int, reset_at:?string}> */
    public static function forUser(User $user): array
    {
        $g = self::grants($user);
        $credits = Credits::summary($user);
        $usedM = UsageCounter::where('user_id', $user->id)->where('period', self::period('month'))->pluck('used', 'entitlement');
        $usedD = UsageCounter::where('user_id', $user->id)->where('period', self::period('day'))->pluck('used', 'entitlement');

        $out = ['credits' => [
            'key' => 'credits', 'enabled' => true, 'unit' => 'credit', 'quota' => $credits['total'], 'used' => $credits['used'],
            'remaining' => $credits['remaining'], 'reset_at' => $credits['reset_at'],
        ]];
        foreach (self::KEYS as $key) {
            $enabled = in_array($key, $g['features'], true);
            if (in_array($key, self::CREDIT_KEYS, true)) {
                $out[$key] = ['key' => $key, 'enabled' => $enabled, 'unit' => 'credit', 'quota' => null, 'used' => 0, 'remaining' => $enabled ? $credits['remaining'] : null, 'reset_at' => $enabled ? $credits['reset_at'] : null];

                continue;
            }
            $unit = self::TEXT_KEYS[$key] ?? null;
            $quota = $enabled && $unit && isset($g['quotas'][$key]) ? (int) $g['quotas'][$key] : null;
            $used = $unit ? (int) (($unit === 'day' ? $usedD : $usedM)[$key] ?? 0) : 0;
            $out[$key] = [
                'key' => $key, 'enabled' => $enabled, 'unit' => $unit ?? 'access', 'quota' => $quota, 'used' => $used,
                'remaining' => $quota !== null ? max(0, $quota - $used) : null, 'reset_at' => $quota !== null ? self::resetAt($unit) : null,
            ];
        }

        return $out;
    }

    /** Pastikan fitur aktif & kuota/kredit cukup; lempar error berkode bila tidak. */
    public static function ensure(User $user, string $key, int $amount = 1): array
    {
        $all = self::forUser($user);
        $e = $all[$key] ?? null;
        if (! $e || ! $e['enabled']) {
            $latest = $user->latestSubscription();
            if ($latest && $latest->ends_at && $latest->ends_at->isPast()) {
                throw new ApiException('subscription_expired', 'Langganan Anda telah berakhir.', 403, ['feature' => $key]);
            }
            throw new ApiException('feature_locked', 'Fitur ini belum termasuk paket Anda.', 403, ['feature' => $key]);
        }
        if ($amount > 0 && $e['remaining'] !== null && $e['remaining'] < $amount) {
            throw new ApiException('quota_exceeded', $e['unit'] === 'credit' ? 'Kredit tidak mencukupi.' : 'Kuota fitur ini sudah habis.', 403, ['feature' => $e['unit'] === 'credit' ? 'credits' : $key]);
        }

        return $e;
    }

    /**
     * Batas jumlah struktural (PRD 55.2: proyek, website, akun sosial, storage) dari paket + add-on.
     * Item yang sudah ada saat downgrade tidak dihapus (BR-025); hanya penambahan baru yang ditolak.
     */
    public static function ensureCount(User $user, string $limitKey, int $current, int $adding, string $feature, string $label): void
    {
        $max = self::limit($user, $limitKey, 0);
        if ($current + $adding > $max) {
            $msg = $max <= 0
                ? "{$label} belum termasuk paket Anda."
                : "Batas {$label} pada paket Anda sudah tercapai (maks. {$max}). Upgrade paket atau beli add-on untuk menambah.";
            throw new ApiException($max <= 0 ? 'feature_locked' : 'quota_exceeded', $msg, 403, ['feature' => $feature, 'limit' => $limitKey, 'max' => $max]);
        }
    }

    /** Konsumsi limit teks secara atomik (kredit media memakai Credits::consume). */
    public static function consume(User $user, string $key, int $amount): void
    {
        $unit = self::TEXT_KEYS[$key] ?? null;
        if ($amount <= 0 || ! $unit) {
            return;
        }
        DB::transaction(function () use ($user, $key, $amount, $unit) {
            $period = self::period($unit);
            $row = UsageCounter::where(['user_id' => $user->id, 'entitlement' => $key, 'period' => $period])->lockForUpdate()->first()
                ?? UsageCounter::create(['user_id' => $user->id, 'entitlement' => $key, 'period' => $period, 'used' => 0]);
            $quota = self::grants($user)['quotas'][$key] ?? null;
            if ($quota !== null && $row->used + $amount > (int) $quota) {
                throw new ApiException('quota_exceeded', 'Kuota fitur ini sudah habis.', 403, ['feature' => $key]);
            }
            $row->increment('used', $amount);
        });
    }

    public static function refund(User $user, string $key, int $amount): void
    {
        $unit = self::TEXT_KEYS[$key] ?? null;
        if ($amount <= 0 || ! $unit) {
            return;
        }
        UsageCounter::where(['user_id' => $user->id, 'entitlement' => $key, 'period' => self::period($unit)])->where('used', '>=', $amount)->decrement('used', $amount);
    }
}
