<?php

namespace App\Services;

use App\Models\AiJob;
use App\Models\AuditLog;
use App\Models\Commission;
use App\Models\CouponRedemption;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Template;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Carbon;

/**
 * Laporan & Analitik (blueprint halaman 10): ringkasan, user per paket, funnel akuisisi, job AI, operasional,
 * keuangan, KPI (hari ini / periode / Δ periode sebelumnya), dan template teratas. Semua dihitung dari data nyata.
 */
class Reports
{
    public static function build(Carbon $from, Carbon $to, ?string $plan = null, ?string $gateway = null): array
    {
        $days = max(1, (int) ceil($from->diffInDays($to)));
        $prevFrom = $from->copy()->subDays($days);
        $prevTo = $from->copy();

        return [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'days' => $days],
            'users_by_plan' => self::usersByPlan(),
            'funnel' => self::funnel($from, $to),
            'ai' => self::ai($from, $to),
            'operations' => self::operations($from, $to, $gateway),
            'finance' => self::finance($from, $to, $plan, $gateway),
            'kpi' => self::kpi($from, $to, $prevFrom, $prevTo),
            'templates' => Template::orderByDesc('usage_count')->limit(8)->get(['id', 'title_id', 'type', 'is_premium', 'usage_count'])
                ->map(fn ($t) => ['id' => $t->id, 'title' => $t->title_id, 'type' => $t->type, 'premium' => (bool) $t->is_premium, 'uses' => (int) $t->usage_count]),
            'margin' => Ops::aiMargin(),
        ];
    }

    private static function usersByPlan(): array
    {
        $users = User::where('role', 'user');
        $total = (clone $users)->count();
        $rows = Subscription::query()->selectRaw('plan_code, plan_name, count(distinct user_id) n')->whereIn('status', ['active', 'past_due'])
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->groupBy('plan_code', 'plan_name')->orderByDesc('n')->get();
        $paid = (int) Subscription::whereIn('status', ['active', 'past_due'])->where('price', '>', 0)->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->distinct('user_id')->count('user_id');

        return [
            'total' => $total, 'paid' => $paid, 'conversion' => $total ? round($paid / $total * 100, 1) : 0.0,
            'suspended' => (clone $users)->whereIn('status', ['suspended', 'banned'])->count(),
            'plans' => $rows->map(fn ($r) => ['code' => $r->plan_code, 'name' => $r->plan_name, 'users' => (int) $r->n])->values(),
        ];
    }

    private static function funnel(Carbon $from, Carbon $to): array
    {
        $reg = User::where('role', 'user')->whereBetween('created_at', [$from, $to]);
        $registered = (clone $reg)->count();
        $verified = (clone $reg)->whereNotNull('email_verified_at')->count();
        $active = (clone $reg)->where('status', 'active')->count();
        $paid = (int) Payment::whereIn('user_id', (clone $reg)->select('id'))->where('status', 'paid')->where('purpose', '!=', 'topup')->distinct('user_id')->count('user_id');

        return ['registered' => $registered, 'verified' => $verified, 'active' => $active, 'paid' => $paid, 'conversion' => $registered ? round($paid / $registered * 100, 1) : 0.0];
    }

    private static function ai(Carbon $from, Carbon $to): array
    {
        $q = AiJob::whereBetween('created_at', [$from, $to]);
        $by = (clone $q)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status');
        $done = (int) ($by['completed'] ?? 0);
        $failed = (int) ($by['failed'] ?? 0);
        $avg = (clone $q)->where('status', 'completed')->whereNotNull('started_at')->whereNotNull('completed_at')
            ->selectRaw('avg(timestampdiff(second, started_at, completed_at)) s')->value('s');
        $recovered = (int) AuditLog::where('action', 'ops.stuck_jobs_recovered')->whereBetween('created_at', [$from, $to])->get()->sum(fn ($a) => (int) ($a->meta['count'] ?? 0));

        return [
            'total' => (int) $by->sum(), 'completed' => $done, 'failed' => $failed, 'cancelled' => (int) ($by['cancelled'] ?? 0),
            'running' => (int) (($by['queued'] ?? 0) + ($by['processing'] ?? 0)),
            'success_rate' => $done + $failed ? round($done / ($done + $failed) * 100, 1) : null,
            'avg_seconds' => $avg !== null ? (int) round((float) $avg) : null, 'stuck_recovered' => $recovered,
            'by_type' => (clone $q)->selectRaw('type, count(*) n, sum(cost) credits')->groupBy('type')->orderByDesc('n')->get()->map(fn ($r) => ['type' => $r->type, 'jobs' => (int) $r->n, 'credits' => (int) $r->credits])->values(),
            'provider_cost' => (int) (clone $q)->sum('provider_cost'),
        ];
    }

    private static function operations(Carbon $from, Carbon $to, ?string $gateway): array
    {
        $pay = Payment::where('gateway', '!=', 'balance')->whereBetween('created_at', [$from, $to])->whereIn('status', ['paid', 'failed', 'expired', 'cancelled', 'refunded']);
        if ($gateway) {
            $pay->where('gateway', $gateway);
        }
        $payTotal = (clone $pay)->count();
        $payPaid = (clone $pay)->whereIn('status', ['paid', 'refunded'])->count();
        $renewOk = LedgerEntry::where('ref_type', 'renewal')->whereBetween('created_at', [$from, $to])->count();
        $renewFail = AuditLog::where('action', 'subscription.renewal_failed')->whereBetween('created_at', [$from, $to])->count();
        $tickets = Ticket::whereBetween('created_at', [$from, $to]);
        $tTotal = (clone $tickets)->count();
        $tLate = (clone $tickets)->whereNotNull('sla_due_at')->where('sla_due_at', '<', now())->whereIn('status', ['open', 'pending'])->count();

        return [
            'payment_success' => $payTotal ? round($payPaid / $payTotal * 100, 1) : null, 'payments' => $payTotal,
            'renewal_success' => $renewOk + $renewFail ? round($renewOk / ($renewOk + $renewFail) * 100, 1) : null, 'renewals' => $renewOk, 'renewals_failed' => $renewFail,
            'tickets' => $tTotal, 'tickets_within_sla' => $tTotal ? round(($tTotal - $tLate) / $tTotal * 100, 1) : null,
            'tickets_resolved' => (clone $tickets)->whereIn('status', ['resolved', 'closed'])->count(),
        ];
    }

    private static function finance(Carbon $from, Carbon $to, ?string $plan, ?string $gateway): array
    {
        $sales = Payment::whereIn('status', ['paid', 'refunded'])->where('purpose', '!=', 'topup')->whereBetween('paid_at', [$from, $to]);
        if ($plan) {
            $sales->where('purpose_code', $plan);
        }
        if ($gateway) {
            $sales->where('gateway', $gateway);
        }

        return [
            'sales' => (int) $sales->sum('amount'),
            'topups' => (int) Payment::where('status', 'paid')->where('purpose', 'topup')->whereBetween('paid_at', [$from, $to])->when($gateway, fn ($q) => $q->where('gateway', $gateway))->sum('amount'),
            'refunds' => (int) Payment::where('status', 'refunded')->whereBetween('refunded_at', [$from, $to])->sum('refund_amount'),
            'commission' => (int) Commission::whereNotIn('status', ['reversed'])->whereBetween('created_at', [$from, $to])->sum('amount'),
            'withdrawals_paid' => (int) Withdrawal::where('status', 'paid')->whereBetween('updated_at', [$from, $to])->sum('amount'),
            'discounts' => (int) CouponRedemption::where('status', 'used')->whereBetween('updated_at', [$from, $to])->sum('discount'),
            'liabilities' => (int) LedgerEntry::query()->selectRaw("sum(case when direction = 'credit' then amount else -amount end) s")->value('s'),
            'daily' => Payment::whereIn('status', ['paid', 'refunded'])->where('purpose', '!=', 'topup')->whereBetween('paid_at', [$from, $to])
                ->selectRaw('date(paid_at) d, sum(amount) amount')->groupBy('d')->orderBy('d')->get()->map(fn ($r) => ['date' => $r->d, 'amount' => (int) $r->amount])->values(),
        ];
    }

    /** KPI: nilai hari ini, periode terpilih, dan perubahan dibanding periode sebelumnya yang sama panjang. */
    private static function kpi(Carbon $from, Carbon $to, Carbon $pFrom, Carbon $pTo): array
    {
        $metrics = [
            'registrations' => fn ($a, $b) => User::where('role', 'user')->whereBetween('created_at', [$a, $b])->count(),
            'revenue' => fn ($a, $b) => (int) Payment::whereIn('status', ['paid', 'refunded'])->whereBetween('paid_at', [$a, $b])->sum('amount'),
            'new_paid_subscriptions' => fn ($a, $b) => Subscription::where('price', '>', 0)->whereBetween('created_at', [$a, $b])->count(),
            'ai_jobs_completed' => fn ($a, $b) => AiJob::where('status', 'completed')->whereBetween('completed_at', [$a, $b])->count(),
            'renewals_failed' => fn ($a, $b) => AuditLog::where('action', 'subscription.renewal_failed')->whereBetween('created_at', [$a, $b])->count(),
            'tickets_resolved' => fn ($a, $b) => Ticket::whereIn('status', ['resolved', 'closed'])->whereBetween('updated_at', [$a, $b])->count(),
            'coupons_used' => fn ($a, $b) => CouponRedemption::where('status', 'used')->whereBetween('updated_at', [$a, $b])->count(),
            'withdrawals_paid' => fn ($a, $b) => Withdrawal::where('status', 'paid')->whereBetween('updated_at', [$a, $b])->count(),
        ];
        $out = [];
        foreach ($metrics as $key => $fn) {
            $cur = $fn($from, $to);
            $prev = $fn($pFrom, $pTo);
            $out[] = ['key' => $key, 'today' => $fn(now()->startOfDay(), now()), 'period' => $cur, 'previous' => $prev, 'delta' => $prev ? round(($cur - $prev) / $prev * 100, 1) : null];
        }

        return $out;
    }
}
