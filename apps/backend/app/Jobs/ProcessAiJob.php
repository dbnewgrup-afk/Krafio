<?php

namespace App\Jobs;

use App\Models\AiJob;
use App\Services\AiGateway;
use App\Services\Entitlements;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Worker job AI (PRD 11.5, 49): diproses di luar request web; gagal → kuota dikembalikan (Design Spec §17.4). */
class ProcessAiJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $jobId) {}

    public function handle(): void
    {
        $job = AiJob::find($this->jobId);
        if (! $job) {
            return;
        }
        try {
            AiGateway::process($job);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    public function failed(?\Throwable $e): void
    {
        $job = AiJob::find($this->jobId);
        if (! $job || $job->status === 'completed') {
            return;
        }
        $job->update(['status' => 'failed', 'error' => 'Proses gagal di penyedia AI.', 'completed_at' => now()]);
        \App\Http\Controllers\Api\StudioController::refundJob($job, 'Pengembalian kredit job gagal #'.$job->id);
    }

    public static function enqueue(AiJob $job): void
    {
        if (config('krafio.jobs_inline')) {
            // Mode pengembangan tanpa worker: diproses setelah respons dikirim.
            self::dispatchAfterResponse($job->id);
        } else {
            self::dispatch($job->id)->onQueue('ai');
        }
    }
}
