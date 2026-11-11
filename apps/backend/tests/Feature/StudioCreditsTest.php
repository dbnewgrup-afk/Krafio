<?php

namespace Tests\Feature;

use App\Models\AiJob;
use App\Services\AiGateway;

/** Alur B — Studio AI, kredit hybrid (PRD 55.1, BR-026/027), refund job gagal (BR-017), batas paket. */
class StudioCreditsTest extends KrafioTestCase
{
    public function test_b1_photo_job_shows_estimate_consumes_credits_and_completes(): void
    {
        $u = $this->user();
        $this->actingUser($u)->json_('GET', '/app/jobs/estimate?type=photo_generate&count=2')->assertOk()->assertJsonPath('data.cost', 16)->assertJsonPath('data.unit', 'credit');
        $r = $this->json_('POST', '/app/jobs', ['type' => 'photo_generate', 'prompt' => 'Kopi susu di meja kayu', 'params' => ['count' => '1']]);
        $r->assertCreated();
        $job = AiJob::findOrFail($r->json('data.id'));
        $this->assertSame('completed', $job->status, 'Job selesai (driver simulasi, queue sync)');
        $this->assertNotEmpty($job->result['preview_palette']);
        $this->json_('GET', '/me')->assertJsonPath('data.entitlements.credits.remaining', 42);
        $this->assertDatabaseHas('credit_ledger', ['user_id' => $u->id, 'direction' => 'debit', 'amount' => 8]);
    }

    public function test_b2_insufficient_credits_returns_quota_exceeded(): void
    {
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
        $alloc = \App\Services\Credits::consume($u, 8, 'ai_job', $job->id, 'uji');
        $job->update(['credit_alloc' => $alloc]);
        (new \App\Jobs\ProcessAiJob($job->id))->failed(new \RuntimeException('provider down'));
        $this->assertSame('failed', $job->fresh()->status);
        $this->assertTrue($job->fresh()->quota_refunded);
        $this->json_('GET', '/me')->assertJsonPath('data.entitlements.credits.remaining', 50);
    }

    public function test_b5_brainstorm_uses_request_limit_not_credits(): void
    {
        $this->actingUser($this->user());
        $this->json_('POST', '/app/jobs', ['type' => 'content_brainstorm', 'prompt' => 'sambal', 'params' => ['ideas' => '3']])->assertCreated();
        $me = $this->json_('GET', '/me');
        $me->assertJsonPath('data.entitlements.credits.remaining', 50);
        $this->assertSame(1, $me->json('data.entitlements')['content.brainstorm']['used']);
        $this->assertFalse(AiGateway::configured('text'));
        $this->assertCount(3, AiJob::latest('id')->first()->result['items']);
    }

    public function test_b6_cancel_queued_job_refunds(): void
    {
        $u = $this->user();
        $this->actingUser($u);
        $job = AiJob::create(['user_id' => $u->id, 'type' => 'photo_generate', 'entitlement' => 'photo.generate', 'prompt' => 'x', 'cost' => 8, 'status' => 'queued']);
        $job->update(['credit_alloc' => \App\Services\Credits::consume($u, 8, 'ai_job', $job->id, 'uji')]);
        $this->json_('POST', "/app/jobs/{$job->id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->json_('GET', '/me')->assertJsonPath('data.entitlements.credits.remaining', 50);
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
