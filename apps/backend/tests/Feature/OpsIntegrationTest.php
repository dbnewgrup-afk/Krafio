<?php

namespace Tests\Feature;

use App\Models\Integration;
use App\Models\MailLog;
use App\Models\WaLog;
use App\Services\Backups;
use App\Services\Messenger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Alur E — Integrasi via Admin (payment/SMTP/WhatsApp), log, laporan, backup & retensi, CRUD admin. */
class OpsIntegrationTest extends KrafioTestCase
{
    public function test_e1_integration_secrets_are_encrypted_and_never_returned(): void
    {
        $this->admin();
        $this->json_('PUT', '/admin/integrations/payment', ['driver' => 'xendit', 'values' => ['secret_key' => 'xnd_rahasia', 'callback_token' => 'cb'], 'is_active' => true])->assertOk()->assertJsonPath('data.values.secret_key', '__set__');
        $raw = DB::table('integrations')->where('kind', 'payment')->value('config');
        $this->assertStringNotContainsString('xnd_rahasia', $raw, 'Credential tersimpan terenkripsi');
        // simpan ulang tanpa mengisi secret → secret lama dipertahankan
        $this->json_('PUT', '/admin/integrations/payment', ['driver' => 'xendit', 'values' => ['secret_key' => '__set__', 'callback_token' => ''], 'is_active' => true])->assertOk();
        $this->assertSame('xnd_rahasia', Integration::where('kind', 'payment')->first()->config['secret_key']);
        // aktif tanpa field wajib ditolak
        $this->json_('PUT', '/admin/integrations/payment', ['driver' => 'tripay', 'values' => [], 'is_active' => true])->assertStatus(422);
    }

    public function test_e2_payment_and_whatsapp_connection_tests(): void
    {
        $this->admin();
        Http::fake(['api.xendit.co/*' => Http::response(['balance' => 0], 200), 'graph.facebook.com/*' => Http::response(['verified_name' => 'Krafio', 'display_phone_number' => '+62 811'], 200)]);
        $this->json_('PUT', '/admin/integrations/payment', ['driver' => 'xendit', 'values' => ['secret_key' => 'k', 'callback_token' => 't'], 'is_active' => true]);
        $this->json_('POST', '/admin/integrations/payment/test')->assertOk()->assertJsonPath('data.ok', true);
        $this->json_('PUT', '/admin/integrations/whatsapp', ['driver' => 'cloud_api', 'values' => ['phone_number_id' => '1', 'business_account_id' => '2', 'access_token' => 'tok'], 'is_active' => true]);
        $this->json_('POST', '/admin/integrations/whatsapp/test')->assertOk()->assertJsonPath('data.ok', true);
        $this->assertSame('success', Integration::where('kind', 'whatsapp')->value('last_test_status'));
    }

    public function test_e3_smtp_test_email_is_sent_and_logged(): void
    {
        $this->admin();
        $this->json_('PUT', '/admin/integrations/smtp', ['driver' => 'smtp', 'values' => ['host' => 'localhost', 'port' => 1025, 'encryption' => 'none', 'from_address' => 'no-reply@krafio.test', 'from_name' => 'Krafio'], 'is_active' => false])->assertOk();
        $this->json_('POST', '/admin/integrations/smtp/test')->assertStatus(422)->assertJsonPath('code', 'not_active');
        // Pengiriman via mailer default (array di testing) tetap tercatat
        Messenger::email('uji@krafio.test', 'test', ['name' => 'Uji', 'time' => 'sekarang']);
        $this->assertDatabaseHas('mail_logs', ['to' => 'uji@krafio.test', 'template' => 'test', 'status' => 'sent']);
        Messenger::email('uji@krafio.test', 'tidak_ada');
        $this->assertDatabaseHas('mail_logs', ['template' => 'tidak_ada', 'status' => 'skipped']);
    }

    public function test_e4_whatsapp_template_send_success_failure_and_skip_are_logged(): void
    {
        Messenger::whatsapp('+628111', 'otp', ['code' => '111111']);
        $this->assertSame('skipped', WaLog::latest('id')->value('status'), 'Integrasi belum aktif → dilewati & tercatat');
        $this->admin();
        $this->json_('PUT', '/admin/integrations/whatsapp', ['driver' => 'cloud_api', 'values' => ['phone_number_id' => '99', 'business_account_id' => '2', 'access_token' => 'tok'], 'is_active' => true]);
        Http::fake(['graph.facebook.com/*' => Http::sequence()->push(['messages' => [['id' => 'wamid.1']]], 200)->push(['error' => ['message' => 'Template tidak ditemukan']], 400)]);
        [$ok] = Messenger::whatsapp('+62 811-1111', 'otp', ['code' => '222222']);
        $this->assertTrue($ok);
        $this->assertDatabaseHas('wa_logs', ['to' => '628111111', 'status' => 'sent', 'provider_message_id' => 'wamid.1']);
        [$ok2] = Messenger::whatsapp('+628111111', 'topup_success', ['name' => 'A', 'amount' => 'Rp1', 'reference' => 'R']);
        $this->assertFalse($ok2);
        $this->assertDatabaseHas('wa_logs', ['status' => 'failed', 'error' => 'Template tidak ditemukan']);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/99/messages') && $r['template']['name'] === 'krafio_otp');
        $this->json_('GET', '/admin/r/wa-logs')->assertOk()->assertJsonPath('meta.total', 3);
    }

    public function test_e5_finance_report_and_csv_export(): void
    {
        $u = $this->user([], 'free', 200000);
        $this->actingUser($u)->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => 'starter', 'cycle' => 'monthly', 'source' => 'balance'])->assertOk();
        $this->admin();
        $r = $this->json_('GET', '/admin/reports/finance')->assertOk();
        $this->assertSame(59000, $r->json('data.totals.sales'));
        $this->assertSame(141000, $r->json('data.outstanding.topup_balance'));
        $csv = $this->get('/api/admin/reports/finance?export=csv');
        $csv->assertOk();
        $this->assertStringContainsString('Periode', $csv->streamedContent());
    }

    public function test_e6_prune_removes_old_logs_but_keeps_financial_data(): void
    {
        $u = $this->user([], 'free', 50000);
        MailLog::create(['to' => 'a@b.c', 'subject' => 'lama', 'mailer' => 'x', 'status' => 'sent', 'created_at' => now()->subDays(200)]);
        MailLog::create(['to' => 'a@b.c', 'subject' => 'baru', 'mailer' => 'x', 'status' => 'sent']);
        DB::table('wallet_ledger')->where('user_id', $u->id)->update(['created_at' => now()->subYears(3)]);
        $out = Backups::prune();
        $this->assertSame(1, $out['mail_logs']);
        $this->assertSame(0, MailLog::where('subject', 'lama')->count());
        $this->assertSame(1, MailLog::where('subject', 'baru')->count());
        $this->assertSame(1, DB::table('wallet_ledger')->where('user_id', $u->id)->count(), 'Ledger keuangan tidak pernah dipangkas');
    }

    public function test_e7_backup_rotation_keeps_daily_and_weekly(): void
    {
        \App\Services\Settings::set('backup.keep_daily', 2);
        \App\Services\Settings::set('backup.keep_weekly', 1);
        foreach ([0, 1, 2, 3, 9] as $daysAgo) {
            $f = "db-uji-{$daysAgo}.sql.gz";
            file_put_contents(Backups::dir().DIRECTORY_SEPARATOR.$f, 'x');
            \App\Models\Backup::create(['type' => 'database', 'file' => $f, 'size' => 1, 'status' => 'success', 'created_at' => now()->startOfWeek()->subDays($daysAgo)]);
        }
        $deleted = Backups::rotate();
        $this->assertGreaterThanOrEqual(2, $deleted);
        $this->assertLessThanOrEqual(3, \App\Models\Backup::count());
        foreach (glob(Backups::dir().DIRECTORY_SEPARATOR.'db-uji-*') as $f) {
            @unlink($f);
        }
    }

    public function test_e8_admin_resource_crud_is_validated_and_audited(): void
    {
        $this->admin();
        $c = $this->json_('POST', '/admin/r/coupons', ['code' => 'HEMAT10', 'type' => 'percent', 'value' => 10, 'duration' => 'once', 'is_active' => true])->assertCreated()->json('data');
        $this->json_('POST', '/admin/r/coupons', ['code' => 'HEMAT10', 'type' => 'percent', 'value' => 10, 'duration' => 'once'])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->json_('PUT', "/admin/r/coupons/{$c['id']}", ['value' => 15])->assertOk()->assertJsonPath('data.value', 15);
        $this->json_('POST', '/admin/r/ledger', [])->assertStatus(405)->assertJsonPath('code', 'read_only');
        $this->json_('POST', '/admin/r/coupons/bulk', ['ids' => [$c['id']], 'action' => 'delete'])->assertStatus(422);
        $this->json_('POST', '/admin/r/coupons/bulk', ['ids' => [$c['id']], 'action' => 'delete', 'confirm' => 'HAPUS'])->assertNoContent();
        $this->assertTrue(\App\Models\AuditLog::where('action', 'admin.coupons.created')->exists());
        $this->json_('GET', '/admin/r/ai-providers')->assertOk();
        $this->json_('POST', '/admin/r/ai-providers', ['name' => 'OpenAI', 'driver' => 'openai_compatible', 'capabilities' => ['text'], 'status' => 'draft', 'api_key' => 'sk-rahasia'])->assertCreated()->assertJsonMissingPath('data.api_key')->assertJsonPath('data.api_key_set', true);
    }
}
