<?php

namespace App\Services\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use App\Models\AiModel;
use App\Models\AiProvider;
use GuzzleHttp\Client as Guzzle;
use Psr\Http\Client\ClientInterface;

/**
 * Driver teks Claude (Anthropic Messages API) lewat SDK resmi `anthropic-ai/sdk`.
 * Dipanggil oleh AiGateway::chat untuk provider ber-driver `anthropic`. API key diisi Admin (terenkripsi di DB).
 *
 * Pengaturan per model (tool Krafio = tulisan pendek/menengah, bukan penalaran berat):
 * - Haiku 5.5  → thinking dimatikan + effort low (paling hemat).
 * - Sonnet 5.5 → thinking `between_tools` (= tanpa thinking) + effort low; fallback server-side bila ditolak filter keamanan.
 * - Opus/Fable → thinking adaptif bawaan, effort low; fallback server-side.
 * Tidak mengirim temperature/frequency_penalty (ditolak model terbaru). Mode JSON → instruksi di system prompt;
 * TextWriter sudah memperbaiki JSON yang tidak rapi.
 */
class ClaudeDriver
{
    /** Untuk test: transport HTTP pengganti (Guzzle MockHandler). */
    public static ?ClientInterface $transport = null;

    /** @return array{text: string, input_tokens: int, output_tokens: int} */
    public static function chat(AiProvider $p, AiModel $m, array $messages, int $maxTokens, array $opts, int $timeout): array
    {
        [$system, $turns] = self::convert($messages);
        if (! empty($opts['json'])) {
            $system = trim($system."\n\nBalas HANYA dengan satu objek JSON yang valid, tanpa teks lain dan tanpa blok kode.");
        }
        $model = $m->model_id;
        $isHaiku = str_contains($model, 'haiku');
        $thinking = match (true) {
            $isHaiku => ['type' => 'disabled'],
            str_contains($model, 'sonnet') => ['type' => 'between_tools'],
            default => null,
        };
        // Model dengan thinking aktif butuh ruang token lebih
        $max = $thinking === null ? max($maxTokens * 2, 4000) : max(256, $maxTokens);

        $client = self::client($p, $timeout);
        try {
            $msg = $client->beta->messages->create(
                maxTokens: $max,
                messages: $turns,
                model: $model,
                system: $system !== '' ? $system : null,
                thinking: $thinking,
                outputConfig: ['effort' => 'low'],
                // Ditolak filter keamanan → server mencoba model cadangan (Haiku tidak punya fallback server-side)
                fallbacks: $isHaiku ? null : 'default',
                betas: $isHaiku ? null : ['server-side-fallback-2026-07-01'],
            );
        } catch (APIException $e) {
            throw new \RuntimeException('Claude menolak: '.mb_substr($e->getMessage(), 0, 200), 0, $e);
        }

        if ($msg->stopReason === 'refusal') {
            throw new \RuntimeException('Claude menolak permintaan ini (filter keamanan).');
        }
        $text = '';
        foreach ($msg->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return ['text' => $text, 'input_tokens' => (int) $msg->usage->inputTokens, 'output_tokens' => (int) $msg->usage->outputTokens];
    }

    private static function client(AiProvider $p, int $timeout): Client
    {
        return new Client(
            apiKey: (string) $p->api_key,
            baseUrl: $p->base_url ?: null,
            requestOptions: [
                'transporter' => self::$transport ?? new Guzzle(['timeout' => $timeout, 'connect_timeout' => 10]),
                'maxRetries' => 1,
            ],
        );
    }

    /** Uji koneksi Admin: key valid bila daftar model bisa dibaca. */
    public static function ping(AiProvider $p): bool
    {
        try {
            self::client($p, 10)->models->list(limit: 1);

            return true;
        } catch (APIException) {
            return false;
        }
    }

    /**
     * Pesan format OpenAI (role system/user/assistant) → system terpisah + giliran user/assistant yang berselang-seling,
     * dimulai dari user (syarat Messages API).
     *
     * @return array{0: string, 1: array<int, array{role: string, content: string}>}
     */
    public static function convert(array $messages): array
    {
        $system = [];
        $turns = [];
        foreach ($messages as $mm) {
            $role = (string) ($mm['role'] ?? 'user');
            $content = trim((string) ($mm['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            if ($role === 'system') {
                $system[] = $content;

                continue;
            }
            $role = $role === 'assistant' ? 'assistant' : 'user';
            if ($turns && end($turns)['role'] === $role) {
                $turns[count($turns) - 1]['content'] .= "\n\n".$content;
            } else {
                $turns[] = ['role' => $role, 'content' => $content];
            }
        }
        while ($turns && $turns[0]['role'] !== 'user') {
            array_shift($turns);
        }
        // Giliran terakhir harus user (prefill asisten ditolak model terbaru)
        while ($turns && end($turns)['role'] !== 'user') {
            array_pop($turns);
        }
        if (! $turns) {
            $turns[] = ['role' => 'user', 'content' => 'Lanjutkan.'];
        }

        return [implode("\n\n", $system), $turns];
    }
}
