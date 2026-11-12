<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\Commission;
use App\Models\LedgerEntry;
use App\Models\MailLog;
use App\Models\Payment;
use App\Models\WaLog;
use App\Models\Withdrawal;
use App\Services\Audit;
use App\Services\Backups;
use App\Services\Integrations;
use App\Services\Messenger;
use App\Services\Payment\PaymentGateway;
use App\Support\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Integrasi (payment, SMTP, WhatsApp), laporan, dan backup — dikelola dari Admin Panel. */
class OpsController extends Controller
{
    private const KINDS = ['payment', 'smtp', 'whatsapp'];

    private function kind(string $k): string
    {
        abort_unless(in_array($k, self::KINDS, true), 404);

        return $k;
    }

    public function integration(string $kind)
    {
        $data = Integrations::present($this->kind($kind));
        if ($kind === 'payment') {
            $data['webhook_urls'] = collect(['midtrans', 'xendit', 'tripay'])->mapWithKeys(fn ($d) => [$d => url("/api/webhooks/payment/{$d}")]);
        }

        return ['data' => $data];
    }

    public function saveIntegration(Request $r, string $kind)
    {
        $data = $r->validate(['driver' => 'required|string|max:30', 'values' => 'array', 'is_active' => 'required|boolean']);
        Integrations::save($this->kind($kind), $data['driver'], $data['values'] ?? [], $data['is_active'], $r->user()->id);

        return ['data' => Integrations::present($kind)];
    }

    public function testIntegration(Request $r, string $kind)
    {
        $this->kind($kind);
        $i = Integrations::get($kind);
        if (! $i) {
            throw new ApiException('not_configured', 'Simpan konfigurasi terlebih dahulu.', 422);
        }
        $c = ['driver' => $i->driver] + ($i->config ?? []);
        if ($kind === 'payment') {
            [$ok, $msg] = PaymentGateway::test($c);
        } elseif ($kind === 'whatsapp') {
            [$ok, $msg] = Messenger::testWhatsappConnection($c);
        } else {
            $to = (string) $r->input('to', $r->user()->email);
            if (! $i->is_active) {
                throw new ApiException('not_active', 'Aktifkan SMTP untuk menguji pengiriman.', 422);
            }
            $ok = Messenger::email($to, 'test', ['name' => $r->user()->name, 'time' => now()->format('d-m-Y H:i')], $r->user());
            $last = MailLog::latest('id')->first();
            $msg = $ok ? "Email uji terkirim ke {$to}." : 'Gagal: '.($last?->error ?? 'tidak diketahui');
        }
        Integrations::recordTest($kind, $ok, $msg);
        Audit::log("admin.integration.{$kind}.tested", $i, ['ok' => $ok]);

        return ['data' => ['ok' => $ok, 'message' => $msg]];
    }

    public function testWhatsappSend(Request $r)
    {
        $data = $r->validate(['to' => ['required', 'regex:/^\+?\d{8,15}$/'], 'event' => 'required|string|max:40']);
        [$ok, $msg] = Messenger::whatsapp($data['to'], $data['event'], ['code' => '123456', 'minutes' => '5', 'name' => $r->user()->name, 'amount' => 'Rp100.000', 'reference' => 'TEST', 'plan' => 'Pro'], $r->user());

        return ['data' => ['ok' => $ok, 'message' => $msg]];
    }

    public function syncWaTemplates()
    {
        [$ok, $msg] = Messenger::syncWaTemplates();

        return ['data' => ['ok' => $ok, 'message' => $msg]];
    }

    /** Laporan keuangan per hari/bulan (PRD 31.5). Hanya membaca; ledger tidak pernah diubah. */
    public function financeReport(Request $r)
    {
        $data = $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date', 'group' => 'nullable|in:day,month']);
        $from = isset($data['from']) ? \Carbon\Carbon::parse($data['from'])->startOfDay() : now()->subDays(29)->startOfDay();
        $to = isset($data['to']) ? \Carbon\Carbon::parse($data['to'])->endOfDay() : now()->endOfDay();
        $fmt = ($data['group'] ?? 'day') === 'month' ? '%Y-%m' : '%Y-%m-%d';
        $bucket = fn ($q, string $col) => $q->selectRaw("DATE_FORMAT({$col}, '{$fmt}') b, SUM(amount) s, COUNT(*) c")->groupBy('b')->pluck('s', 'b');

        $topup = $bucket(Payment::where('status', 'paid')->where('purpose', 'topup')->whereBetween('paid_at', [$from, $to]), 'paid_at');
        $gatewaySales = $bucket(Payment::where('status', 'paid')->where('purpose', '!=', 'topup')->where('gateway', '!=', 'balance')->whereBetween('paid_at', [$from, $to]), 'paid_at');
        $balanceSales = $bucket(LedgerEntry::where('ref_type', 'order')->where('direction', 'debit')->whereBetween('created_at', [$from, $to]), 'created_at');
        $refunds = $bucket(LedgerEntry::where('ref_type', 'order_refund')->where('direction', 'credit')->whereBetween('created_at', [$from, $to]), 'created_at');
        $withdrawPaid = $bucket(Withdrawal::where('status', 'paid')->whereBetween('processed_at', [$from, $to]), 'processed_at');
        $commission = $bucket(Commission::whereNotIn('status', ['reversed'])->whereBetween('created_at', [$from, $to]), 'created_at');

        $keys = collect([$topup, $gatewaySales, $balanceSales, $withdrawPaid, $commission])->flatMap(fn ($c) => $c->keys())->unique()->sort()->values();
        $rows = $keys->map(fn ($k) => [
            'period' => $k,
            'topup' => (int) ($topup[$k] ?? 0),
            'sales' => (int) ($gatewaySales[$k] ?? 0) + (int) ($balanceSales[$k] ?? 0) - (int) ($refunds[$k] ?? 0),
            'withdrawals_paid' => (int) ($withdrawPaid[$k] ?? 0),
            'commissions' => (int) ($commission[$k] ?? 0),
        ]);
        $totals = ['topup' => $rows->sum('topup'), 'sales' => $rows->sum('sales'), 'withdrawals_paid' => $rows->sum('withdrawals_paid'), 'commissions' => $rows->sum('commissions')];
        if ($r->query('export') === 'csv') {
            Audit::log('admin.report.finance_export');

            return BillingController::csv('laporan-keuangan.csv', ['Periode', 'Top-up', 'Penjualan', 'Withdrawal dibayar', 'Komisi'], $rows->map(fn ($x) => array_values($x)));
        }

        return ['data' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'rows' => $rows, 'totals' => $totals, 'outstanding' => [
            'topup_balance' => (int) DB::table('wallet_ledger as l')->whereRaw('l.id = (select max(id) from wallet_ledger x where x.user_id = l.user_id and x.wallet = ?)', ['topup'])->sum('balance_after'),
            'commission_balance' => (int) DB::table('wallet_ledger as l')->whereRaw('l.id = (select max(id) from wallet_ledger x where x.user_id = l.user_id and x.wallet = ?)', ['commission'])->sum('balance_after'),
            'withdraw_pending' => (int) Withdrawal::whereIn('status', ['requested', 'under_review', 'approved', 'processing'])->sum('amount'),
        ]]];
    }

    /** Ringkasan pengiriman email & WhatsApp 30 hari. */
    public function messagingReport()
    {
        $since = now()->subDays(30);
        $sum = fn ($m) => $m::where('created_at', '>=', $since)->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        return ['data' => ['mail' => $sum(MailLog::class), 'whatsapp' => $sum(WaLog::class), 'since' => $since->toDateString()]];
    }

    /* ---------------- Backup ---------------- */
    public function backups()
    {
        return ['data' => [
            'items' => Backup::latest('id')->limit(100)->get(),
            'settings' => collect(Backups::RETENTION_DEFAULTS)->mapWithKeys(fn ($v, $k) => [$k => Backups::setting($k)]),
            'directory' => Backups::dir(),
            'disk_free_gb' => round(@disk_free_space(Backups::dir()) / 1024 / 1024 / 1024, 1),
        ]];
    }

    public function runBackup(Request $r)
    {
        $db = Backups::database('manual');
        $files = $r->boolean('files') ? Backups::files('manual') : null;
        $rot = Backups::rotate();
        Audit::log('admin.backup.run', $db, ['status' => $db->status, 'files' => (bool) $files]);

        return ['data' => ['database' => $db, 'files' => $files, 'rotated' => $rot]];
    }

    public function downloadBackup(Backup $backup)
    {
        $path = Backups::dir().DIRECTORY_SEPARATOR.$backup->file;
        abort_unless($backup->status === 'success' && is_file($path), 404);
        Audit::log('admin.backup.download', $backup);

        return response()->download($path);
    }

    public function deleteBackup(Backup $backup)
    {
        @unlink(Backups::dir().DIRECTORY_SEPARATOR.$backup->file);
        $backup->delete();
        Audit::log('admin.backup.deleted', $backup);

        return response()->noContent();
    }

    public function prune()
    {
        return ['data' => Backups::prune()];
    }
}
