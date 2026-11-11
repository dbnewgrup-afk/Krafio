<?php

namespace Tests\Feature;

use App\Models\AiJob;
use App\Models\Faq;
use App\Models\Subscription;
use App\Models\Website;
use App\Services\Credits;
use App\Services\Entitlements;
use App\Services\FaqResponder;
use App\Services\Ops;
use App\Services\SeoWriter;

/** Fitur berbasis kode/algoritma (tanpa AI eksternal): SEO otomatis, jawaban FAQ, prakiraan kredit, monitoring & pemulihan job. */
class RuleBasedFeaturesTest extends KrafioTestCase
{
    public function test_seo_writer_always_produces_publishable_lengths(): void
    {
        $cases = [
            ['Toko QA', 'Kopi', []],
            ['A', '', []],
            [str_repeat('Nama Usaha Sangat Panjang ', 6), str_repeat('Deskripsi bisnis yang sangat panjang sekali. ', 10), ['Layanan satu', 'Layanan dua']],
            ['Kedai Sari', 'Kedai kopi susu rumahan di Bandung', ['Kopi susu', 'Roti bakar']],
        ];
        foreach ($cases as [$name, $biz, $svc]) {
            $t = SeoWriter::title($name, $svc, $biz);
            $d = SeoWriter::description($name, $biz, $svc);
            $this->assertGreaterThanOrEqual(10, mb_strlen($t), $t);
            $this->assertLessThanOrEqual(60, mb_strlen($t), $t);
            $this->assertGreaterThanOrEqual(50, mb_strlen($d), $d);
            $this->assertLessThanOrEqual(160, mb_strlen($d), $d);
        }
    }

    public function test_new_website_draft_can_be_published_without_manual_seo_edits(): void
    {
        $u = $this->user();
        $this->admin();
        $this->json_('POST', "/admin/users/{$u->id}/features", ['mode' => 'plan', 'plan_code' => 'starter', 'reason' => 'uji publish'])->assertOk();
        $this->actingUser($u->fresh());
        $w = $this->json_('POST', '/app/websites', ['name' => 'Toko QA', 'business' => 'Kopi', 'services' => 'Kopi susu, Roti bakar', 'subdomain' => 'toko-qa'])->assertCreated();
        $content = $w->json('data.content');
        $this->json_('POST', '/app/websites/'.$w->json('data.id').'/publish', ['content' => $content])->assertOk();
        $this->assertSame('published', Website::find($w->json('data.id'))->status);
    }

    public function test_seo_suggest_fixes_invalid_seo_and_is_owner_only(): void
    {
        $u = $this->user();
        $this->admin();
        $this->json_('POST', "/admin/users/{$u->id}/features", ['mode' => 'plan', 'plan_code' => 'starter', 'reason' => 'uji seo'])->assertOk();
        $this->actingUser($u->fresh());
        $w = $this->json_('POST', '/app/websites', ['name' => 'Toko QA', 'business' => 'Kopi', 'subdomain' => 'toko-qa2'])->assertCreated();
        $content = $w->json('data.content');
        $content['seo'] = ['title' => 'x', 'description' => 'pendek'];
        $r = $this->json_('POST', '/app/websites/'.$w->json('data.id').'/seo-suggest', ['content' => $content])->assertOk();
        $this->assertGreaterThanOrEqual(10, mb_strlen($r->json('data.seo.title')));
        $this->assertGreaterThanOrEqual(50, mb_strlen($r->json('data.seo.description')));

        $this->actingUser($this->user())->json_('POST', '/app/websites/'.$w->json('data.id').'/seo-suggest', ['content' => $content])->assertNotFound();
    }

    public function test_assistant_answers_from_faq_without_ai_provider_and_keeps_quota(): void
    {
        Faq::create(['category' => 'billing', 'question_id' => 'Bagaimana cara top up saldo?', 'answer_id' => 'Buka menu Saldo lalu pilih Top-up, masukkan nominal dan bayar.', 'is_active' => true]);
        Faq::create(['category' => 'affiliate', 'question_id' => 'Kapan komisi afiliasi bisa ditarik?', 'answer_id' => 'Komisi bisa ditarik setelah masa tahan selesai.', 'is_active' => true]);
        $this->assertStringContainsString('Top-up', FaqResponder::answer('gimana caranya isi saldo / topup?')['answer']);
        $this->assertStringContainsString('masa tahan', FaqResponder::answer('kapan bisa withdraw komisi afiliasi')['answer']);
        $this->assertNull(FaqResponder::answer('resep rendang padang'));

        $u = $this->user();
        $this->actingUser($u);
        $r = $this->json_('POST', '/app/assistant', ['message' => 'cara top up saldo gimana min?'])->assertOk();
        $this->assertStringContainsString('Top-up', $r->json('data.content'));
        $this->assertSame(0, Entitlements::forUser($u)['chatbot.basic']['used'], 'Jawaban FAQ tidak memotong kuota chatbot');
    }

    public function test_credit_forecast_estimates_run_out_date(): void
    {
        $u = $this->user();
        $this->assertNull(Ops::creditForecast($u)['days_left'], 'Belum ada pemakaian → tidak ada prakiraan');
        Credits::consume($u, 20, 'ai_job', null, 'uji');
        $f = $this->actingUser($u)->json_('GET', '/app/credits/forecast')->assertOk()->json('data');
        $this->assertGreaterThan(0, $f['daily_avg']);
        $this->assertSame((int) floor(30 / $f['daily_avg']), $f['days_left'], 'Sisa 30 kredit dibagi rata-rata harian');
    }

    public function test_stuck_jobs_are_recovered_once_and_listed_in_attention(): void
    {
        $u = $this->user();
        Credits::ensurePlanPack($u);
        $alloc = Credits::consume($u, 10, 'ai_job', null, 'uji');
        $job = AiJob::create(['user_id' => $u->id, 'type' => 'photo_generate', 'entitlement' => 'photo.generate', 'prompt' => 'x', 'params' => [], 'status' => 'processing', 'cost' => 10, 'credit_alloc' => $alloc, 'started_at' => now()->subHour()]);
        $fresh = AiJob::create(['user_id' => $u->id, 'type' => 'photo_generate', 'entitlement' => 'photo.generate', 'prompt' => 'y', 'params' => [], 'status' => 'processing', 'cost' => 0, 'started_at' => now()]);
        Subscription::where('user_id', $u->id)->update(['status' => 'past_due']);

        $this->admin();
        $att = collect($this->json_('GET', '/admin/ops/attention')->assertOk()->json('data'))->keyBy('key');
        $this->assertSame(1, $att['stuck_jobs']['count']);
        $this->assertSame(1, $att['past_due']['count']);
        $this->assertSame('stuck_jobs', $this->json_('GET', '/admin/ops/attention')->json('data.0.key'), 'Kritis & ada isinya di urutan teratas');

        $before = Credits::summary($u)['remaining'];
        $this->json_('POST', '/admin/ops/recover-stuck-jobs')->assertOk()->assertJsonPath('data.recovered', 1);
        $this->assertSame('failed', $job->fresh()->status);
        $this->assertSame('processing', $fresh->fresh()->status, 'Job yang baru mulai tidak disentuh');
        $this->assertSame($before + 10, Credits::summary($u->fresh())['remaining']);
        $this->assertSame(0, Ops::recoverStuckJobs(), 'Tidak ada refund ganda');

        $this->actingUser($u)->json_('GET', '/admin/ops/attention')->assertForbidden();
    }
}
