<?php

namespace Tests\Feature;

use App\Models\AiJob;
use App\Models\AuditLog;
use App\Models\CreditPack;
use App\Models\LedgerEntry;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\UsageCounter;
use App\Models\User;
use App\Services\Audit;
use App\Services\Credits;
use App\Services\Entitlements;

/** Kontrol data manual di menu Admin: hak per resource (lihat/tambah/ubah/hapus) + efek sampingnya. */
class AdminResourceControlTest extends KrafioTestCase
{
    public function test_financial_and_log_resources_stay_read_only(): void
    {
        $u = $this->user([], 'free', 50000);
        $this->admin();
        $entry = LedgerEntry::where('user_id', $u->id)->firstOrFail();
        $this->json_('GET', '/admin/r/ledger/'.$entry->id)->assertOk();
        $this->json_('PUT', '/admin/r/ledger/'.$entry->id, ['amount' => 1])->assertStatus(405);
        $this->json_('DELETE', '/admin/r/ledger/'.$entry->id)->assertStatus(405);
        $this->json_('POST', '/admin/r/ledger/bulk', ['ids' => [$entry->id], 'action' => 'delete', 'confirm' => 'HAPUS'])->assertStatus(405);
        Audit::log('test.event', $u);
        $log = AuditLog::firstOrFail();
        $this->json_('DELETE', '/admin/r/audit-logs/'.$log->id)->assertStatus(405);
        $this->json_('GET', '/admin/r/payments')->assertOk();
    }

    public function test_admin_grants_bonus_credits_through_ledger(): void
    {
        $u = $this->user();
        $before = Credits::summary($u)['remaining'];
        $this->admin();
        $r = $this->json_('POST', '/admin/r/credit-packs', ['user_id' => $u->id, 'credits_total' => 5000, 'label' => 'Kredit uji QA'])->assertCreated();
        $this->assertSame('bonus', $r->json('data.source'));
        $this->assertSame($before + 5000, Credits::summary($u->fresh())['remaining']);
        $this->assertDatabaseHas('credit_ledger', ['user_id' => $u->id, 'direction' => 'credit', 'amount' => 5000]);

        // Jumlah kredit tidak bisa diubah setelah dibuat (hanya label & masa berlaku)
        $this->json_('PUT', '/admin/r/credit-packs/'.$r->json('data.id'), ['credits_total' => 999999, 'label' => 'Ganti label'])->assertOk();
        $this->assertSame(5000, CreditPack::find($r->json('data.id'))->credits_total);

        $admin = User::where('role', 'admin')->first();
        $this->json_('POST', '/admin/r/credit-packs', ['user_id' => $admin->id, 'credits_total' => 10, 'label' => 'x'])->assertStatus(422)->assertJsonPath('code', 'admin_no_features');
    }

    public function test_usage_counter_can_be_corrected(): void
    {
        $u = $this->user();
        Entitlements::consume($u, 'chatbot.basic', 4);
        $row = UsageCounter::where('user_id', $u->id)->firstOrFail();
        $this->admin();
        $this->json_('PUT', '/admin/r/usage/'.$row->id, ['used' => 1])->assertOk();
        $this->assertSame(1, $row->fresh()->used);
        $this->json_('PUT', '/admin/r/usage/'.$row->id, ['used' => -3])->assertStatus(422);
        $this->json_('DELETE', '/admin/r/usage/'.$row->id)->assertNoContent();
    }

    public function test_stuck_job_stopped_by_admin_refunds_credits_once(): void
    {
        $u = $this->user();
        Credits::ensurePlanPack($u);
        $alloc = Credits::consume($u, 10, 'ai_job', null, 'uji');
        $job = AiJob::create(['user_id' => $u->id, 'type' => 'photo_generate', 'entitlement' => 'photo.generate', 'prompt' => 'kopi', 'params' => [], 'status' => 'processing', 'cost' => 10, 'credit_alloc' => $alloc]);
        $left = Credits::summary($u)['remaining'];

        $this->admin();
        $this->json_('PUT', '/admin/r/ai-jobs/'.$job->id, ['status' => 'cancelled'])->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame($left + 10, Credits::summary($u->fresh())['remaining']);
        $this->assertTrue((bool) $job->fresh()->quota_refunded);
        // Sudah berhenti → tidak bisa diubah lagi (tidak ada refund ganda)
        $this->json_('PUT', '/admin/r/ai-jobs/'.$job->id, ['status' => 'failed'])->assertStatus(422)->assertJsonPath('code', 'not_cancellable');
        $this->assertSame($left + 10, Credits::summary($u->fresh())['remaining']);
    }

    public function test_projects_websites_and_subscriptions_are_editable_with_limited_fields(): void
    {
        $u = $this->user();
        $p = Project::create(['user_id' => $u->id, 'name' => 'Lama', 'type' => 'photo', 'status' => 'active']);
        $this->admin();
        $this->json_('PUT', '/admin/r/projects/'.$p->id, ['name' => 'Baru', 'status' => 'archived', 'user_id' => 999])->assertOk();
        $this->assertSame(['Baru', 'archived', $u->id], [$p->fresh()->name, $p->fresh()->status, $p->fresh()->user_id], 'Field di luar aturan diabaikan');
        $this->json_('DELETE', '/admin/r/projects/'.$p->id)->assertNoContent();
        $this->assertSoftDeleted('projects', ['id' => $p->id]);

        $sub = Subscription::where('user_id', $u->id)->firstOrFail();
        $this->json_('PUT', '/admin/r/subscriptions/'.$sub->id, ['status' => 'cancelled', 'price' => 1])->assertOk();
        $this->assertSame(['cancelled', 0], [$sub->fresh()->status, (int) $sub->fresh()->price]);
        $this->json_('DELETE', '/admin/r/subscriptions/'.$sub->id)->assertStatus(405);
        $this->json_('POST', '/admin/r/subscriptions', ['status' => 'active'])->assertStatus(405);
        $this->assertGreaterThan(0, AuditLog::where('action', 'admin.subscriptions.updated')->count());
    }
}
