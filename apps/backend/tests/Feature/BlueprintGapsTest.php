<?php

namespace Tests\Feature;

use App\Models\AiJob;
use App\Models\AiModel;
use App\Models\AiPrompt;
use App\Models\AiProvider;
use App\Models\Approval;
use App\Models\Commission;
use App\Models\CreditLedgerEntry;
use App\Models\CreditPack;
use App\Models\Payment;
use App\Models\User;
use App\Models\Website;
use App\Models\Withdrawal;
use App\Services\Billing;
use App\Services\Credits;
use App\Services\CustomDomains;
use App\Services\Ops;
use App\Services\Wallet;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/** Celah blueprint yang ditutup: refund, FIFO withdrawal, margin AI, driver media + watermark, prompt library, domain kustom, laporan. */
class BlueprintGapsTest extends KrafioTestCase
{
    private function buy(User $u, string $code = 'starter'): Payment
    {
        $this->actingUser($u)->json_('POST', '/app/orders', ['kind' => 'plan', 'code' => $code, 'cycle' => 'monthly', 'source' => 'balance'])->assertOk();

        return Payment::where('user_id', $u->id)->latest('id')->firstOrFail();
    }

    private function png(int $w = 64, int $h = 64): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 30, 60, 200));
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    /* ---------------- Refund ---------------- */

    public function test_balance_purchase_is_recorded_as_payment_and_refund_reverses_everything(): void
    {
        $ref = $this->user();
        $u = $this->user([], 'free', 500000);
        $u->forceFill(['referred_by' => $ref->id])->save();
        $p = $this->buy($u->fresh());
        $this->assertSame(['balance', 'paid', 'subscription'], [$p->gateway, $p->status, $p->purpose]);
        $sub = $u->fresh()->activeSubscription();
        $this->assertSame($p->id, $sub->payment_id, 'Paket tertaut ke pembayaran asal');
        Credits::ensurePlanPack($u->fresh());
        $this->assertSame('pending', Commission::where('payment_id', $p->id)->value('status'));
        $balanceAfterBuy = Wallet::balance($u->id, 'topup');

        // Riwayat + pengajuan
        $hist = collect($this->actingUser($u)->json_('GET', '/app/payments-history')->assertOk()->json('data'));
        $this->assertTrue($hist->firstWhere('id', $p->id)['refundable']);
        $this->json_('POST', "/app/payments/{$p->id}/refund", ['reason' => 'pendek'])->assertStatus(422);
        $this->json_('POST', "/app/payments/{$p->id}/refund", ['reason' => 'Salah pilih paket, ingin ganti ke Pro'])->assertCreated();
        $this->json_('POST', "/app/payments/{$p->id}/refund", ['reason' => 'Salah pilih paket, ingin ganti ke Pro'])->assertStatus(422)->assertJsonPath('code', 'refund_not_allowed');
        $this->actingUser($this->user())->json_('POST', "/app/payments/{$p->id}/refund", ['reason' => 'Bukan pembayaran saya sama sekali'])->assertNotFound();

        // Admin menyetujui (wajib re-auth → helper admin() sudah re-auth)
        $a = Approval::where('type', 'refund')->where('subject_id', $p->id)->firstOrFail();
        $this->admin();
        $this->json_('POST', "/admin/approvals/{$a->id}/decide", ['decision' => 'approve'])->assertSuccessful();

        $p->refresh();
        $this->assertSame(['refunded', 'balance'], [$p->status, $p->refund_method]);
        $this->assertSame($balanceAfterBuy + (int) $p->amount, Wallet::balance($u->id, 'topup'), 'Dana kembali ke saldo top-up (ledger baru)');
        $this->assertSame('cancelled', $sub->fresh()->status);
        $this->assertSame('free', $u->fresh()->activeSubscription()->plan_code);
        $this->assertSame(0, CreditPack::where('subscription_id', $sub->id)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(), 'Kredit paket ikut kedaluwarsa');
        $this->assertSame('reversed', Commission::where('payment_id', $p->id)->value('status'), 'Komisi affiliate dibatalkan otomatis');
        $this->assertSame(1, $u->notifications()->where('data->title', 'Refund disetujui')->count());
        $h = $this->actingUser($u->fresh())->json_('GET', '/app/payments-history');
        $this->assertSame(200, $h->status(), (string) $h->getContent());
        $this->assertFalse(collect($h->json('data'))->firstWhere('id', $p->id)['refundable']);
    }

    public function test_refund_window_and_rejection(): void
    {
        $u = $this->user([], 'free', 500000);
        $p = $this->buy($u);
        $this->travel(8)->days();
        $this->actingUser($u)->json_('POST', "/app/payments/{$p->id}/refund", ['reason' => 'Terlambat mengajukan refund'])->assertStatus(422);
        $this->travelBack();

        $this->actingUser($u)->json_('POST', "/app/payments/{$p->id}/refund", ['reason' => 'Alasan cukup panjang untuk refund'])->assertCreated();
        $a = Approval::where('type', 'refund')->firstOrFail();
        $this->admin();
        $this->json_('POST', "/admin/approvals/{$a->id}/decide", ['decision' => 'reject', 'reason' => 'Paket sudah dipakai penuh'])->assertSuccessful();
        $this->assertSame('paid', $p->fresh()->status);
        $this->assertSame(1, $u->notifications()->where('data->title', 'Refund ditolak')->count());
    }

    public function test_topup_refund_requires_transfer_reference_and_debits_balance(): void
    {
        $u = $this->user();
        $p = Payment::create(['user_id' => $u->id, 'reference' => 'KRFTEST01', 'purpose' => 'topup', 'amount' => 100000, 'method' => 'va', 'gateway' => 'manual', 'status' => 'pending', 'expires_at' => now()->addDay()]);
        Billing::markPaid($p->id);
        $this->actingUser($u)->json_('POST', "/app/payments/{$p->id}/refund", ['reason' => 'Top-up dobel tidak sengaja'])->assertCreated();
        $a = Approval::where('type', 'refund')->firstOrFail();
        $this->admin();
        $this->json_('POST', "/admin/approvals/{$a->id}/decide", ['decision' => 'approve'])->assertStatus(422)->assertJsonValidationErrors('reference');
        $this->json_('POST', "/admin/approvals/{$a->id}/decide", ['decision' => 'approve', 'reference' => 'TRX-BCA-99'])->assertSuccessful();
        $this->assertSame(0, Wallet::balance($u->id, 'topup'));
        $this->assertSame(['refunded', 'transfer', 'TRX-BCA-99'], [$p->fresh()->status, $p->fresh()->refund_method, $p->fresh()->refund_reference]);
    }

    public function test_refund_after_commission_released_claws_back_from_commission_balance(): void
    {
        $ref = $this->user();
        $u = $this->user([], 'free', 500000);
        $u->forceFill(['referred_by' => $ref->id])->save();
        $p = $this->buy($u->fresh());
        $this->travel(15)->days();
        Billing::releaseCommissions();
        $amount = (int) Commission::where('payment_id', $p->id)->value('amount');
        $this->assertSame($amount, Wallet::balance($ref->id, 'commission'));
        $this->travelBack();

        $p->forceFill(['paid_at' => now()])->save(); // masih dalam masa refund untuk skenario chargeback
        $this->actingUser($u)->json_('POST', "/app/payments/{$p->id}/refund", ['reason' => 'Chargeback dari bank penerbit'])->assertCreated();
        $this->admin();
        $this->json_('POST', '/admin/approvals/'.Approval::where('type', 'refund')->value('id').'/decide', ['decision' => 'approve'])->assertSuccessful();
        $this->assertSame(0, Wallet::balance($ref->id, 'commission'), 'Komisi yang sudah tersedia ditarik kembali lewat ledger koreksi');
        $this->assertSame('reversed', Commission::where('payment_id', $p->id)->value('status'));
    }

    /* ---------------- Withdrawal FIFO ---------------- */

    public function test_withdrawal_paid_marks_only_commissions_it_covers_oldest_first(): void
    {
        $ref = $this->user();
        foreach ([1, 2, 3] as $i) {
            $b = $this->user([], 'free', 500000);
            $b->forceFill(['referred_by' => $ref->id])->save();
            $this->buy($b->fresh(), 'pro');
            $this->travel(1)->hours();
        }
        $this->travel(15)->days();
        Billing::releaseCommissions();
        $each = (int) Commission::where('referrer_id', $ref->id)->value('amount');
        $this->travelBack();

        $w = Withdrawal::create(['user_id' => $ref->id, 'destination_id' => null, 'destination_snapshot' => [], 'amount' => $each * 2, 'status' => 'processing']);
        $this->admin();
        $this->json_('POST', "/admin/withdrawals/{$w->id}/status", ['status' => 'paid', 'reference' => 'TRX-1'])->assertOk();
        $this->assertSame(2, Commission::where('referrer_id', $ref->id)->where('status', 'paid')->count());
        $this->assertSame(1, Commission::where('referrer_id', $ref->id)->where('status', 'available')->count());
    }

    /* ---------------- Margin AI ---------------- */

    public function test_ai_margin_alert_appears_and_notifies_admins_once_per_day(): void
    {
        $u = $this->user();
        Credits::consume($u, 10, 'ai_job', null, 'uji'); // pendapatan 10 × Rp180 = Rp1.800
        AiProvider::create(['name' => 'Mahal', 'driver' => 'custom_http', 'base_url' => 'https://ai.test', 'capabilities' => ['image'], 'status' => 'active', 'priority' => 1, 'cost_month' => 1500]);
        $m = Ops::aiMargin();
        $this->assertSame([1800, 1500], [$m['revenue'], $m['cost']]);
        $this->assertTrue($m['low'], 'Margin 16,7% < 55%');

        $this->admin();
        $item = collect($this->json_('GET', '/admin/ops/attention')->json('data'))->firstWhere('key', 'ai_margin_low');
        $this->assertSame(1, $item['count']);
        $this->assertTrue(Ops::alertLowMargin());
        $this->assertFalse(Ops::alertLowMargin(), 'Sekali per hari');
        Ops::resetProviderCounters();
        $this->assertSame(0, (int) AiProvider::value('cost_month'));
    }

    /* ---------------- Driver media, watermark, fallback ---------------- */

    private function comfyProvider(int $priority = 1): AiProvider
    {
        $p = AiProvider::create(['name' => 'ComfyUI lokal', 'driver' => 'comfyui', 'base_url' => 'http://comfy.test', 'capabilities' => ['image'], 'status' => 'active', 'priority' => $priority]);
        AiModel::create(['provider_id' => $p->id, 'name' => 'SDXL', 'model_id' => 'sdxl.safetensors', 'capability' => 'image', 'credit_cost' => 8, 'cost_idr' => 300, 'is_active' => true]);
        AiPrompt::create(['key' => 'comfyui.photo_generate', 'name' => 'WF', 'version' => '1.0', 'status' => 'active',
            'template' => '{"3":{"class_type":"KSampler","inputs":{"seed":{{seed}},"text":"{{prompt}}","w":{{width}},"ckpt":"{{model}}"}}}']);

        return $p;
    }

    public function test_comfyui_generates_stores_watermarks_and_serves_only_to_owner(): void
    {
        Storage::fake('local');
        $this->comfyProvider();
        $png = $this->png(300, 300);
        Http::fake([
            'comfy.test/prompt' => Http::response(['prompt_id' => 'abc']),
            'comfy.test/history/abc' => Http::response(['abc' => ['outputs' => ['9' => ['images' => [['filename' => 'k_0001.png', 'subfolder' => '', 'type' => 'output']]]]]]),
            'comfy.test/view*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);
        $u = $this->user();
        $id = $this->actingUser($u)->json_('POST', '/app/jobs', ['type' => 'photo_generate', 'prompt' => 'Kopi "susu" gula aren', 'params' => ['count' => '1', 'ratio' => '1:1']])->assertCreated()->json('data.id');
        $job = AiJob::findOrFail($id);
        $this->assertSame('completed', $job->status);
        $this->assertSame(300, $job->provider_cost);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/prompt') && str_contains($r->body(), 'Kopi \\"susu\\" gula aren') && str_contains($r->body(), 'sdxl.safetensors'));
        $url = $job->result['preview_images'][0];
        $this->assertSame("/api/app/jobs/{$id}/files/0", $url);
        $this->assertArrayNotHasKey('files', $this->json_('GET', '/app/jobs')->json('data.0.result') ?? [], 'Path storage tidak dikirim ke browser');

        $res = $this->get($url)->assertOk();
        $this->assertSame('image/png', $res->headers->get('Content-Type'));
        $this->assertNotSame($png, Storage::disk('local')->get($job->result['files'][0]['path']), 'Paket Gratis → hasil diberi watermark');
        $this->actingUser($this->user())->get($url)->assertNotFound();
        $this->assertSame(300, (int) AiProvider::value('cost_month'));
    }

    public function test_media_falls_back_to_next_provider_and_refunds_when_all_fail(): void
    {
        Storage::fake('local');
        $this->comfyProvider(1);
        $http = AiProvider::create(['name' => 'HTTP cadangan', 'driver' => 'custom_http', 'base_url' => 'https://gen.test/run', 'capabilities' => ['image'], 'status' => 'active', 'priority' => 2]);
        AiModel::create(['provider_id' => $http->id, 'name' => 'Cadangan', 'model_id' => 'img-v1', 'capability' => 'image', 'credit_cost' => 8, 'cost_idr' => 100, 'is_active' => true]);
        $allDown = false; // satu fake ber-saklar (Http::fake kedua dalam satu test diabaikan Laravel)
        $png = $this->png();
        Http::fake(function ($req) use (&$allDown, $png) {
            if ($allDown || str_contains($req->url(), 'comfy.test')) {
                return Http::response('down', 500);
            }

            return Http::response(['outputs' => [['base64' => base64_encode($png), 'mime' => 'image/png']]]);
        });
        $u = $this->user([], 'free');
        $id = $this->actingUser($u)->json_('POST', '/app/jobs', ['type' => 'photo_generate', 'prompt' => 'Roti', 'params' => ['count' => '1']])->assertCreated()->json('data.id');
        $job = AiJob::findOrFail($id);
        $this->assertSame('completed', $job->status);
        $this->assertTrue($job->result['fallback']);
        $this->assertSame($http->id, $job->provider_id);
        $this->assertSame('down', AiProvider::where('driver', 'comfyui')->value('health'));

        // Semua gagal → gagal + kredit kembali sekali
        $allDown = true;
        $before = Credits::summary($u->fresh())['remaining'];
        $id2 = $this->json_('POST', '/app/jobs', ['type' => 'photo_generate', 'prompt' => 'Sambal', 'params' => ['count' => '1']])->assertCreated()->json('data.id');
        $j2 = AiJob::findOrFail($id2);
        $this->assertSame('failed', $j2->status);
        $this->assertTrue((bool) $j2->quota_refunded);
        $this->assertSame($before, Credits::summary($u->fresh())['remaining']);
        $this->assertSame(1, CreditLedgerEntry::where('ref_type', 'ai_job')->where('ref_id', $id2)->where('direction', 'credit')->count());
    }

    /* ---------------- Prompt library ---------------- */

    public function test_brainstorm_uses_latest_active_prompt_version(): void
    {
        $p = AiProvider::create(['name' => 'Teks', 'driver' => 'openai_compatible', 'base_url' => 'https://llm.test/v1', 'api_key' => 'sk-test', 'capabilities' => ['text'], 'status' => 'active', 'priority' => 1]);
        AiModel::create(['provider_id' => $p->id, 'name' => 'mini', 'model_id' => 'gpt-mini', 'capability' => 'text', 'credit_cost' => 1, 'cost_idr' => 20, 'is_active' => true]);
        // Admin mengubah prompt di Prompt Library → dipakai tanpa deploy
        AiPrompt::where('key', 'brainstorm.default')->update(['version' => '2.0', 'status' => 'active', 'template' => 'VERSI-DUA {{n}} ide {{platform}}: {{topic}}']);
        Http::fake(['llm.test/*' => Http::response(['choices' => [['message' => ['content' => "1. Ide A\n2. Ide B\n3. Ide C"]]], 'usage' => ['total_tokens' => 2000]])]);
        $u = $this->user();
        $this->actingUser($u)->json_('POST', '/app/jobs', ['type' => 'content_brainstorm', 'prompt' => 'sambal', 'params' => ['ideas' => '3', 'platform' => 'tiktok']])->assertCreated();
        Http::assertSent(fn ($r) => str_contains($r->body(), 'VERSI-DUA 3 ide tiktok: sambal'));
        // Prompt dinonaktifkan → kembali ke teks bawaan
        AiPrompt::where('key', 'brainstorm.default')->update(['status' => 'draft']);
        $this->json_('POST', '/app/jobs', ['type' => 'content_brainstorm', 'prompt' => 'rendang', 'params' => ['ideas' => '3']])->assertCreated();
        Http::assertSent(fn ($r) => str_contains($r->body(), 'Buat 3 ide konten untuk instagram dengan nada friendly tentang: rendang'));
        $this->assertSame(80, (int) $p->fresh()->cost_month, '2 job × 2.000 token × Rp20/1K');
    }

    /* ---------------- Domain kustom ---------------- */

    public function test_custom_domain_needs_plan_and_dns_verification(): void
    {
        $u = $this->user([], 'free', 1000000);
        $this->buy($u, 'starter');
        $this->actingUser($u->fresh());
        $w = $this->json_('POST', '/app/websites', ['name' => 'Toko Sari', 'business' => 'Kedai kopi rumahan di Bandung', 'subdomain' => 'toko-sari'])->assertCreated()->json('data.id');
        $this->json_('POST', "/app/websites/{$w}/domain", ['domain' => 'www.tokosari.id'])->assertStatus(403); // Starter: 0 domain kustom

        $this->admin();
        $this->json_('POST', "/admin/users/{$u->id}/features", ['mode' => 'plan', 'plan_code' => 'pro', 'reason' => 'uji domain'])->assertOk();
        $this->actingUser($u->fresh());
        $this->json_('POST', "/app/websites/{$w}/domain", ['domain' => 'tokosari.krafio.site'])->assertStatus(422);
        $d = $this->json_('POST', "/app/websites/{$w}/domain", ['domain' => 'https://WWW.TokoSari.id/'])->assertOk()->json('data.domain');
        $this->assertSame(['www.tokosari.id', 'pending'], [$d['domain'], $d['status']]);
        $token = Website::find($w)->domain_token;

        CustomDomains::$resolver = fn ($host, $type) => [];
        $this->assertSame('pending', $this->json_('POST', "/app/websites/{$w}/domain/verify")->json('data.domain.status'));
        CustomDomains::$resolver = fn ($host, $type) => match (true) {
            $type === DNS_TXT && $host === '_krafio.www.tokosari.id' => [['txt' => 'krafio-verify='.$token]],
            $type === DNS_CNAME && $host === 'www.tokosari.id' => [['target' => CustomDomains::target().'.']],
            default => [],
        };
        $r = $this->json_('POST', "/app/websites/{$w}/domain/verify")->json('data.domain');
        $this->assertSame('verified', $r['status']);
        $this->assertNull($r['error']);
        $this->assertSame(1, $u->notifications()->where('data->title', 'Domain terverifikasi')->count());
        CustomDomains::$resolver = null;

        $other = $this->user();
        $this->actingUser($other)->json_('POST', "/app/websites/{$w}/domain/verify")->assertNotFound();
    }

    /* ---------------- Laporan ---------------- */

    public function test_reports_endpoint_and_csv(): void
    {
        $u = $this->user([], 'free', 500000);
        $this->buy($u);
        $this->admin();
        $r = $this->json_('GET', '/admin/reports')->assertOk()->json('data');
        foreach (['users_by_plan', 'funnel', 'ai', 'operations', 'finance', 'kpi', 'templates', 'margin'] as $k) {
            $this->assertArrayHasKey($k, $r);
        }
        $this->assertGreaterThan(0, $r['finance']['sales']);
        $this->assertSame(8, count($r['kpi']));
        $this->json_('GET', '/admin/reports', ['from' => '2020-01-01', 'to' => '2026-12-31'])->assertStatus(422);
        $csv = $this->get('/api/admin/reports?export=csv')->assertOk();
        $this->assertStringContainsString('registrations', $csv->streamedContent());
        $this->actingUser($u)->json_('GET', '/admin/reports')->assertForbidden();
    }
}
