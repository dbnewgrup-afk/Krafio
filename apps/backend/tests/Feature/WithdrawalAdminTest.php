<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Withdrawal;
use App\Services\Wallet;

/** Alur D — Withdrawal komisi & keputusan Admin (PRD 5.5, Design Spec §7.2 butir 6). */
class WithdrawalAdminTest extends KrafioTestCase
{
    private function request(int $commission, int $amount): array
    {
        $u = $this->user([], 'free', 0, $commission);
        $this->actingUser($u);
        $d = $this->json_('POST', '/app/wallet/destinations', ['type' => 'bank', 'provider' => 'BCA', 'holder' => 'Uji', 'account' => '1234567890'])->assertCreated()->json('data');
        $this->assertSame('****7890', $d['account_masked']);

        return [$u, $d, $this->json_('POST', '/app/wallet/withdrawals', ['amount' => $amount, 'destination_id' => $d['id']])];
    }

    public function test_d1_rules_min_amount_and_only_commission_balance(): void
    {
        [$u, $d] = $this->request(150000, 100000);
        $this->assertSame(50000, Wallet::balance($u->id, 'commission'), 'Nominal di-reserve dari saldo komisi');
        $this->json_('POST', '/app/wallet/withdrawals', ['amount' => 99000, 'destination_id' => $d['id']])->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->json_('POST', '/app/wallet/withdrawals', ['amount' => 100000, 'destination_id' => $d['id']])->assertStatus(422)->assertJsonPath('code', 'insufficient_balance');
        // saldo top-up tidak dapat ditarik
        Wallet::post($u->id, 'topup', 'credit', 500000, 'uji', ['test', null]);
        $this->json_('POST', '/app/wallet/withdrawals', ['amount' => 200000, 'destination_id' => $d['id']])->assertStatus(422)->assertJsonPath('code', 'insufficient_balance');
    }

    public function test_d2_admin_reject_returns_funds(): void
    {
        [$u, , $r] = $this->request(200000, 150000);
        $r->assertCreated();
        $ap = Approval::where('type', 'withdrawal')->firstOrFail();
        $this->admin();
        $this->json_('POST', "/admin/approvals/{$ap->id}/decide", ['decision' => 'reject'])->assertStatus(422); // alasan wajib
        $this->json_('POST', "/admin/approvals/{$ap->id}/decide", ['decision' => 'reject', 'reason' => 'Data rekening tidak sesuai'])->assertNoContent();
        $this->assertSame(200000, Wallet::balance($u->id, 'commission'));
        $this->assertSame('rejected', Withdrawal::first()->status);
    }

    public function test_d3_admin_approve_process_paid_requires_reauth_and_reference(): void
    {
        [$u, , $r] = $this->request(300000, 100000);
        $w = $r->json('data');
        $ap = Approval::where('type', 'withdrawal')->firstOrFail();
        $this->admin();
        $this->withSession(['admin_mfa_passed' => true, 'admin_last_seen' => time(), 'admin_reauth_at' => time() - 3600]);
        $this->json_('POST', "/admin/approvals/{$ap->id}/decide", ['decision' => 'approve'])->assertStatus(403)->assertJsonPath('code', 'reauth_required');
        $this->withSession(['admin_mfa_passed' => true, 'admin_last_seen' => time(), 'admin_reauth_at' => time()]);
        $this->json_('POST', "/admin/approvals/{$ap->id}/decide", ['decision' => 'approve'])->assertNoContent();
        $this->json_('POST', "/admin/withdrawals/{$w['id']}/status", ['status' => 'paid', 'reference' => 'X'])->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
        $this->json_('POST', "/admin/withdrawals/{$w['id']}/status", ['status' => 'processing'])->assertOk();
        $this->json_('POST', "/admin/withdrawals/{$w['id']}/status", ['status' => 'paid'])->assertStatus(422);
        $this->json_('POST', "/admin/withdrawals/{$w['id']}/status", ['status' => 'paid', 'reference' => 'TRF-BCA-777'])->assertOk()->assertJsonPath('data.status', 'paid');
        $this->assertSame(200000, Wallet::balance($u->id, 'commission'));
        $this->assertTrue(AuditLog::where('action', 'admin.withdrawal.paid')->exists());
        $this->assertDatabaseHas('mail_logs', ['to' => $u->email, 'template' => 'withdrawal_paid', 'status' => 'sent']);
    }

    public function test_d4_admin_balance_adjustment_is_ledger_entry_with_reason(): void
    {
        $u = $this->user();
        $this->admin();
        $this->json_('POST', "/admin/users/{$u->id}/balance", ['wallet' => 'topup', 'direction' => 'credit', 'amount' => 25000, 'reason' => 'abc'])->assertStatus(422);
        $this->json_('POST', "/admin/users/{$u->id}/balance", ['wallet' => 'topup', 'direction' => 'credit', 'amount' => 25000, 'reason' => 'Kompensasi gangguan layanan'])->assertNoContent();
        $this->assertDatabaseHas('wallet_ledger', ['user_id' => $u->id, 'amount' => 25000, 'ref_type' => 'admin_adjustment', 'reason' => 'Kompensasi gangguan layanan']);
        $this->json_('POST', "/admin/users/{$u->id}/reveal", ['field' => 'email'])->assertOk()->assertJsonPath('data.value', $u->email);
        $this->assertTrue(AuditLog::where('action', 'admin.users.reveal')->exists());
    }
}
