<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Audit;
use App\Services\Billing;
use App\Services\Entitlements;
use App\Services\Messenger;
use App\Support\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin mengatur paket/fitur akun pengguna tanpa pembelian: memberi paket katalog atau
 * racikan fitur custom (sumber langganan `admin`, harga 0). Akun admin adalah pengelola
 * dan tidak pernah memiliki paket/fitur.
 */
class UserFeaturesController extends Controller
{
    /** Kuota yang bisa diatur: kredit bulanan + limit fitur teks. */
    public const QUOTA_KEYS = ['credits', 'chatbot.basic', 'chatbot.premium', 'content.brainstorm', 'seo.basic', 'seo.advanced'];

    /** Limit struktural yang ditegakkan backend (Entitlements::ensureCount). */
    public const LIMIT_KEYS = ['projects', 'websites', 'social_accounts', 'storage_mb'];

    private function guardTarget(User $user): void
    {
        if ($user->isAdmin()) {
            throw new ApiException('admin_no_features', 'Akun admin adalah pengelola sehingga tidak memiliki paket maupun fitur.', 422);
        }
    }

    public function show(User $user)
    {
        $this->guardTarget($user);
        $g = Entitlements::grants($user);
        $sub = $g['subscription'];

        return ['data' => [
            'current' => $sub ? [
                'id' => $sub->id, 'source' => $sub->source, 'plan_code' => $sub->plan_code, 'plan_name' => $sub->plan_name, 'price' => (int) $sub->price,
                'starts_at' => $sub->starts_at?->toIso8601String(), 'ends_at' => $sub->ends_at?->toIso8601String(),
                'granted_by_admin' => $sub->source === 'admin',
            ] : null,
            'features' => array_values($g['features']),
            'quotas' => (object) $g['quotas'],
            'limits' => (object) $g['limits'],
            'upcoming' => Billing::upcomingPeriods($user)->count(),
            'catalog' => [
                'feature_keys' => Entitlements::KEYS,
                'quota_keys' => self::QUOTA_KEYS,
                'limit_keys' => self::LIMIT_KEYS,
                'plans' => Plan::where('is_active', true)->orderBy('sort')->get()
                    ->reject(fn ($p) => $p->limits['custom'] ?? false)
                    ->map(fn ($p) => ['code' => $p->code, 'name' => $p->name_id, 'features' => $p->features ?? [], 'quotas' => (object) ($p->quotas ?? []), 'limits' => (object) ($p->limits ?? [])])
                    ->values(),
            ],
        ]];
    }

    public function store(Request $r, User $user)
    {
        $this->guardTarget($user);
        $d = $r->validate([
            'mode' => 'required|in:plan,custom',
            'plan_code' => 'required_if:mode,plan|nullable|string|exists:plans,code',
            'name' => 'required_if:mode,custom|nullable|string|min:2|max:80',
            'base_plan' => 'nullable|string|exists:plans,code',
            'features' => 'required_if:mode,custom|array',
            'features.*' => 'string|in:'.implode(',', Entitlements::KEYS),
            'quotas' => 'nullable|array',
            'quotas.*' => 'integer|min:0|max:100000000',
            'limits' => 'nullable|array',
            'days' => 'nullable|integer|min:1|max:3650',
            'reason' => 'required|string|min:5|max:500',
        ]);

        if ($d['mode'] === 'plan') {
            $p = Plan::where('code', $d['plan_code'])->where('is_active', true)->first();
            if (! $p || ($p->limits['custom'] ?? false)) {
                throw new ApiException('validation_failed', 'Paket ini tidak bisa diberikan langsung. Gunakan mode custom.', 422, [], ['plan_code' => ['Paket tidak valid.']]);
            }
            $code = $p->code;
            $name = $p->name_id;
            $snapshot = ['features' => $p->features ?? [], 'quotas' => $p->quotas ?? [], 'limits' => $p->limits ?? []];
        } else {
            $quotas = array_intersect_key(array_map('intval', $d['quotas'] ?? []), array_flip(self::QUOTA_KEYS));
            // Limit lain (resolusi, watermark, dll.) diambil dari paket dasar; limit struktural boleh ditimpa admin.
            $base = ! empty($d['base_plan']) ? (Plan::where('code', $d['base_plan'])->first()?->limits ?? []) : [];
            unset($base['custom']);
            $limits = $base;
            foreach ($d['limits'] ?? [] as $k => $v) {
                if (! is_string($k) || ! preg_match('/^[a-z_]{1,40}$/', $k) || ! (is_scalar($v) || $v === null)) {
                    throw new ApiException('validation_failed', 'Format limit tidak valid.', 422, [], ['limits' => ["Limit {$k} tidak valid."]]);
                }
                if (in_array($k, self::LIMIT_KEYS, true) && (! is_numeric($v) || (int) $v < 0)) {
                    throw new ApiException('validation_failed', 'Limit harus angka ≥ 0.', 422, [], ["limits.{$k}" => ['Harus angka ≥ 0.']]);
                }
                $limits[$k] = in_array($k, self::LIMIT_KEYS, true) ? (int) $v : $v;
            }
            $code = 'admin-custom';
            $name = $d['name'];
            $snapshot = ['features' => array_values(array_unique($d['features'])), 'quotas' => $quotas, 'limits' => $limits];
        }

        $days = $d['days'] ?? null;
        $sub = DB::transaction(function () use ($user, $code, $name, $snapshot, $days, $d, $r) {
            $cancelled = [];
            // Paket pemberian admin menggantikan langganan berjalan & periode yang sudah diperpanjang di muka
            foreach (Subscription::where('user_id', $user->id)->whereIn('status', ['active', 'past_due'])->lockForUpdate()->get() as $s) {
                if ($s->ends_at && $s->ends_at->isPast()) {
                    continue;
                }
                $cancelled[] = ['id' => $s->id, 'plan' => $s->plan_name, 'price' => (int) $s->price];
                $s->update(['status' => 'cancelled', 'ends_at' => $s->starts_at && $s->starts_at->isFuture() ? $s->ends_at : now(), 'scheduled_plan_code' => null, 'scheduled_cycle' => null]);
            }
            $sub = Subscription::create([
                'user_id' => $user->id, 'source' => 'admin', 'plan_code' => $code, 'plan_name' => $name, 'cycle' => 'custom', 'price' => 0,
                'snapshot' => $snapshot + ['granted_by' => $r->user()->id, 'reason' => $d['reason'], 'mode' => $d['mode']],
                'status' => 'active', 'starts_at' => now(), 'ends_at' => $days ? now()->addDays($days) : null,
            ]);
            Audit::log('admin.users.features.set', $user, [
                'mode' => $d['mode'], 'plan_code' => $code, 'name' => $name, 'days' => $days, 'reason' => $d['reason'],
                'features' => $snapshot['features'], 'quotas' => $snapshot['quotas'], 'limits' => $snapshot['limits'], 'replaced' => $cancelled,
            ]);

            return $sub;
        });

        Messenger::notify($user, 'subscription_active', ['plan' => $name, 'ends_at' => $sub->ends_at?->format('d-m-Y') ?? '-'], 'Paket diperbarui', 'Admin mengaktifkan '.$name.' untuk akun Anda'.($sub->ends_at ? ' sampai '.$sub->ends_at->format('d-m-Y') : '').'.', '/app/subscription');

        return $this->show($user->fresh());
    }

    /** Cabut paket pemberian admin → kembali ke paket Gratis. */
    public function destroy(Request $r, User $user)
    {
        $this->guardTarget($user);
        $d = $r->validate(['reason' => 'required|string|min:5|max:500']);
        $sub = $user->activeSubscription();
        if (! $sub || $sub->source !== 'admin') {
            throw new ApiException('not_admin_grant', 'Paket aktif bukan pemberian admin sehingga tidak bisa dicabut dari sini.', 422);
        }
        DB::transaction(function () use ($user, $sub, $d) {
            $sub->update(['status' => 'cancelled', 'ends_at' => now()]);
            AuthController::grantFreePlan($user, true);
            Audit::log('admin.users.features.revoke', $user, ['subscription_id' => $sub->id, 'plan' => $sub->plan_name, 'reason' => $d['reason']]);
        });

        return $this->show($user->fresh());
    }
}
