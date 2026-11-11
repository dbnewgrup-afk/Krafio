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
            ['key' => 'stuck_jobs', 'severity' => 'critical', 'count' => AiJob::where('status', 'processing')->where(fn ($q) => $q->where('started_at', '<', $stuckCut)->orWhere(fn ($w) => $w->whereNull('started_at')->where('updated_at', '<', $stuckCut)))->count(), 'to' => '/admin/ai/jobs'],
            ['key' => 'failed_jobs_24h', 'severity' => 'warning', 'count' => AiJob::where('status', 'failed')->where('updated_at', '>=', now()->subDay())->count(), 'to' => '/admin/ai/jobs'],
            ['key' => 'past_due', 'severity' => 'warning', 'count' => Subscription::where('status', 'past_due')->count(), 'to' => '/admin/billing/subscriptions'],
            ['key' => 'expiring_7d', 'severity' => 'info', 'count' => Subscription::where('status', 'active')->where('price', '>', 0)->whereBetween('ends_at', [now(), now()->addDays(7)])->count(), 'to' => '/admin/billing/subscriptions'],
            ['key' => 'pending_approvals', 'severity' => 'warning', 'count' => Approval::where('status', 'pending')->count(), 'to' => '/admin/approvals'],
            ['key' => 'pending_withdrawals', 'severity' => 'warning', 'count' => Withdrawal::whereIn('status', ['requested', 'pending', 'processing'])->count(), 'to' => '/admin/finance/withdrawals'],
            ['key' => 'failed_payments_24h', 'severity' => 'info', 'count' => Payment::where('status', 'failed')->where('updated_at', '>=', now()->subDay())->count(), 'to' => '/admin/finance/payments'],
            ['key' => 'open_tickets_sla', 'severity' => 'critical', 'count' => Ticket::whereIn('status', ['open', 'pending'])->whereNotNull('sla_due_at')->where('sla_due_at', '<', now())->count(), 'to' => '/admin/support/inbox'],
            ['key' => 'pending_accounts', 'severity' => 'info', 'count' => User::where('role', 'user')->where('status', 'pending_approval')->count(), 'to' => '/admin/approvals/accounts'],
        ];
        $rank = ['critical' => 0, 'warning' => 1, 'info' => 2];
        usort($items, fn ($a, $b) => [$a['count'] === 0, $rank[$a['severity']], -$a['count']] <=> [$b['count'] === 0, $rank[$b['severity']], -$b['count']]);

        return $items;
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
