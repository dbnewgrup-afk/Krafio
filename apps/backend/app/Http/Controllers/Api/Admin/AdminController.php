<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\WorkspaceController;
use App\Http\Controllers\Controller;
use App\Models\AiJob;
use App\Models\AiProvider;
use App\Models\Approval;
use App\Models\CustomPackage;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\SecurityEvent;
use App\Models\Template;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\Notice;
use App\Services\AiGateway;
use App\Services\Audit;
use App\Services\Entitlements;
use App\Services\Settings;
use App\Services\Wallet;
use App\Services\Messenger;
use App\Support\ApiException;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

/** Admin Panel API (PRD 13 + Spesifikasi Admin Panel). Seluruh tindakan penting masuk audit log. */
class AdminController extends Controller
{
    /** Tindakan keuangan butuh re-autentikasi bila sesi > 15 menit (Design Spec §7.2 butir 6). */
    private function requireRecentAuth(): void
    {
        if (time() - (int) session('admin_reauth_at', 0) > 15 * 60) {
            throw new ApiException('reauth_required', 'Konfirmasi kata sandi diperlukan.', 403);
        }
    }

    public function reauth(Request $r)
    {
        $r->validate(['password' => 'required|string']);
        if (! Hash::check($r->input('password'), $r->user()->password)) {
            Audit::security('admin_reauth_failed', $r->user()->id, 'warning');
            throw new ApiException('invalid_credentials', 'Kata sandi salah.', 422);
        }
        session(['admin_reauth_at' => time()]);

        return response()->noContent();
    }

    public function ping()
    {
        $limit = (int) Settings::get('security.admin_session_minutes') * 60;

        return ['data' => ['expires_in' => max(0, $limit - (time() - (int) session('admin_last_seen', time())))]];
    }

    public function badges()
    {
        return ['data' => [
            'approvals' => Approval::where('status', 'pending')->count(),
            'withdrawals' => Withdrawal::whereIn('status', ['requested', 'under_review'])->count(),
            'tickets' => Ticket::where('status', 'open')->count(),
            'failed_jobs' => AiJob::where('status', 'failed')->where('created_at', '>', now()->subDay())->count(),
            'account_approvals' => Approval::where('status', 'pending')->where('type', 'account')->count(),
        ]];
    }

    public function dashboard()
    {
        $today = now()->startOfDay();
        $month = now()->startOfMonth();

        return ['data' => [
            'users' => [
                'total' => User::where('role', 'user')->count(),
                'new_today' => User::where('role', 'user')->where('created_at', '>=', $today)->count(),
                'active' => User::where('role', 'user')->where('status', 'active')->count(),
                'pending' => User::where('role', 'user')->whereIn('status', ['pending_verification', 'pending_approval'])->count(),
                'suspended' => User::where('role', 'user')->whereIn('status', ['suspended', 'banned'])->count(),
            ],
            'finance' => [
                'revenue_month' => (int) Payment::where('status', 'paid')->where('purpose', '!=', 'topup')->where('paid_at', '>=', $month)->sum('amount') + (int) LedgerEntry::where('ref_type', 'order')->where('direction', 'debit')->where('created_at', '>=', $month)->sum('amount'),
                'topup_today' => (int) Payment::where('status', 'paid')->where('purpose', 'topup')->where('paid_at', '>=', $today)->sum('amount'),
                'withdraw_pending' => (int) Withdrawal::whereIn('status', ['requested', 'under_review', 'approved', 'processing'])->sum('amount'),
                'pending_payments' => Payment::where('status', 'pending')->count(),
            ],
            'ai' => [
                'jobs_today' => AiJob::where('created_at', '>=', $today)->count(),
                'failed_today' => AiJob::where('status', 'failed')->where('created_at', '>=', $today)->count(),
                'active_providers' => AiProvider::where('status', 'active')->count(),
                'tokens_month' => (int) AiProvider::sum('tokens_used'),
            ],
            'system' => [
                'queue_backlog' => DB::table('jobs')->count(),
                'failed_queue' => DB::table('failed_jobs')->count(),
                'security_events_today' => SecurityEvent::where('created_at', '>=', $today)->count(),
            ],
            'signups_14d' => collect(range(13, 0))->map(fn ($d) => [
                'date' => now()->subDays($d)->toDateString(),
                'count' => User::where('role', 'user')->whereDate('created_at', now()->subDays($d)->toDateString())->count(),
            ]),
            'recent_approvals' => Approval::where('status', 'pending')->latest()->limit(5)->get()->map(fn ($a) => ['id' => $a->id, 'type' => $a->type, 'title' => $a->title, 'created_at' => $a->created_at->toIso8601String()]),
        ]];
    }

    /* ---------------- Users ---------------- */
    public function users(Request $r)
    {
        $q = User::query()->where('role', $r->query('role') === 'admin' ? 'admin' : 'user');
        if ($s = trim((string) $r->query('search'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"));
        }
        if ($st = $r->query('status')) {
            $q->whereIn('status', explode(',', $st));
        }
        $sort = (string) $r->query('sort', '');
        in_array(ltrim($sort, '-'), ['created_at', 'name', 'last_login_at'], true) ? $q->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc') : $q->latest();
        $map = fn ($u) => [
            'id' => $u->id, 'name' => $u->name, 'email' => Present::mask($u->email), 'phone' => $u->phone ? substr($u->phone, 0, 4).'****'.substr($u->phone, -3) : null,
            'status' => $u->status, 'plan' => $u->activeSubscription()?->plan_name, 'created_at' => $u->created_at->toIso8601String(), 'last_login_at' => $u->last_login_at?->toIso8601String(),
        ];
        if ($r->query('export') === 'csv') {
            Audit::log('admin.users.export');

            return BillingController::csv('users.csv', ['ID', 'Nama', 'Email', 'Status', 'Paket', 'Daftar'], $q->limit(10000)->get()->map(fn ($u) => [$u->id, $u->name, Present::mask($u->email), $u->status, $u->activeSubscription()?->plan_name, $u->created_at]));
        }

        return Present::paginate($q->paginate(min(100, (int) $r->query('per_page', 25))), $map);
    }

    public function user(User $user)
    {
        $me = Present::me($user);
        $me['email'] = Present::mask($user->email);
        $me['phone'] = $user->phone ? substr($user->phone, 0, 4).'****'.substr($user->phone, -3) : null;

        return ['data' => $me + [
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'last_login_ip' => $user->last_login_ip,
            'referred_by' => $user->referrer?->name,
            'subscriptions' => $user->subscriptions()->latest('id')->limit(20)->get(['id', 'plan_name', 'status', 'starts_at', 'ends_at', 'price']),
            'counts' => ['jobs' => AiJob::where('user_id', $user->id)->count(), 'websites' => $user->hasMany(\App\Models\Website::class)->count(), 'referrals' => User::where('referred_by', $user->id)->count()],
        ]];
    }

    /** Tampilkan data sensitif — selalu tercatat ke audit log (Design Spec §7.2 butir 8). */
    public function reveal(Request $r, User $user)
    {
        $field = $r->validate(['field' => 'required|in:email,phone'])['field'];
        Audit::log('admin.users.reveal', $user, ['field' => $field]);

        return ['data' => ['value' => $user->{$field}]];
    }

    public function setStatus(Request $r, User $user)
    {
        abort_if($user->isAdmin(), 403);
        $data = $r->validate(['status' => 'required|in:'.implode(',', User::STATUSES), 'reason' => 'required|string|min:5|max:1000']);
        $old = $user->status;
        $user->forceFill(['status' => $data['status'], 'status_note' => $data['reason']])->save();
        if ($data['status'] === 'active') {
            AuthController::grantFreePlan($user);
        }
        if (in_array($data['status'], ['suspended', 'banned', 'deactivated'], true)) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        $user->notify(new Notice('Status akun diperbarui', 'Status akun Anda: '.$data['status'].'.', 'security'));
        Audit::log('admin.users.status', $user, ['from' => $old, 'to' => $data['status'], 'reason' => $data['reason']]);

        return response()->noContent();
    }

    /** Penyesuaian saldo hanya lewat entri ledger + alasan + re-autentikasi (PRD 5.3, 31). */
    public function adjustBalance(Request $r, User $user)
    {
        $this->requireRecentAuth();
        $data = $r->validate(['wallet' => 'required|in:topup,commission', 'direction' => 'required|in:credit,debit', 'amount' => 'required|integer|min:1|max:100000000', 'reason' => 'required|string|min:5|max:500']);
        $e = Wallet::post($user->id, $data['wallet'], $data['direction'], $data['amount'], 'Penyesuaian admin', ['admin_adjustment', null], $data['reason'], $r->user()->id);
        $user->notify(new Notice('Saldo disesuaikan', 'Saldo '.$data['wallet'].' disesuaikan oleh admin.', 'transaction', '/app/wallet'));
        Audit::log('admin.wallet.adjust', $user, $data + ['ledger_id' => $e->id]);

        return response()->noContent();
    }

    public function resetQuota(Request $r, User $user)
    {
        $data = $r->validate(['entitlement' => 'required|in:'.implode(',', Entitlements::KEYS), 'reason' => 'required|string|min:5']);
        \App\Models\UsageCounter::where(['user_id' => $user->id, 'entitlement' => $data['entitlement'], 'period' => Entitlements::period()])->update(['used' => 0]);
        Audit::log('admin.quota.reset', $user, $data);

        return response()->noContent();
    }

    /* ---------------- Approvals ---------------- */
    public function approvals(Request $r)
    {
        $q = Approval::with(['user', 'decider'])->latest();
        if ($t = $r->query('type')) {
            $q->where('type', $t);
        }
        $q->where('status', $r->query('status', 'pending'));
        if ($s = $r->query('search')) {
            $q->where('title', 'like', "%{$s}%");
        }

        return Present::paginate($q->paginate(min(100, (int) $r->query('per_page', 25))), fn ($a) => [
            'id' => $a->id, 'type' => $a->type, 'title' => $a->title, 'status' => $a->status, 'reason' => $a->reason, 'payload' => $a->payload,
            'user' => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name, 'email' => Present::mask($a->user->email)] : null,
            'decider' => $a->decider?->name, 'decided_at' => $a->decided_at?->toIso8601String(), 'created_at' => $a->created_at->toIso8601String(), 'subject_id' => $a->subject_id,
        ]);
    }

    public function decide(Request $r, Approval $approval)
    {
        $data = $r->validate(['decision' => 'required|in:approve,reject,revision', 'reason' => 'nullable|string|max:1000', 'reference' => 'nullable|string|max:120']);
        if ($data['decision'] !== 'approve' && mb_strlen(trim((string) ($data['reason'] ?? ''))) < 5) {
            throw new ApiException('validation_failed', 'Alasan wajib diisi.', 422, [], ['reason' => ['Alasan wajib diisi (minimal 5 karakter).']]);
        }
        if ($approval->status !== 'pending') {
            throw new ApiException('already_decided', 'Approval sudah diputuskan.', 422);
        }
        if ($approval->type === 'withdrawal') {
            $this->requireRecentAuth();
        }
        DB::transaction(function () use ($approval, $data, $r) {
            $status = ['approve' => 'approved', 'reject' => 'rejected', 'revision' => 'revision'][$data['decision']];
            $approval->update(['status' => $status, 'reason' => $data['reason'] ?? null, 'decided_by' => $r->user()->id, 'decided_at' => now()]);
            $u = $approval->user;
            switch ($approval->type) {
                case 'account':
                    $map = ['approve' => 'active', 'reject' => 'rejected', 'revision' => 'request_revision'];
                    $u->forceFill(['status' => $map[$data['decision']], 'status_note' => $data['reason'] ?? null])->save();
                    if ($data['decision'] === 'approve') {
                        AuthController::grantFreePlan($u);
                    }
                    $u->notify(new Notice('Keputusan akun', $data['decision'] === 'approve' ? 'Akun Anda telah disetujui.' : 'Akun Anda memerlukan tindak lanjut.', 'security'));
                    break;
                case 'withdrawal':
                    $w = Withdrawal::lockForUpdate()->findOrFail($approval->subject_id);
                    if ($data['decision'] === 'approve') {
                        $w->update(['status' => 'approved', 'processed_by' => $r->user()->id]);
                    } else {
                        // Dana yang di-reserve dikembalikan ke saldo komisi (PRD 5.5)
                        $w->update(['status' => 'rejected', 'admin_note' => $data['reason'], 'processed_by' => $r->user()->id, 'processed_at' => now()]);
                        Wallet::post($w->user_id, 'commission', 'credit', (int) $w->amount, 'Pengembalian withdrawal #'.$w->id, ['withdrawal_refund', $w->id], $data['reason'], $r->user()->id);
                    }
                    if ($data['decision'] === 'approve') {
                        $u->notify(new Notice('Withdrawal disetujui', 'Withdrawal Rp'.number_format($w->amount, 0, ',', '.').' disetujui dan segera diproses.', 'transaction', '/app/wallet/withdrawals'));
                    } else {
                        Messenger::notify($u, 'withdrawal_rejected', ['amount' => 'Rp'.number_format($w->amount, 0, ',', '.'), 'reason' => (string) $data['reason']], 'Withdrawal ditolak', 'Withdrawal Rp'.number_format($w->amount, 0, ',', '.').' ditolak: '.$data['reason'], '/app/wallet/withdrawals');
                    }
                    break;
                case 'template':
                    Template::whereKey($approval->subject_id)->update(['status' => $data['decision'] === 'approve' ? 'active' : 'draft']);
                    break;
                case 'custom_package':
                    $u?->notify(new Notice('Permintaan custom package', $data['decision'] === 'approve' ? 'Permintaan disetujui; admin akan menawarkan paket.' : 'Permintaan belum dapat dipenuhi: '.$data['reason'], 'subscription', '/app/custom-packages'));
                    break;
            }
            Audit::log('admin.approval.'.$data['decision'], $approval, ['type' => $approval->type, 'reason' => $data['reason'] ?? null]);
        });

        return response()->noContent();
    }

    /** Tahap lanjut withdrawal setelah approve: processing → paid (wajib referensi bukti) — PRD 5.5 butir 10–12. */
    public function withdrawalStatus(Request $r, Withdrawal $withdrawal)
    {
        $this->requireRecentAuth();
        $data = $r->validate(['status' => 'required|in:under_review,processing,paid', 'reference' => 'required_if:status,paid|nullable|string|max:120', 'note' => 'nullable|string|max:500']);
        $allowed = ['requested' => ['under_review'], 'under_review' => [], 'approved' => ['processing'], 'processing' => ['paid']];
        if (! in_array($data['status'], $allowed[$withdrawal->status] ?? [], true)) {
            throw new ApiException('invalid_transition', 'Perubahan status tidak valid.', 422);
        }
        $withdrawal->update(['status' => $data['status'], 'reference' => $data['reference'] ?? $withdrawal->reference, 'admin_note' => $data['note'] ?? $withdrawal->admin_note, 'processed_at' => $data['status'] === 'paid' ? now() : $withdrawal->processed_at]);
        if ($data['status'] === 'paid') {
            \App\Models\Commission::where('referrer_id', $withdrawal->user_id)->where('status', 'available')->limit(1000)->update(['status' => 'paid']);
            Messenger::notify($withdrawal->user, 'withdrawal_paid', ['amount' => 'Rp'.number_format($withdrawal->amount, 0, ',', '.'), 'reference' => (string) $data['reference']], 'Withdrawal dibayar', 'Dana Rp'.number_format($withdrawal->amount, 0, ',', '.').' telah ditransfer. Ref: '.$data['reference'], '/app/wallet/withdrawals');
        }
        Audit::log('admin.withdrawal.'.$data['status'], $withdrawal, $data);

        return ['data' => BillingController::wd($withdrawal)];
    }

    /* ---------------- Settings ---------------- */
    public function settings()
    {
        $all = Settings::all();
        foreach (Settings::SECRET_KEYS as $k) {
            $all[$k] = ! empty($all[$k]) ? '__set__' : null;
        }
        foreach ($all as $k => $v) {
            if (str_contains($k, 'webhook_secret')) {
                $all[$k] = $v ? '__set__' : null;
            }
        }

        return ['data' => $all];
    }

    public function saveSettings(Request $r)
    {
        $data = $r->validate(['values' => 'required|array']);
        $changed = [];
        foreach ($data['values'] as $k => $v) {
            if (! preg_match('/^[a-z_]+(\.[a-z0-9_]+)+$/', $k)) {
                continue;
            }
            if ($v === '__set__') {
                continue; // secret tidak diubah
            }
            Settings::set($k, $v);
            $changed[] = $k;
        }
        Audit::log('admin.settings.updated', null, ['keys' => $changed]);

        return ['data' => Settings::all()];
    }

    /* ---------------- System ---------------- */
    public function system()
    {
        $db = ['status' => 'healthy', 'version' => null, 'size_mb' => null];
        try {
            $db['version'] = DB::selectOne('select version() v')->v;
            $db['size_mb'] = round((float) DB::selectOne('select sum(data_length+index_length)/1024/1024 s from information_schema.tables where table_schema = database()')->s, 1);
        } catch (\Throwable) {
            $db['status'] = 'down';
        }
        $redis = 'not_configured';
        if (extension_loaded('redis') || class_exists(\Predis\Client::class)) {
            try {
                \Illuminate\Support\Facades\Redis::connection()->ping();
                $redis = 'healthy';
            } catch (\Throwable) {
                $redis = 'down';
            }
        }
        $cacheOk = true;
        try {
            Cache::put('krafio.health', 1, 5);
        } catch (\Throwable) {
            $cacheOk = false;
        }

        return ['data' => [
            'app' => ['version' => config('krafio.version'), 'php' => PHP_VERSION, 'laravel' => app()->version(), 'environment' => app()->environment(), 'debug' => (bool) config('app.debug')],
            'database' => $db,
            'cache' => ['driver' => config('cache.default'), 'status' => $cacheOk ? 'healthy' : 'down'],
            'redis' => ['status' => $redis],
            'queue' => ['driver' => config('queue.default'), 'backlog' => DB::table('jobs')->count(), 'failed' => DB::table('failed_jobs')->count(), 'inline_mode' => (bool) config('krafio.jobs_inline')],
            'storage' => ['disk_free_gb' => round(@disk_free_space(storage_path()) / 1024 / 1024 / 1024, 1), 'disk_total_gb' => round(@disk_total_space(storage_path()) / 1024 / 1024 / 1024, 1)],
            'session' => ['driver' => config('session.driver'), 'lifetime' => config('session.lifetime')],
            'mail' => ['mailer' => config('mail.default')],
            'checked_at' => now()->toIso8601String(),
        ]];
    }

    /* ---------------- AI providers ---------------- */
    public function rotateKey(Request $r, AiProvider $provider)
    {
        $this->requireRecentAuth();
        $provider->update(['api_key' => $r->validate(['api_key' => 'required|string|min:8|max:500'])['api_key'], 'key_rotated_at' => now()]);
        Audit::log('admin.ai.key_rotated', $provider);

        return response()->noContent();
    }

    public function testProvider(AiProvider $provider)
    {
        if (! $provider->api_key || ! $provider->base_url) {
            throw new ApiException('not_configured', 'Base URL dan API key wajib diisi.', 422);
        }
        try {
            $res = Http::timeout(10)->withToken($provider->api_key)->acceptJson()->get(rtrim($provider->base_url, '/').'/models');
            $ok = $res->successful();
        } catch (\Throwable) {
            $ok = false;
        }
        $provider->update(['health' => $ok ? 'healthy' : 'down', 'last_checked_at' => now()]);
        Audit::log('admin.ai.tested', $provider, ['ok' => $ok]);

        return ['data' => ['ok' => $ok, 'health' => $provider->health]];
    }

    public function aiUsage()
    {
        return ['data' => [
            'by_type' => AiJob::selectRaw('type, count(*) jobs, sum(cost) credits, sum(status = "failed") failed')->where('created_at', '>=', now()->startOfMonth())->groupBy('type')->get(),
            'providers' => AiProvider::orderBy('priority')->get(['id', 'name', 'status', 'health', 'tokens_used', 'monthly_token_limit', 'cost_month', 'priority']),
            'text_configured' => AiGateway::configured('text'),
            'image_configured' => AiGateway::configured('image'),
            'video_configured' => AiGateway::configured('video'),
        ]];
    }

    /* ---------------- Tickets ---------------- */
    public function ticket(Ticket $ticket)
    {
        return ['data' => WorkspaceController::ticketDetail($ticket)];
    }

    public function replyTicket(Request $r, Ticket $ticket)
    {
        AppServicesInbox::post($ticket, 'admin', $r->validate(['body' => 'required|string|max:5000'])['body'], $r->user());
        Audit::log('admin.ticket.replied', $ticket);

        return response()->noContent();
    }

    public function updateTicket(Request $r, Ticket $ticket)
    {
        $to = $r->validate(['status' => 'required|in:open,pending,resolved,closed'])['status'];
        AppServicesInbox::activity($ticket, 'status', ['from' => $ticket->status, 'to' => $to]);
        $ticket->update(['status' => $to] + (in_array($to, ['resolved', 'closed'], true) ? ['sla_due_at' => null] : []));
        Audit::log('admin.ticket.status', $ticket, ['status' => $ticket->status]);

        return response()->noContent();
    }

    public function offerCustomPackage(Request $r, CustomPackage $package)
    {
        $package->update(['status' => 'published']);
        if ($package->offered_to) {
            User::find($package->offered_to)?->notify(new Notice('Penawaran paket khusus', $package->name, 'subscription', '/app/custom-packages'));
        }
        Audit::log('admin.custom_package.offered', $package);

        return response()->noContent();
    }
}
