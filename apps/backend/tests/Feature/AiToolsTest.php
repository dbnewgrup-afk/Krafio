<?php

namespace Tests\Feature;

use App\Jobs\PullLocalModel;
use App\Models\AiJob;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Upload;
use App\Services\Ai\TextWriter;
use App\Services\Ai\VideoTools;
use App\Services\AiGateway;
use App\Services\Settings;
use App\Support\AiTools;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/** AI Tools: form berbasis skema, mesin teks bertahap, kontrol Admin, dan pemrosesan video lokal. */
class AiToolsTest extends KrafioTestCase
{
    private function textProvider(): AiProvider
    {
        $p = AiProvider::create(['name' => 'LLM uji', 'driver' => 'openai_compatible', 'base_url' => 'https://llm.test/v1', 'api_key' => 'sk-test', 'capabilities' => ['text'], 'status' => 'active', 'priority' => 1]);
        AiModel::create(['provider_id' => $p->id, 'name' => 'mini', 'model_id' => 'gpt-mini', 'capability' => 'text', 'credit_cost' => 1, 'is_active' => true]);

        return $p;
    }

    public function test_user_gets_tool_schemas_with_basic_and_advanced_fields(): void
    {
        $this->actingUser($this->user());
        $tools = collect($this->json_('GET', '/app/ai-tools')->assertOk()->json('data'))->keyBy('key');
        $this->assertTrue($tools->has('content_script') && $tools->has('photo_generate') && $tools->has('video_clipper'));
        $script = collect($tools['content_script']['fields'])->keyBy('key');
        $this->assertSame(['series' => true], $script['parts']['show_if']);
        $this->assertSame('advanced', $script['hook_style']['group']);
        $this->assertNotEmpty($tools['content_script']['sections']);
    }

    public function test_series_script_costs_credits_per_part_and_writes_each_part(): void
    {
        $this->textProvider();
        $n = 0;
        Http::fake(['llm.test/*' => function () use (&$n) {
            $n++;

            return Http::response(['choices' => [['message' => ['content' => json_encode(['title' => "Judul {$n}", 'hook' => "Hook {$n}", 'script' => ['baris satu', 'baris dua'], 'cta' => 'Follow ya', 'hashtags' => 'kopi senja #umkm'])]]], 'usage' => ['total_tokens' => 500]]);
        }]);
        $u = $this->user();
        $this->actingUser($u);
        $base = ['topic' => 'Kopi susu gula aren', 'platform' => 'tiktok', 'series' => true, 'parts' => 3, 'include' => ['hashtags']];
        // 3 part × 2 kredit per part (naskah)
        $this->assertSame(6, $this->json_('GET', '/app/jobs/estimate?type=content_script&params='.urlencode(json_encode($base)))->json('data.cost'));
        // Tanpa series → field "parts" disembunyikan, biaya 1 part
        $this->assertSame(2, $this->json_('GET', '/app/jobs/estimate?type=content_script&params='.urlencode(json_encode(['series' => false] + $base)))->json('data.cost'));

        $job = $this->json_('POST', '/app/jobs', ['type' => 'content_script', 'params' => $base])->assertCreated()->json('data');
        $this->assertSame(6, $job['cost']);
        $r = AiJob::find($job['id'])->result;
        $this->assertSame('completed', AiJob::find($job['id'])->status);
        $this->assertCount(3, $r['parts']);
        $this->assertSame('Judul 2', $r['parts'][1]['title']);
        $sections = collect($r['parts'][0]['sections'])->keyBy('key');
        $this->assertSame("baris satu\nbaris dua", $sections['script']['content'], 'Daftar dari model digabung jadi teks');
        $this->assertSame('#kopi #senja #umkm', $sections['hashtags']['content'], 'Hashtag dinormalisasi');
        $this->assertFalse($sections->has('scenes'), 'Bagian yang tidak dicentang tidak diminta');
        // Part 2 menerima ringkasan part 1 agar series menyambung
        Http::assertSent(fn ($req) => str_contains($req->body(), 'INI PART 2 DARI 3') && str_contains($req->body(), 'Judul 1'));
    }

    public function test_truncated_json_from_model_is_repaired(): void
    {
        $this->textProvider();
        Http::fake(['llm.test/*' => Http::response(['choices' => [['message' => ['content' => '{"title": "Promo Jumat", "caption": "Beli 2 gratis 1 setiap Jumat']]]])]);
        $this->actingUser($this->user());
        $id = $this->json_('POST', '/app/jobs', ['type' => 'content_caption', 'params' => ['topic' => 'Promo kopi', 'variations' => 1]])->assertCreated()->json('data.id');
        $sec = collect(AiJob::find($id)->result['parts'][0]['sections'])->keyBy('key');
        $this->assertSame('Beli 2 gratis 1 setiap Jumat', $sec['caption']['content']);
    }

    public function test_provider_rejecting_optional_params_is_retried_without_them(): void
    {
        // Gemini (endpoint OpenAI-compatible) menolak frequency_penalty dengan 400 → kirim ulang tanpa parameter itu
        $p = $this->textProvider();
        $bodies = [];
        Http::fake(['llm.test/*' => function ($req) use (&$bodies) {
            $bodies[] = $req->data();

            return isset($req->data()['frequency_penalty']) || isset($req->data()['response_format'])
                ? Http::response(['error' => ['message' => 'Unknown name "frequency_penalty"']], 400)
                : Http::response(['choices' => [['message' => ['content' => 'Halo dari Gemini']]], 'usage' => ['total_tokens' => 10]]);
        }]);
        $res = AiGateway::chat([['role' => 'user', 'content' => 'hai']], 50, ['frequency_penalty' => 0.4, 'json' => true]);
        $this->assertSame('Halo dari Gemini', $res['text'] ?? null);
        $this->assertSame($p->id, $res['provider_id']);
        $this->assertCount(3, $bodies);
        $this->assertSame('healthy', $p->fresh()->health);
    }

    public function test_truncated_object_keeps_its_keys_instead_of_inner_array(): void
    {
        $raw = "{\n \"title\": \"Part 2\",\n \"hook\": \"Gak percaya?\",\n \"scenes\": [\n  \"1. Close-up\",\n  \"2. Wide\"\n ],\n \"script\": [\"baris satu\", \"baris du";
        $d = TextWriter::decode($raw);
        $this->assertSame(['title', 'hook', 'scenes', 'script'], array_keys($d));
        $this->assertSame('Gak percaya?', $d['hook']);
    }

    public function test_invalid_options_fall_back_and_required_fields_are_enforced(): void
    {
        $this->actingUser($this->user());
        $this->json_('POST', '/app/jobs', ['type' => 'copywriting', 'params' => ['kind' => 'meta_ads']])->assertStatus(422)->assertJsonValidationErrors('product');
        $tool = AiTools::get('content_caption');
        $clean = AiTools::sanitize($tool, ['topic' => '<b>Promo</b>', 'platform' => 'myspace', 'variations' => 99, 'emoji' => 'none'])['params'];
        $this->assertSame(['Promo', 'instagram_feed', 5, 'none'], [$clean['topic'], $clean['platform'], $clean['variations'], $clean['emoji']]);
    }

    public function test_without_text_provider_no_sample_results_are_produced(): void
    {
        $this->actingUser($this->user());
        $tool = collect($this->json_('GET', '/app/ai-tools')->json('data'))->firstWhere('key', 'content_plan');
        $this->assertFalse($tool['provider_ready']);
        $this->json_('POST', '/app/jobs', ['type' => 'content_plan', 'params' => ['topic' => 'Toko hijab', 'days' => '14']])->assertStatus(503)->assertJsonPath('code', 'ai_unavailable');
        $this->assertSame(0, AiJob::count());
    }

    public function test_provider_dropping_mid_job_fails_and_refunds_instead_of_sample(): void
    {
        $this->textProvider();
        Http::fake(['llm.test/*' => Http::response(['error' => 'down'], 500)]);
        $u = $this->user();
        $this->actingUser($u);
        $id = $this->json_('POST', '/app/jobs', ['type' => 'content_caption', 'params' => ['topic' => 'Promo', 'variations' => 2]])->assertCreated()->json('data.id');
        $job = AiJob::find($id);
        $this->assertSame('failed', $job->status);
        $this->assertTrue($job->quota_refunded);
        $this->assertNull($job->result);
    }

    public function test_admin_controls_tools_without_deploy(): void
    {
        $this->admin();
        $this->json_('PUT', '/admin/ai-tools/content_caption', ['label' => 'Caption Jualan', 'cost_per_unit' => 2, 'hidden_fields' => ['emoji'], 'defaults' => ['platform' => 'tiktok', 'variations' => 2]])->assertOk();
        $this->json_('PUT', '/admin/ai-tools/story_series', ['enabled' => false])->assertOk();
        $this->json_('PUT', '/admin/ai-tools/content_caption', ['hidden_fields' => ['topic']])->assertStatus(200); // field wajib tetap tampil
        $this->assertNotEmpty($this->json_('GET', '/admin/ai-tools')->assertOk()->json('data.tools.0.repos'));

        $this->actingUser($this->user());
        $tools = collect($this->json_('GET', '/app/ai-tools')->json('data'))->keyBy('key');
        $this->assertFalse($tools->has('story_series'));
        $cap = $tools['content_caption'];
        $fields = collect($cap['fields'])->keyBy('key');
        $this->assertSame('Caption Jualan', $cap['label']);
        $this->assertTrue($fields->has('topic'));
        $this->assertSame('tiktok', $fields['platform']['default']);
        $this->assertSame(2, $fields['variations']['default']);
        $this->assertSame(6, $this->json_('GET', '/app/jobs/estimate?type=content_caption&params='.urlencode(json_encode(['topic' => 'x', 'variations' => 3])))->json('data.cost'));
        $this->json_('POST', '/app/jobs', ['type' => 'story_series', 'params' => ['premise' => 'Barista misterius']])->assertStatus(403)->assertJsonPath('code', 'tool_disabled');
    }

    public function test_clipper_cuts_loudest_moment_with_ffmpeg(): void
    {
        if (! VideoTools::available()) {
            $this->markTestSkipped('FFmpeg tidak tersedia.');
        }
        $u = $this->user();
        $path = 'uploads/'.$u->id.'/uji.mp4';
        Storage::disk('local')->makeDirectory('uploads/'.$u->id);
        // 20 detik: hening kecuali nada keras di detik 12–16
        (new Process([config('krafio.ffmpeg'), '-y', '-loglevel', 'error', '-f', 'lavfi', '-i', 'testsrc2=size=320x240:rate=10:duration=20', '-f', 'lavfi', '-i', 'sine=frequency=500:duration=20',
            '-filter_complex', "[1:a]volume='if(between(t,12,16),1,0.02)':eval=frame[a]", '-map', '0:v', '-map', '[a]', '-c:v', 'libx264', '-preset', 'ultrafast', '-c:a', 'aac', Storage::disk('local')->path($path)]))->mustRun();
        $up = Upload::create(['user_id' => $u->id, 'disk' => 'local', 'path' => $path, 'original_name' => 'uji.mp4', 'mime' => 'video/mp4', 'size' => Storage::disk('local')->size($path)]);
        $job = AiJob::create(['user_id' => $u->id, 'upload_id' => $up->id, 'type' => 'video_clipper', 'entitlement' => 'video.clipper', 'prompt' => 'uji', 'params' => ['clips' => '1', 'clip_len' => '15', 'source_minutes' => '5', 'ratio' => '9:16', 'pick' => 'loud'], 'cost' => 0])->fresh();
        AiGateway::process($job);
        $job->refresh();
        $this->assertSame('completed', $job->status, (string) $job->error);
        $clip = $job->result['clips'][0];
        $this->assertTrue($clip['start'] <= 12 && $clip['end'] >= 16, 'Klip memuat bagian paling keras');
        $this->assertTrue(Storage::disk('local')->exists($job->result['files'][0]['path']));
    }

    public function test_prompt_optimizer_includes_negative_only_for_image_targets(): void
    {
        $this->textProvider();
        Http::fake(['llm.test/*' => Http::response(['choices' => [['message' => ['content' => '{"title":"v1","optimized":"cinematic iced palm sugar latte","negative":"blur, text","changes":"- tambah gaya","tips":"pakai 4:5"}']]]])]);
        $this->actingUser($this->user());
        $id = $this->json_('POST', '/app/jobs', ['type' => 'prompt_optimizer', 'params' => ['prompt' => 'foto kopi', 'target' => 'image']])->assertCreated()->json('data.id');
        $keys = collect(AiJob::find($id)->result['parts'][0]['sections'])->pluck('key')->all();
        $this->assertSame(['optimized', 'negative', 'changes', 'tips'], $keys);
        $id = $this->json_('POST', '/app/jobs', ['type' => 'prompt_optimizer', 'params' => ['prompt' => 'tulis caption', 'target' => 'content']])->assertCreated()->json('data.id');
        $this->assertNotContains('negative', collect(AiJob::find($id)->result['parts'][0]['sections'])->pluck('key')->all());
    }

    public function test_doc_results_export_to_docx_and_xlsx_for_owner_only(): void
    {
        $u = $this->user();
        $job = AiJob::create(['user_id' => $u->id, 'type' => 'content_plan', 'entitlement' => 'content.brainstorm', 'prompt' => 'x', 'status' => 'completed', 'cost' => 1, 'result' => [
            'format' => 'doc', 'title' => 'Kalender Kopi', 'items' => [['day' => '1', 'title' => '=Promo', 'cta' => 'Beli']], 'item_fields' => [['key' => 'day', 'label' => 'Hari'], ['key' => 'title', 'label' => 'Judul'], ['key' => 'cta', 'label' => 'CTA']],
        ]]);
        $this->actingUser($u);
        $x = $this->get("/api/app/jobs/{$job->id}/export/xlsx")->assertOk();
        $this->assertStringContainsString('spreadsheetml', $x->headers->get('Content-Type'));
        $tmp = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($tmp, $x->getContent());
        $z = new \ZipArchive;
        $this->assertTrue($z->open($tmp) === true);
        $sheet = $z->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('&apos;=Promo', $sheet, 'Formula injection dinetralkan');
        $this->assertNotFalse(simplexml_load_string($sheet));
        $z->close();
        $d = $this->get("/api/app/jobs/{$job->id}/export/docx")->assertOk();
        $this->assertStringContainsString('wordprocessingml', $d->headers->get('Content-Type'));
        $this->actingUser($this->user());
        $this->get("/api/app/jobs/{$job->id}/export/docx")->assertNotFound();
    }

    public function test_script_to_video_renders_scenes_with_ffmpeg(): void
    {
        if (! VideoTools::available()) {
            $this->markTestSkipped('FFmpeg tidak tersedia.');
        }
        $u = $this->user();
        $job = AiJob::create(['user_id' => $u->id, 'type' => 'script_video', 'entitlement' => 'video.edit', 'prompt' => 'x', 'cost' => 0,
            'params' => ['script' => "Halo\n\nIni adegan dua\nPenutup", 'seconds' => 1, 'ratio' => '1:1', 'theme' => 'ocean', 'resolution' => '720', 'animate' => true, 'brand' => '@uji']])->fresh();
        AiGateway::process($job);
        $job->refresh();
        $this->assertSame('completed', $job->status, (string) $job->error);
        $this->assertSame(3, $job->result['source_duration'], '3 adegan × 1 detik (baris kosong dibuang)');
        $this->assertSame([720, 720], [VideoTools::probe(Storage::disk('local')->path($job->result['files'][0]['path']))['width'], VideoTools::probe(Storage::disk('local')->path($job->result['files'][0]['path']))['height']]);
    }

    public function test_clipper_ranges_parser_and_upload_duration_drives_cost(): void
    {
        $r = VideoTools::parseRanges("0:30-1:05\n2:10-2:40, 9:00-9:30, abc, 1:00:00-1:00:10, 0:50-0:40", 200.0);
        $this->assertSame([[30.0, 35.0], [130.0, 30.0]], array_map(fn ($w) => [$w['start'], $w['len']], $r), 'Rentang di luar durasi / terbalik / tidak valid dibuang');
        if (! VideoTools::available()) {
            return;
        }
        $u = $this->user();
        $path = Storage::disk('local')->path('src.mp4');
        @mkdir(dirname($path), 0777, true);
        (new Process([config('krafio.ffmpeg'), '-y', '-loglevel', 'error', '-f', 'lavfi', '-i', 'testsrc=size=320x240:rate=10:duration=125', '-c:v', 'libx264', '-preset', 'ultrafast', $path]))->mustRun();
        $this->actingUser($u);
        $up = $this->post('/api/app/uploads', ['file' => new UploadedFile($path, 'src.mp4', 'video/mp4', null, true)], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $this->assertEqualsWithDelta(125, $up['duration'], 1);
        $this->assertSame([320, 240], [$up['width'], $up['height']]);
        // 125 detik → 3 menit × biaya clipper per menit (default 10)
        $this->assertSame(30, $this->json_('GET', '/app/jobs/estimate?type=video_clipper&upload_id='.$up['id'].'&params='.urlencode(json_encode(['mode' => 'auto'])))->json('data.cost'));
    }

    public function test_admin_lists_local_models_and_switches_active_model(): void
    {
        $p = AiProvider::create(['name' => 'Ollama', 'driver' => 'openai_compatible', 'base_url' => 'http://127.0.0.1:11434/v1', 'api_key' => 'ollama', 'capabilities' => ['text'], 'status' => 'active', 'priority' => 50]);
        AiModel::create(['provider_id' => $p->id, 'name' => 'qwen2.5:3b', 'model_id' => 'qwen2.5:3b', 'capability' => 'text', 'credit_cost' => 1, 'is_active' => true]);
        Http::fake(['127.0.0.1:11434/api/tags' => Http::response(['models' => [['name' => 'qwen2.5:3b', 'size' => 1930000000, 'details' => ['parameter_size' => '3.1B']], ['name' => 'qwen2.5:7b', 'size' => 4700000000, 'details' => []]]])]);
        $this->admin();
        $d = $this->json_('GET', '/admin/ai-local')->assertOk()->json('data.providers.0');
        $this->assertSame(['ollama', 'qwen2.5:3b', 2], [$d['runtime'], $d['active_model'], count($d['models'])]);
        $this->json_('POST', '/admin/ai-local/use', ['provider_id' => $p->id, 'model' => 'qwen2.5:7b'])->assertOk();
        $this->assertSame('qwen2.5:7b', AiModel::where('provider_id', $p->id)->value('model_id'));
        Queue::fake();
        $this->json_('POST', '/admin/ai-local/pull', ['provider_id' => $p->id, 'model' => 'llama3.2:3b'])->assertStatus(202);
        Queue::assertPushed(PullLocalModel::class, fn ($j) => $j->model === 'llama3.2:3b' && $j->host === 'http://127.0.0.1:11434');
        $this->json_('POST', '/admin/ai-local/pull', ['provider_id' => $p->id, 'model' => 'bad model; rm -rf'])->assertStatus(422);
    }

    public function test_chat_costs_one_credit_per_n_messages(): void
    {
        $this->textProvider();
        Settings::set('credits.chat_messages_per_credit', 3); // diatur Admin (default 10)
        Http::fake(['llm.test/*' => Http::response(['choices' => [['message' => ['content' => 'Halo!']]]])]);
        $this->actingUser($this->user());
        $credits = fn () => $this->json_('GET', '/me')->json('data.entitlements.credits.remaining');
        $start = $credits();
        $this->json_('POST', '/app/assistant', ['message' => 'hai'])->assertOk();
        $this->assertSame($start - 1, $credits(), 'Pesan pertama memotong 1 kredit untuk blok 3 pesan');
        $this->json_('POST', '/app/assistant', ['message' => 'dua'])->assertOk();
        $this->json_('POST', '/app/assistant', ['message' => 'tiga'])->assertOk();
        $this->assertSame($start - 1, $credits(), 'Pesan 2–3 tidak memotong lagi');
        $this->json_('POST', '/app/assistant', ['message' => 'empat'])->assertOk();
        $this->assertSame($start - 2, $credits());
    }

    /** APP_KEY diganti setelah key provider tersimpan → provider dilewati, halaman tetap jalan (bukan error 500). */
    public function test_undecryptable_provider_key_is_skipped_not_500(): void
    {
        $p = $this->textProvider();
        \DB::table('ai_providers')->where('id', $p->id)->update(['api_key' => 'eyJpdiI6InJ1c2FrIn0=']);
        $this->actingUser($this->user());
        $this->json_('GET', '/app/ai-tools/content_script')->assertOk()->assertJsonPath('data.provider_ready', false);
        $this->assertSame('down', $p->fresh()->health);
    }
}
