<?php

namespace App\Support;

use App\Models\AiJob;
use App\Models\LegalDocument;
use App\Models\Plan;
use App\Models\Template;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\Entitlements;
use App\Services\Wallet;

/** Bentuk respons API yang dipakai frontend (api-client/types.ts). Field per bahasa dengan fallback ke `id`. */
class Present
{
    public static function lang(): string
    {
        $l = substr((string) request()->header('Accept-Language', 'id'), 0, 2);

        return $l === 'en' ? 'en' : 'id';
    }

    public static function tr(object|array $m, string $field): ?string
    {
        $m = (object) $m;
        $en = $m->{$field.'_en'} ?? null;

        return self::lang() === 'en' && $en ? $en : ($m->{$field.'_id'} ?? null);
    }

    public static function me(User $u): array
    {
        $sub = $u->activeSubscription() ?? $u->latestSubscription();
        $status = null;
        if ($sub) {
            $status = $sub->ends_at && $sub->ends_at->isPast() ? 'expired' : $sub->status;
        }

        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'role' => $u->role,
            'status' => $u->status,
            'status_note' => $u->status_note,
            'locale' => $u->locale,
            'theme' => $u->theme,
            'loader' => $u->loader,
            'referral_code' => $u->referral_code,
            'email_verified_at' => $u->email_verified_at?->toIso8601String(),
            'mfa_enabled' => (bool) $u->mfa_enabled,
            'mfa_passed' => $u->isAdmin() ? (bool) session('admin_mfa_passed') : null,
            'created_at' => $u->created_at?->toIso8601String(),
            'plan' => $sub ? ['code' => $sub->plan_code, 'name' => $sub->plan_name, 'ends_at' => $sub->ends_at?->toIso8601String(), 'status' => $status] : null,
            'entitlements' => $u->isAdmin() ? (object) [] : Entitlements::forUser($u),
            'balances' => [
                'topup' => Wallet::balance($u->id, 'topup'),
                'commission' => Wallet::balance($u->id, 'commission'),
                'commission_reserved' => Wallet::reserved($u->id),
            ],
            'unread_notifications' => $u->unreadNotifications()->count(),
            'pending_terms' => $u->isAdmin() ? [] : self::pendingTerms($u),
            'ai_consent' => (bool) $u->ai_consent_at,
            'auto_renew' => (bool) $u->auto_renew,
            'trial_available' => ! $u->isAdmin() && ! $u->trial_used_at && ! (($s = $u->activeSubscription()) && $s->price > 0),
            'scheduled_change' => ($s2 = $u->activeSubscription()) && $s2->scheduled_plan_code ? ['plan_code' => $s2->scheduled_plan_code, 'effective_at' => $s2->ends_at?->toIso8601String()] : null,
            'limits' => $u->isAdmin() ? (object) [] : (object) Entitlements::grants($u)['limits'],
        ];
    }

    /** Dokumen hukum versi baru yang wajib disetujui ulang (Design Spec §11.2 butir 1). */
    public static function pendingTerms(User $u): array
    {
        $out = [];
        foreach (LegalDocument::where('is_active', true)->where('requires_reconsent', true)->get() as $doc) {
            $agreed = UserConsent::where('user_id', $u->id)->where('document', $doc->type)->where('version', $doc->version)->exists();
            if (! $agreed) {
                $out[] = self::legal($doc);
            }
        }

        return $out;
    }

    public static function legal(LegalDocument $d): array
    {
        $en = self::lang() === 'en' && ! empty($d->sections_en);

        return [
            'id' => $d->id,
            'type' => $d->type,
            'title' => $en ? ($d->title_en ?: $d->title_id) : $d->title_id,
            'version' => $d->version,
            'effective_at' => $d->effective_at?->toDateString(),
            'sections' => $en ? $d->sections_en : $d->sections_id,
            'summary_of_changes' => $en ? ($d->summary_en ?: $d->summary_id) : $d->summary_id,
            'is_translated' => self::lang() === 'id' || $en,
        ];
    }

    public static function plan(Plan $p): array
    {
        return [
            'id' => $p->id,
            'code' => $p->code,
            'name' => self::tr($p, 'name'),
            'description' => self::tr($p, 'description') ?? '',
            'price_monthly' => (int) $p->price_monthly,
            'price_yearly' => (int) $p->price_yearly,
            'is_recommended' => $p->is_recommended,
            'is_active' => $p->is_active,
            'sort' => $p->sort,
            'features' => $p->features ?? [],
            'quotas' => (object) ($p->quotas ?? []),
            'limits' => (object) ($p->limits ?? []),
        ];
    }

    public static function template(Template $t): array
    {
        return [
            'id' => $t->id,
            'title' => self::tr($t, 'title'),
            'type' => $t->type,
            'category' => $t->category,
            'ratio' => $t->ratio,
            'is_premium' => $t->is_premium,
            'status' => $t->status,
            'is_featured' => $t->is_featured,
            'palette' => $t->palette ?? [],
            'art' => \Illuminate\Support\Str::slug((string) $t->title_id),
            'cover_url' => $t->cover_url,
            'focus' => $t->focus,
            'badges' => $t->badges ?? [],
            'usage_count' => $t->usage_count,
            'description' => self::tr($t, 'description') ?? '',
        ];
    }

    public static function job(AiJob $j): array
    {
        return [
            'id' => $j->id,
            'type' => $j->type,
            'prompt' => $j->prompt,
            'status' => $j->status,
            'progress' => (int) $j->progress,
            'cost' => (int) $j->cost,
            'result' => $j->result,
            'error' => $j->error,
            'created_at' => $j->created_at?->toIso8601String(),
            'completed_at' => $j->completed_at?->toIso8601String(),
        ];
    }

    /** Paginasi standar {data, meta} (PRD 33.3). */
    public static function paginate($paginator, ?callable $map = null): array
    {
        $items = collect($paginator->items());

        return [
            'data' => $map ? $items->map($map)->values() : $items->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public static function mask(string $email): string
    {
        [$u, $d] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($u, 0, 1).'***@'.$d;
    }
}
