<?php

namespace App\Jobs;

use App\Models\AnanasSyncRun;
use App\Services\Ananas\AnanasDailyOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunAnanasScheduledJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

    public int $tries = 1;

    public function __construct(
        public int $runId,
    ) {
        $this->onQueue('sync');
    }

    public function handle(AnanasDailyOrchestrator $orchestrator): void
    {
        $orchestrator->execute($this->runId);
    }

    public function failed(?Throwable $exception): void
    {
        $run = AnanasSyncRun::query()->find($this->runId);

        if ($run === null || ! $run->isActive()) {
            return;
        }

        $run->update([
            'status' => AnanasSyncRun::STATUS_FAILED,
            'error_message' => $exception !== null
                ? 'Queue job failed: '.$exception->getMessage()
                : 'Queue job failed: worker timeout or process crash.',
            'finished_at' => now(),
        ]);
    }
}
