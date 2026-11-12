<?php

namespace App\Services;

use App\Http\Controllers\Api\StudioController;
use App\Models\AiJob;
use App\Models\Approval;
use App\Models\CreditLedgerEntry;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\Notice;
use Illuminate\Support\Carbon;

/**
 * Monitoring operasional & otomasi berbasis aturan (tanpa AI):
 * daftar "Perlu tindakan" untuk admin, pemulihan job macet, dan prakiraan kredit habis untuk user.
 */
class Ops
{
    public const STUCK_MINUTES = 30;

    /** @return array<int, array{key:string, severity:string, count:int, to:string}> */
    public static function attention(): array
    {
        $stuckCut = now()->subMinutes(self::STUCK_MINUTES);
        $items = [
            ['key' => 'stuck_jobs', 'severity' => 'critical', 'count' => AiJob::where('status', 'processing')->where(fn ($q) => $q->where('started_at', '<', $stuckCut)->orWhere(fn ($w) => $w->whereNull('started_at')->where('updated_at', '<', $stuckCut)))->count(), 'to' => '/admin/media/jobs'],
            ['key' => 'failed_jobs_24h', 'severity' => 'warning', 'count' => AiJob::where('status', 'failed')->where('updated_at', '>=', now()->subDay())->count(), 'to' => '/admin/media/jobs'],
            ['key' => 'past_due', 'severity' => 'warning', 'count' => Subscription::where('status', 'past_due')->count(), 'to' => '/admin/billing/subscriptions'],
            ['key' => 'expiring_7d', 'severity' => 'info', 'count' => Subscription::where('status', 'active')->where('price', '>', 0)->whereBetween('ends_at', [now(), now()->addDays(7)])->count(), 'to' => '/admin/billing/subscriptions'],
            ['key' => 'pending_approvals', 'severity' => 'warning', 'count' => Approval::where('status', 'pending')->count(), 'to' => '/admin/approvals'],
            ['key' => 'pending_withdrawals', 'severity' => 'warning', 'count' => Withdrawal::whereIn('status', ['requested', 'pending', 'processing'])->count(), 'to' => '/admin/finance/withdrawals'],
            ['key' => 'failed_payments_24h', 'severity' => 'info', 'count' => Payment::where('status', 'failed')->where('updated_at', '>=', now()->subDay())->count(), 'to' => '/admin/finance/payments'],
            ['key' => 'open_tickets_sla', 'severity' => 'critical', 'count' => Ticket::whereIn('status', ['open', 'pending'])->whereNotNull('sla_due_at')->where('sla_due_at', '<', now())->count(), 'to' => '/admin/support/inbox'],
            ['key' => 'pending_accounts', 'severity' => 'info', 'count' => User::where('role', 'user')->where('status', 'pending_approval')->count(), 'to' => '/admin/approvals/accounts'],
            ['key' => 'pending_refunds', 'severity' => 'warning', 'count' => Approval::where('type', 'refund')->where('status', 'pending')->count(), 'to' => '/admin/approvals/refunds'],
        ];
        $m = self::aiMargin();
        $items[] = ['key' => 'ai_margin_low', 'severity' => 'critical', 'count' => $m['low'] ? 1 : 0, 'to' => '/admin/reports', 'value' => $m['margin'], 'threshold' => $m['threshold']];
        $rank = ['critical' => 0, 'warning' => 1, 'info' => 2];
        usort($items, fn ($a, $b) => [$a['count'] === 0, $rank[$a['severity']], -$a['count']] <=> [$b['count'] === 0, $rank[$b['severity']], -$b['count']]);

        return $items;
    }

    /**
     * Margin AI bulan berjalan (blueprint "Alert margin 55%"): pendapatan = kredit AI terpakai (setelah refund)
     * × nilai rupiah per kredit; biaya = akumulasi biaya provider (cost_idr model × pemakaian).
     *
     * @return array{revenue:int, cost:int, margin:?float, threshold:int, low:bool, credits_used:int}
     */
    public static function aiMargin(): array
    {
        $from = now()->startOfMonth();
        $debit = (int) CreditLedgerEntry::where('direction', 'debit')->where('ref_type', 'ai_job')->where('created_at', '>=', $from)->sum('amount');
        $refund = (int) CreditLedgerEntry::where('direction', 'credit')->where('ref_type', 'ai_job')->where('created_at', '>=', $from)->sum('amount');
        $credits = max(0, $debit - $refund);
        $revenue = $credits * (int) Settings::get('billing.credit_value_idr', 180);
        $cost = (int) \App\Models\AiProvider::sum('cost_month');
        $margin = $revenue > 0 ? round(($revenue - $cost) / $revenue * 100, 1) : ($cost > 0 ? -100.0 : null);
        $threshold = (int) Settings::get('billing.margin_alert_percent', 55);

        return ['revenue' => $revenue, 'cost' => $cost, 'margin' => $margin, 'threshold' => $threshold, 'low' => $margin !== null && $margin < $threshold, 'credits_used' => $credits];
    }

    /** Scheduler harian: beri tahu admin bila margin AI di bawah ambang (sekali per hari). */
    public static function alertLowMargin(): bool
    {
        $m = self::aiMargin();
        if (! $m['low'] || ! \Illuminate\Support\Facades\Cache::add('ops.margin_alert.'.now()->format('Ymd'), 1, now()->endOfDay())) {
            return false;
        }
        \Illuminate\Support\Facades\Notification::send(User::where('role', 'admin')->get(), new \App\Notifications\Notice(
            'Margin AI di bawah '.$m['threshold'].'%',
            'Margin bulan ini '.$m['margin'].'% (pendapatan kredit Rp'.number_format($m['revenue'], 0, ',', '.').', biaya provider Rp'.number_format($m['cost'], 0, ',', '.').'). Tinjau harga kredit, model, atau provider.',
            'system', '/admin/ai/usage'));
        Audit::log('ops.margin_low', null, $m, null);

        return true;
    }

    /** Awal bulan: reset penghitung token & biaya provider (dasar kuota token bulanan & margin). */
    public static function resetProviderCounters(): void
    {
        \App\Models\AiProvider::query()->update(['tokens_used' => 0, 'cost_month' => 0]);
    }

    /** Job yang macet di "processing" > 30 menit → gagal + kredit/kuota dikembalikan (sekali). */
    public static function recoverStuckJobs(): int
    {
        $cut = now()->subMinutes(self::STUCK_MINUTES);
        $n = 0;
        AiJob::where('status', 'processing')
            ->where(fn ($q) => $q->where('started_at', '<', $cut)->orWhere(fn ($w) => $w->whereNull('started_at')->where('updated_at', '<', $cut)))
            ->limit(200)->get()
            ->each(function (AiJob $job) use (&$n) {
                $job->update(['status' => 'failed', 'completed_at' => now(), 'error' => 'Waktu proses habis (lebih dari '.self::STUCK_MINUTES.' menit). Kredit dikembalikan.']);
                StudioController::refundJob($job, 'Job #'.$job->id.' melewati batas waktu');
                $job->user?->notify(new Notice('Job dihentikan', 'Job #'.$job->id.' melewati batas waktu. Kredit sudah dikembalikan.', 'ai', '/app/projects'));
                $n++;
            });
        if ($n > 0) {
            Audit::log('ops.stuck_jobs_recovered', null, ['count' => $n], null);
        }

        return $n;
    }

    /**
     * Prakiraan kredit habis dari rata-rata pemakaian harian 14 hari terakhir.
     *
     * @return array{daily_avg:float, days_left:?int, runs_out_at:?string, before_reset:?bool}
     */
    public static function creditForecast(User $u): array
    {
        $summary = Credits::summary($u);
        $since = now()->subDays(14)->startOfDay();
        $used = (int) CreditLedgerEntry::where('user_id', $u->id)->where('direction', 'debit')->where('created_at', '>=', $since)->sum('amount');
        $refunded = (int) CreditLedgerEntry::where('user_id', $u->id)->where('direction', 'credit')->whereIn('ref_type', ['ai_job'])->where('created_at', '>=', $since)->sum('amount');
        $first = CreditLedgerEntry::where('user_id', $u->id)->min('created_at');
        $days = max(1, min(14, $first ? (int) ceil(now()->diffInHours(max(Carbon::parse($first), $since)) / 24) : 14));
        $avg = round(max(0, $used - $refunded) / $days, 1);
        if ($avg <= 0 || $summary['remaining'] <= 0) {
            return ['daily_avg' => $avg, 'days_left' => $summary['remaining'] <= 0 ? 0 : null, 'runs_out_at' => null, 'before_reset' => null];
        }
        $left = (int) floor($summary['remaining'] / $avg);
        $at = now()->addDays($left);
        $reset = $summary['reset_at'] ? Carbon::parse($summary['reset_at']) : null;

        return ['daily_avg' => $avg, 'days_left' => $left, 'runs_out_at' => $at->toIso8601String(), 'before_reset' => $reset ? $at->lt($reset) : null];
    }
}
