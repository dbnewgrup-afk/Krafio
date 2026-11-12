<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\MailLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing;
use App\Services\Wallet;

/** Blueprint "Siklus Langganan & Pemantauan Perpanjangan" dan "Saldo, Komisi Affiliate & Withdrawal". */
class LifecycleAndAffiliateTest extends KrafioTestCase
{
    private function buyStarter(User $u): Subscription
    {
        $this->actingUser($u)->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => 'starter', 'cycle' => 'monthly', 'source' => 'balance'])->assertOk();

        return $u->fresh()->activeSubscription();
    }

    private function titles(User $u, string $title): int
    {
        return $u->notifications()->where('data->title', $title)->count();
    }

    public function test_reminder_is_sent_once_per_stage_by_email_and_never_doubled_when_scheduler_skips(): void
    {
        $u = $this->user([], 'free', 500000);
        $s = $this->buyStarter($u);

        // Scheduler "terlewat" sampai H-2: hanya SATU pengingat (tahap H-3), bukan H-7 + H-3 sekaligus
        $this->travelTo($s->ends_at->copy()->subDays(2)->subHour());
        Billing::renewals();
        Billing::renewals();
        $this->assertSame(1, $this->titles($u, 'Paket segera berakhir'));
        $this->assertSame(2, $s->fresh()->reminders_sent);
        $mail = MailLog::where('user_id', $u->id)->where('template', 'subscription_expiring')->latest('id')->first();
        $this->assertNotNull($mail, 'Pengingat juga dikirim lewat email');
        $this->assertSame('sent', $mail->status);
        $this->assertStringContainsString('3 hari', $mail->subject, 'H-2 lewat 1 jam dibulatkan ke atas');

        $this->travelTo($s->ends_at->copy()->subHours(20));
        Billing::renewals();
        $this->assertSame(2, $this->titles($u, 'Paket segera berakhir'), 'H-1 tetap dikirim satu kali');
        $this->travelBack();
    }

    public function test_dashboard_renewal_banner_reports_auto_renew_and_balance(): void
    {
        $u = $this->user([], 'free', 500000);
        $s = $this->buyStarter($u);
        $this->assertNull($this->actingUser($u)->json_('GET', '/me')->json('data.renewal'), 'Belum ≤ 7 hari → tanpa banner');

        $this->json_('PATCH', '/app/subscription/auto-renew', ['enabled' => true])->assertSuccessful();
        $this->travelTo($s->ends_at->copy()->subDays(3));
        $r = $this->actingUser($u->fresh())->json_('GET', '/me')->assertOk()->json('data.renewal');
        $this->assertSame('expiring', $r['state']);
        $this->assertSame(3, $r['days']);
        $this->assertTrue($r['auto']);
        $this->assertSame((int) Plan::where('code', 'starter')->value('price_monthly'), $r['renew_price']);

        $this->json_('PATCH', '/app/subscription/auto-renew', ['enabled' => false])->assertSuccessful();
        $this->assertFalse($this->json_('GET', '/me')->json('data.renewal.auto'));
        $this->travelBack();
    }

    public function test_failed_renewal_goes_past_due_then_back_to_free_with_proper_notifications(): void
    {
        $price = (int) Plan::where('code', 'starter')->value('price_monthly');
        $u = $this->user([], 'free', $price); // cukup untuk beli, tidak cukup untuk perpanjang
        $s = $this->buyStarter($u);
        $this->assertSame(0, Wallet::balance($u->id, 'topup'));
        $this->json_('PATCH', '/app/subscription/auto-renew', ['enabled' => true])->assertSuccessful();

        $this->travelTo($s->ends_at->copy()->addHour());
        Billing::renewals();
        $this->assertSame('past_due', $s->fresh()->status);
        $this->assertSame(1, $this->titles($u, 'Perpanjangan gagal'));
        $this->assertTrue(MailLog::where('user_id', $u->id)->where('template', 'renewal_failed')->exists(), 'Email perpanjangan gagal (kategori transaksi wajib)');
        $banner = $this->actingUser($u->fresh())->json_('GET', '/me')->json('data.renewal');
        $this->assertSame('past_due', $banner['state']);
        $this->assertNotNull($banner['grace_until']);

        $this->travelTo($s->ends_at->copy()->addDays(4));
        $welcomeBefore = $this->titles($u, 'Selamat datang di Krafio');
        Billing::renewals();
        $this->assertSame('expired', $s->fresh()->status);
        $this->assertSame('free', $u->fresh()->activeSubscription()->plan_code);
        $this->assertSame(1, $this->titles($u, 'Paket berakhir'));
        $this->assertSame($welcomeBefore, $this->titles($u, 'Selamat datang di Krafio'), 'Tidak ada "Selamat datang" saat turun ke Gratis');
        $this->assertNull($this->actingUser($u->fresh())->json_('GET', '/me')->json('data.renewal'));
        $this->travelBack();
    }

    public function test_admin_granted_plan_is_reminded_but_never_charged(): void
    {
        $u = $this->user([], 'free', 500000);
        $this->admin();
        $this->json_('POST', "/admin/users/{$u->id}/features", ['mode' => 'plan', 'plan_code' => 'pro', 'days' => 10, 'reason' => 'Kompensasi gangguan'])->assertOk();
        $s = $u->fresh()->activeSubscription();

        $this->travelTo($s->ends_at->copy()->subDays(5));
        Billing::renewals();
        $this->assertSame(1, $this->titles($u, 'Paket segera berakhir'));
        $this->assertTrue($this->actingUser($u->fresh())->json_('GET', '/me')->json('data.renewal.granted_by_admin'));

        $this->travelTo($s->ends_at->copy()->addDays(4));
        Billing::renewals();
        $this->assertSame('free', $u->fresh()->activeSubscription()->plan_code);
        $this->assertSame(500000, Wallet::balance($u->id, 'topup'), 'Saldo tidak didebit');
        $this->travelBack();
    }

    public function test_admin_reverses_commission_only_during_hold_and_affiliate_is_notified(): void
    {
        $ref = $this->user();
        $buyer = $this->user(['referred_by' => $ref->id], 'free', 500000);
        $buyer->forceFill(['referred_by' => $ref->id])->save();
        $this->buyStarter($buyer->fresh());
        $c = Commission::where('referrer_id', $ref->id)->firstOrFail();
        $this->assertSame('pending', $c->status);

        $this->admin();
        $this->json_('PUT', "/admin/r/commissions/{$c->id}", ['status' => 'reversed'])->assertStatus(422)->assertJsonValidationErrors('reversal_reason');
        $this->json_('PUT', "/admin/r/commissions/{$c->id}", ['status' => 'reversed', 'reversal_reason' => 'Pembeli mengajukan refund', 'amount' => 1])->assertOk();
        $c->refresh();
        $this->assertSame(['reversed', 'Pembeli mengajukan refund'], [$c->status, $c->reversal_reason]);
        $this->assertNotNull($c->reversed_at);
        $this->assertNotSame(1, (int) $c->amount, 'Nominal tidak bisa diubah');
        $this->assertSame(1, $this->titles($ref, 'Komisi dibatalkan'));
        $this->assertTrue(MailLog::where('user_id', $ref->id)->where('template', 'commission_reversed')->exists());

        // Komisi dibatalkan tidak pernah dilepas ke saldo
        $this->travel(15)->days();
        Billing::releaseCommissions();
        $this->assertSame(0, Wallet::balance($ref->id, 'commission'));
        $this->assertSame('reversed', $c->fresh()->status);

        // Komisi yang sudah tersedia tidak bisa dibatalkan lewat sini
        $buyer2 = $this->user([], 'free', 500000);
        $buyer2->forceFill(['referred_by' => $ref->id])->save();
        $this->buyStarter($buyer2->fresh());
        Billing::releaseCommissions();
        $this->travel(15)->days();
        Billing::releaseCommissions();
        $c2 = Commission::where('referrer_id', $ref->id)->where('id', '!=', $c->id)->firstOrFail();
        $this->assertSame('available', $c2->status);
        $this->admin();
        $this->json_('PUT', "/admin/r/commissions/{$c2->id}", ['status' => 'reversed', 'reversal_reason' => 'Coba batalkan'])->assertStatus(422)->assertJsonPath('code', 'not_reversible');
        $this->travelBack();
    }
}
