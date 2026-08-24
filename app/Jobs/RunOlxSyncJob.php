<?php

namespace App\Jobs;

use App\Models\ApiImportJob;
use App\Services\Olx\OlxSyncOrchestrator;
use App\Services\Olx\OlxSyncSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunOlxSyncJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

    public int $tries = 1;

    public int $maxExceptions = 1;

    public function __construct(
        public bool $fullSync = false,
        public ?int $productId = null,
        public ?int $maxCreatesPerRun = null,
        public ?int $continueJobId = null,
    ) {
        $this->onQueue('sync');

        if ($maxCreatesPerRun !== null && $maxCreatesPerRun >= 300) {
            $this->timeout = 14400;
        }
    }

    public function handle(OlxSyncOrchestrator $orchestrator): void
    {
        $orchestrator->run($this->fullSync, $this->productId, $this->maxCreatesPerRun, $this->continueJobId);
    }

    public function failed(?Throwable $exception): void
    {
        $source = app(OlxSyncSettings::class)->apiSource();

        if ($source === null) {
            return;
        }

        $job = ApiImportJob::query()
            ->where('api_source_id', $source->id)
            ->whereIn('type', ['olx_incremental', 'olx_full'])
            ->where('status', 'running')
            ->latest()
            ->first();

        if ($job === null) {
            return;
        }

        $pending = data_get($job->stats, 'pending');
        $hasPending = is_array($pending) && (
            ($pending['create'] ?? []) !== []
            || ($pending['update'] ?? []) !== []
            || ($pending['hide'] ?? []) !== []
            || ($pending['unhide'] ?? []) !== []
        );

        if ($hasPending) {
            $job->update([
                'error_message' => 'Wave timed out; resuming remaining OLX listings.',
            ]);

            static::dispatch($this->fullSync, null, $this->maxCreatesPerRun, $job->id);

            return;
        }

        $job->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $exception !== null
                ? 'Queue job failed: '.$exception->getMessage()
                : 'Queue job failed: worker timeout or process crash.',
        ]);
    }
}
