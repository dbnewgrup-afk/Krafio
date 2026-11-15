<?php

namespace Tests\Feature;

use App\Models\AiJob;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Services\Ai\ClaudeDriver;
use App\Services\AiGateway;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;

/** Driver Claude (SDK resmi Anthropic) & Gemini (Interactions API + OpenAI-compatible). Tanpa panggilan jaringan sungguhan. */
class ClaudeGeminiDriversTest extends KrafioTestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    /** @var array<int, array{request: RequestInterface}> */
    private array $sent = [];

    protected function tearDown(): void
    {
        ClaudeDriver::$transport = null;
        parent::tearDown();
    }

    private function claude(string $model, array $responses): AiProvider
    {
        AiProvider::query()->update(['status' => 'draft']);
        $p = AiProvider::create(['name' => 'Claude uji', 'driver' => 'anthropic', 'api_key' => 'sk-ant-test', 'capabilities' => ['text'], 'status' => 'active', 'priority' => 1]);
        AiModel::create(['provider_id' => $p->id, 'name' => $model, 'model_id' => $model, 'capability' => 'text', 'credit_cost' => 1, 'cost_idr' => 6, 'is_active' => true]);
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->sent));
        ClaudeDriver::$transport = new Guzzle(['handler' => $stack]);

        return $p;
    }

    private function claudeReply(string $text, string $stop = 'end_turn'): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-haiku-5-5', 'stop_reason' => $stop, 'stop_sequence' => null,
            'content' => $text === '' ? [] : [['type' => 'text', 'text' => $text, 'citations' => null]],
            'usage' => ['input_tokens' => 120, 'output_tokens' => 80, 'cache_creation_input_tokens' => 0, 'cache_read_input_tokens' => 0],
        ]));
    }

    private function gemini(array $caps = ['text', 'image', 'video']): AiProvider
    {
        AiProvider::query()->update(['status' => 'draft']);
        $p = AiProvider::create(['name' => 'Gemini uji', 'driver' => 'gemini', 'api_key' => 'g-test', 'capabilities' => $caps, 'status' => 'active', 'priority' => 1]);
        AiModel::create(['provider_id' => $p->id, 'name' => 'Flash', 'model_id' => 'gemini-3.8-flash', 'capability' => 'text', 'credit_cost' => 1, 'is_active' => true]);
        AiModel::create(['provider_id' => $p->id, 'name' => 'Nano Banana', 'model_id' => 'gemini-nano-banana-2.1', 'capability' => 'image', 'credit_cost' => 1, 'cost_idr' => 555, 'is_active' => true]);
        AiModel::create(['provider_id' => $p->id, 'name' => 'Omni', 'model_id' => 'gemini-omni-1.2-flash', 'capability' => 'video', 'credit_cost' => 1, 'is_active' => true]);

        return $p;
    }

    public function test_claude_haiku_chat_uses_sdk_with_system_prompt_and_thinking_off(): void
    {
        $p = $this->claude('claude-haiku-5-5', [$this->claudeReply('{"title":"Halo"}')]);
        $res = AiGateway::chat([['role' => 'system', 'content' => 'Kamu penulis.'], ['role' => 'user', 'content' => 'Tulis judul.']], 500, ['json' => true, 'temperature' => 0.7, 'frequency_penalty' => 0.4]);

        $this->assertSame('{"title":"Halo"}', $res['text']);
        $this->assertSame(200, $res['tokens']);
        $req = $this->sent[0]['request'];
        $this->assertStringEndsWith('/v1/messages', $req->getUri()->getPath());
        $this->assertSame('Bearer sk-ant-test', $req->getHeaderLine('Authorization'));
        $body = json_decode((string) $req->getBody(), true);
        $this->assertSame('claude-haiku-5-5', $body['model']);
        $this->assertStringContainsString('Kamu penulis.', $body['system']);
        $this->assertStringContainsString('JSON', $body['system']);
        $this->assertSame([['role' => 'user', 'content' => 'Tulis judul.']], $body['messages']);
        $this->assertSame(['type' => 'disabled'], $body['thinking']);
        $this->assertSame('low', $body['output_config']['effort']);
        $this->assertArrayNotHasKey('temperature', $body, 'Model terbaru menolak temperature');
        $this->assertArrayNotHasKey('fallbacks', $body, 'Haiku tidak punya fallback server-side');
        $this->assertSame(200, (int) $p->fresh()->tokens_used);
        $this->assertSame('healthy', $p->fresh()->health);
    }

    public function test_claude_sonnet_turns_thinking_off_and_enables_server_fallback(): void
    {
        $this->claude('claude-sonnet-5-5', [$this->claudeReply('Oke')]);
        AiGateway::chat([['role' => 'assistant', 'content' => 'sapaan'], ['role' => 'user', 'content' => 'Hai'], ['role' => 'user', 'content' => 'lagi']], 300);
        $req = $this->sent[0]['request'];
        $body = json_decode((string) $req->getBody(), true);
        $this->assertSame(['type' => 'between_tools'], $body['thinking']);
        $this->assertSame('default', $body['fallbacks']);
        $this->assertStringContainsString('server-side-fallback-2026-07-01', $req->getHeaderLine('anthropic-beta'));
        $this->assertSame([['role' => 'user', 'content' => "Hai\n\nlagi"]], $body['messages'], 'Diawali user, giliran sama digabung');
    }

    public function test_claude_refusal_or_error_falls_back_to_next_provider(): void
    {
        $this->claude('claude-haiku-5-5', [$this->claudeReply('', 'refusal')]);
        $p2 = AiProvider::create(['name' => 'Cadangan', 'driver' => 'openai_compatible', 'base_url' => 'https://llm.test/v1', 'api_key' => 'k', 'capabilities' => ['text'], 'status' => 'active', 'priority' => 2]);
        AiModel::create(['provider_id' => $p2->id, 'name' => 'm', 'model_id' => 'm', 'capability' => 'text', 'credit_cost' => 1, 'is_active' => true]);
        Http::fake(['llm.test/*' => Http::response(['choices' => [['message' => ['content' => 'dari cadangan']]]])]);

        $this->assertSame('dari cadangan', AiGateway::chat([['role' => 'user', 'content' => 'x']])['text']);
    }

    public function test_gemini_text_uses_openai_compatible_endpoint_and_tool_model_preference(): void
    {
        $this->gemini();
        AiModel::create(['provider_id' => AiProvider::where('driver', 'gemini')->where('status', 'active')->value('id'), 'name' => 'Lite', 'model_id' => 'gemini-3.5-flash-lite', 'capability' => 'text', 'credit_cost' => 1, 'is_active' => true, 'is_fallback' => true]);
        $bodies = [];
        Http::fake(['generativelanguage.googleapis.com/*' => function (Request $r) use (&$bodies) {
            $bodies[] = [$r->url(), $r->data(), $r->header('Authorization')[0] ?? ''];

            return Http::response(['choices' => [['message' => ['content' => 'ok']]], 'usage' => ['total_tokens' => 50]]);
        }]);

        AiGateway::chat([['role' => 'user', 'content' => 'a']], 100, ['frequency_penalty' => 0.4]);
        AiGateway::chat([['role' => 'user', 'content' => 'b']], 100, ['model' => 'gemini-3.8-flash']);

        $this->assertSame('https://generativelanguage.googleapis.com/v1beta/openai/chat/completions', $bodies[0][0]);
        $this->assertSame('Bearer g-test', $bodies[0][2]);
        $this->assertSame('gemini-3.5-flash-lite', $bodies[0][1]['model'], 'Tanpa pilihan → model bawaan provider');
        $this->assertArrayNotHasKey('frequency_penalty', $bodies[0][1], 'Gemini menolak frequency_penalty');
        $this->assertSame('gemini-3.8-flash', $bodies[1][1]['model'], 'Pilihan model per tool dipakai');
        $models = array_map(fn ($c) => $c[1]->model_id, AiGateway::textCandidates('gemini-3.8-flash'));
        $this->assertSame(['gemini-3.8-flash', 'gemini-3.5-flash-lite'], array_slice($models, 0, 2), 'Model bawaan provider jadi cadangan pertama');
    }

    public function test_gemini_generates_photo_via_interactions_api(): void
    {
        $this->gemini();
        $sent = [];
        Http::fake(['generativelanguage.googleapis.com/v1beta/interactions' => function (Request $r) use (&$sent) {
            $sent[] = [$r->data(), $r->header('x-goog-api-key')[0] ?? '', $r->header('Api-Revision')[0] ?? ''];

            return Http::response(['id' => 'i1', 'status' => 'completed', 'steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'ini gambarnya'], ['type' => 'image', 'mime_type' => 'image/png', 'data' => self::PNG]]]]]);
        }]);
        $this->actingUser($this->user());
        $r = $this->json_('POST', '/app/jobs', ['type' => 'photo_generate', 'prompt' => 'Kopi susu di meja kayu', 'params' => ['count' => '1', 'ratio' => '4:5']])->assertCreated();
        $job = AiJob::findOrFail($r->json('data.id'));

        $this->assertSame('completed', $job->status, (string) $job->error);
        $this->assertCount(1, $job->result['media']);
        $this->assertSame(555, (int) $job->provider_cost);
        [$body, $key, $rev] = $sent[0];
        $this->assertSame('gemini-nano-banana-2.1', $body['model']);
        $this->assertSame('g-test', $key);
        $this->assertSame('2026-05-20', $rev);
        $this->assertSame('image', $body['response_format']['type']);
        $this->assertSame('4:5', $body['response_format']['aspect_ratio']);
        $this->assertStringContainsString('Kopi susu', $body['input'][0]['text']);
    }

    public function test_gemini_video_downloads_uri_result_from_files_api(): void
    {
        $this->gemini();
        $calls = [];
        Http::fake(function (Request $r) use (&$calls) {
            $calls[] = $r->method().' '.$r->url();
            if (str_ends_with($r->url(), '/interactions')) {
                return Http::response(['id' => 'v1', 'status' => 'completed', 'steps' => [['type' => 'model_output', 'content' => [['type' => 'video', 'mime_type' => 'video/mp4', 'uri' => 'https://generativelanguage.googleapis.com/v1beta/files/abc123']]]]]);
            }
            if (str_contains($r->url(), 'files/abc123:download')) {
                return Http::response('MP4BYTES', 200, ['Content-Type' => 'video/mp4']);
            }
            if (str_ends_with($r->url(), 'files/abc123')) {
                return Http::response(['name' => 'files/abc123', 'state' => 'ACTIVE']);
            }

            return Http::response([], 404);
        });
        $u = $this->user();
        $job = AiJob::create(['user_id' => $u->id, 'type' => 'video_generate', 'entitlement' => 'video.generate', 'prompt' => 'Kopi diseduh', 'params' => ['duration' => 5, 'ratio' => '9:16'], 'cost' => 80, 'status' => 'queued']);
        AiGateway::process($job);
        $job->refresh();

        $this->assertSame('completed', $job->status, (string) $job->error);
        $this->assertTrue(collect($calls)->contains(fn ($c) => str_contains($c, 'files/abc123:download')));
    }

    public function test_gemini_failure_refunds_and_reports_error(): void
    {
        $this->gemini();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'API key not valid']], 400)]);
        $this->actingUser($u = $this->user());
        $r = $this->json_('POST', '/app/jobs', ['type' => 'photo_generate', 'prompt' => 'X', 'params' => ['count' => '1']])->assertCreated();
        $job = AiJob::findOrFail($r->json('data.id'));
        $this->assertSame('failed', $job->status);
        $this->assertDatabaseHas('credit_ledger', ['user_id' => $u->id, 'direction' => 'credit', 'amount' => 8]);
    }

    public function test_admin_connection_test_works_without_base_url_for_claude(): void
    {
        $p = $this->claude('claude-haiku-5-5', [new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['data' => [['type' => 'model', 'id' => 'claude-haiku-5-5', 'display_name' => 'Claude Haiku 5.5', 'created_at' => '2026-01-01T00:00:00Z']], 'has_more' => false, 'first_id' => 'claude-haiku-5-5', 'last_id' => 'claude-haiku-5-5']))]);
        $this->admin();
        $this->json_('POST', "/admin/ai-providers/{$p->id}/test")->assertOk()->assertJsonPath('data.ok', true);
        $this->assertStringEndsWith('/v1/models', $this->sent[0]['request']->getUri()->getPath());
    }
}
