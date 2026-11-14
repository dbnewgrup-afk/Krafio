<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Unduh model ke runtime lokal Ollama (konsep manajemen model Jan, Bab 53) dengan progres yang dibaca Admin.
 * POST {host}/api/pull {"model": "...", "stream": true} → baris JSON {status, total, completed}.
 */
class PullLocalModel implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public string $host, public string $model) {}

    public static function key(string $model): string
    {
        return 'ai-local:pull:'.$model;
    }

    public function handle(): void
    {
        $key = self::key($this->model);
        Cache::put($key, ['status' => 'mulai', 'pct' => 0, 'done' => false], 7200);
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => json_encode(['model' => $this->model, 'stream' => true]), 'timeout' => 3600, 'ignore_errors' => true]]);
        $fh = @fopen(rtrim($this->host, '/').'/api/pull', 'r', false, $ctx);
        if (! $fh) {
            Cache::put($key, ['status' => 'Gagal terhubung ke runtime lokal', 'pct' => 0, 'done' => true, 'error' => true], 7200);

            return;
        }
        $last = 0.0;
        while (($line = fgets($fh)) !== false) {
            $d = json_decode($line, true) ?: [];
            if (! empty($d['error'])) {
                Cache::put($key, ['status' => (string) $d['error'], 'pct' => 0, 'done' => true, 'error' => true], 7200);
                fclose($fh);

                return;
            }
            $pct = ! empty($d['total']) ? round(100 * ($d['completed'] ?? 0) / $d['total'], 1) : null;
            if (microtime(true) - $last > 1 || ($d['status'] ?? '') === 'success') {
                Cache::put($key, ['status' => (string) ($d['status'] ?? ''), 'pct' => $pct, 'done' => ($d['status'] ?? '') === 'success'], 7200);
                $last = microtime(true);
            }
        }
        fclose($fh);
    }
}
