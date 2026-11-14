<?php

namespace Tests\Feature;

use App\Jobs\ProcessAiJob;
use App\Models\AiJob;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Services\AiGateway;
use App\Services\Credits;
use Illuminate\Support\Facades\Http;

/** Alur B — Studio AI, kredit hybrid (PRD 55.1, BR-026/027), refund job gagal (BR-017), batas paket. */
class StudioCreditsTest extends KrafioTestCase
{
    /** Penyedia gambar palsu (driver custom_http) — tidak ada lagi "hasil contoh" tanpa penyedia. */
    private function imageProvider(): void
    {
        $p = AiProvider::create(['name' => 'Img uji', 'driver' => 'custom_http', 'base_url' => 'https://img.test/gen', 'capabilities' => ['image'], 'status' => 'active', 'priority' => 1]);
        AiModel::create(['provider_id' => $p->id, 'name' => 'img', 'model_id' => 'img-1', 'capability' => 'image', 'credit_cost' => 1, 'is_active' => true]);
        $png = base64_encode((string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
        Http::fake(['img.test/*' => Http::response(['outputs' => [['base64' => $png, 'mime' => 'image/png']]])]);
    }

    public function test_b1_photo_job_shows_estimate_consumes_credits_and_completes(): void
    {
        $this->imageProvider();
        $u = $this->user();
        $this->actingUser($u)->json_('GET', '/app/jobs/estimate?type=photo_generate&count=2')->assertOk()->assertJsonPath('data.cost', 16)->assertJsonPath('data.unit', 'credit');
        $r = $this->json_('POST', '/app/jobs', ['type' => 'photo_generate', 'prompt' => 'Kopi susu di meja kayu', 'params' => ['count' => '1']]);
        $r->assertCreated();
        $job = AiJob::findOrFail($r->json('data.id'));
        $this->assertSame('completed', $job->status, 'Job selesai di penyedia (queue sync)');
        $this->assertNotEmpty($job->result['media']);
        $this->assertArrayNotHasKey('preview_palette', $job->result, 'Tidak ada pratinjau contoh');
        $this->json_('GET', '/me')->assertJsonPath('data.entitlements.credits.remaining', 47);
        $this->assertDatabaseHas('credit_ledger', ['user_id' => $u->id, 'direction' => 'debit', 'amount' => 8]);
    }

    public function test_b2_insufficient_credits_returns_quota_exceeded(): void
    {
        $this->imageProvider();
        $this->actingUser($this->user());
        $this->json_('POST', '/app/jobs', ['type' => 'photo_generate', 'prompt' => 'A', 'params' => ['count' => '4']])->assertCreated(); // 32
        $this->json_('POST', '/app/jobs', ['type' => 'photo_generate', 'prompt' => 'B', 'params' => ['count' => '3']])->assertStatus(403)->assertJsonPath('code', 'quota_exceeded'); // 24 > 18
    }

    public function test_b3_plan_limits_hd_video_and_feature_lock_on_free(): void
    {
        $this->actingUser($this->user());
        $this->json_('POST', '/app/jobs', ['type' => 'photo_generate', 'prompt' => 'HD', 'params' => ['quality' => 'hd']])->assertStatus(403)->assertJsonPath('code', 'plan_limit');
        $this->json_('POST', '/app/jobs', ['type' => 'video_generate', 'prompt' => 'Video'])->assertStatus(403)->assertJsonPath('code', 'feature_locked');
    }

    public function test_b4_failed_job_refunds_credits_to_original_pack(): void
    {
        $u = $this->user();
        $this->actingUser($u);
        $job = AiJob::create(['user_id' => $u->id, 'type' => 'photo_generate', 'entitlement' => 'photo.generate', 'prompt' => 'x', 'cost' => 8, 'status' => 'processing']);
        $alloc = Credits::consume($u, 8, 'ai_job', $job->id, 'uji');
        $job->update(['credit_alloc' => $alloc]);
        (new ProcessAiJob($job->id))->failed(new \RuntimeException('provider down'));
        $this->assertSame('failed', $job->fresh()->status);
        $this->assertTrue($job->fresh()->quota_refunded);
        $this->json_('GET', '/me')->assertJsonPath('data.entitlements.credits.remaining', 55);
    }

    public function test_b5_text_tools_consume_credits(): void
    {
        $p = AiProvider::create(['name' => 'LLM', 'driver' => 'openai_compatible', 'base_url' => 'https://llm.test/v1', 'api_key' => 'k', 'capabilities' => ['text'], 'status' => 'active', 'priority' => 1]);
        AiModel::create(['provider_id' => $p->id, 'name' => 'm', 'model_id' => 'm', 'capability' => 'text', 'credit_cost' => 1, 'is_active' => true]);
        Http::fake(['llm.test/*' => Http::response(['choices' => [['message' => ['content' => '{"items":[{"title":"A"},{"title":"B"},{"title":"C"}]}']]]])]);
        $this->actingUser($this->user());
        $this->json_('POST', '/app/jobs', ['type' => 'content_brainstorm', 'prompt' => 'sambal', 'params' => ['ideas' => '3']])->assertCreated();
        $me = $this->json_('GET', '/me');
        // Sejak 14 Nov 2026 tool teks memakai kredit: 3 ide = 1 unit × 1 kredit
        $me->assertJsonPath('data.entitlements.credits.remaining', 54);
        $this->assertSame('credit', $me->json('data.entitlements')['content.brainstorm']['unit']);
        $this->assertCount(3, AiJob::latest('id')->first()->result['items']);
    }

    /** Tanpa penyedia AI: tidak ada hasil contoh — ditolak sebelum kuota/kredit dipotong. */
    public function test_b5b_no_provider_rejects_without_charging(): void
    {
        $this->actingUser($this->user());
        $this->assertFalse(AiGateway::configured('text'));
        $this->json_('POST', '/app/jobs', ['type' => 'content_brainstorm', 'prompt' => 'sambal', 'params' => ['ideas' => '3']])->assertStatus(503)->assertJsonPath('code', 'ai_unavailable');
        $this->json_('POST', '/app/jobs', ['type' => 'photo_generate', 'prompt' => 'Kopi'])->assertStatus(503);
        $this->assertSame(0, AiJob::count());
        $me = $this->json_('GET', '/me');
        $me->assertJsonPath('data.entitlements.credits.remaining', 55);
        $this->assertSame(0, $me->json('data.entitlements')['content.brainstorm']['used']);
    }

    public function test_b6_cancel_queued_job_refunds(): void
    {
        $u = $this->user();
        $this->actingUser($u);
        $job = AiJob::create(['user_id' => $u->id, 'type' => 'photo_generate', 'entitlement' => 'photo.generate', 'prompt' => 'x', 'cost' => 8, 'status' => 'queued']);
        $job->update(['credit_alloc' => Credits::consume($u, 8, 'ai_job', $job->id, 'uji')]);
        $this->json_('POST', "/app/jobs/{$job->id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->json_('GET', '/me')->assertJsonPath('data.entitlements.credits.remaining', 55);
    }

    public function test_b7_ai_consent_required_and_seo_blocks_internal_url(): void
    {
        $u = $this->user();
        $u->forceFill(['ai_consent_at' => null])->save();
        $this->actingUser($u->fresh());
        $this->json_('POST', '/app/jobs', ['type' => 'photo_generate', 'prompt' => 'x'])->assertStatus(403)->assertJsonPath('code', 'ai_consent_required');
        $this->json_('POST', '/app/seo/audits', ['url' => 'http://127.0.0.1/admin'])->assertStatus(422)->assertJsonPath('code', 'blocked_url');
        $this->json_('POST', '/app/seo/audits', ['url' => 'http://10.0.0.5/'])->assertStatus(422)->assertJsonPath('code', 'blocked_url');
    }
}
