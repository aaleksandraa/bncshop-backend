<?php

namespace App\Jobs;

use App\Models\ApiImportJob;
use App\Models\ApiSource;
use App\Services\Sync\SyncOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\TimeoutExceededException;
use InvalidArgumentException;
use Throwable;

class RunApiSyncJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 10800;

    public int $tries = 1;

    public int $maxExceptions = 1;

    public function __construct(
        public ApiSource $apiSource,
        public bool $fullSync = false,
        public ?int $maxProductPages = null,
        public ?int $startProductPage = null,
        public bool $skipMetadata = false,
        public ?int $importJobId = null,
        public ?string $modifiedAfter = null,
    ) {
        $this->onQueue('sync');
        $this->timeout = max(300, (int) config('bnc.a1_sync_job_timeout', 10800));
    }

    public function handle(SyncOrchestrator $orchestrator): void
    {
        if (! $this->apiSource->usesIntegrationApiImport()) {
            throw new InvalidArgumentException(sprintf(
                'API source #%d (%s) does not use IntegrationApiClient import. Use the dedicated sync pipeline instead.',
                $this->apiSource->id,
                $this->apiSource->name,
            ));
        }

        $explicitPageCap = $this->maxProductPages !== null && $this->importJobId === null;
        $chunked = ! $explicitPageCap;
        $pagesPerJob = $explicitPageCap
            ? $this->maxProductPages
            : max(1, (int) config('bnc.a1_sync_pages_per_job', 40));
        $timeBudget = $chunked
            ? max(60, (int) config('bnc.a1_sync_job_time_budget_seconds', 5400))
            : null;

        $stats = $orchestrator->run(
            $this->apiSource,
            $this->fullSync,
            $pagesPerJob,
            $this->startProductPage,
            $this->skipMetadata || $this->importJobId !== null,
            $this->importJobId,
            $this->modifiedAfter,
            $timeBudget,
            $chunked,
        );

        $nextPage = $stats['products']['next_page'] ?? $stats['next_page'] ?? null;

        if ($chunked && $nextPage !== null) {
            static::dispatch(
                $this->apiSource->fresh() ?? $this->apiSource,
                $this->fullSync,
                null,
                (int) $nextPage,
                skipMetadata: true,
                importJobId: (int) ($stats['import_job_id'] ?? $this->importJobId),
                modifiedAfter: is_string($stats['modified_after'] ?? null)
                    ? $stats['modified_after']
                    : $this->modifiedAfter,
            );
        }
    }

    public function failed(?Throwable $exception): void
    {
        $job = $this->importJobId !== null
            ? ApiImportJob::query()->find($this->importJobId)
            : ApiImportJob::query()
                ->where('api_source_id', $this->apiSource->id)
                ->where('status', 'running')
                ->latest('id')
                ->first();

        if ($job === null || $job->status !== 'running') {
            return;
        }

        $lastPage = $job->items()->max('page');
        $shouldContinue = $lastPage !== null && $this->isTimeoutFailure($exception);

        if ($shouldContinue) {
            static::dispatch(
                $this->apiSource,
                $this->fullSync,
                null,
                (int) $lastPage + 1,
                skipMetadata: true,
                importJobId: $job->id,
                modifiedAfter: is_string(data_get($job->stats, 'modified_after'))
                    ? data_get($job->stats, 'modified_after')
                    : $this->modifiedAfter,
            );

            return;
        }

        $job->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $exception?->getMessage()
                ?? 'Queue job failed: worker timeout or process crash.',
        ]);
    }

    private function isTimeoutFailure(?Throwable $exception): bool
    {
        if ($exception instanceof TimeoutExceededException) {
            return true;
        }

        $message = $exception?->getMessage() ?? '';

        return str_contains($message, 'timeout')
            || str_contains($message, 'timed out')
            || str_contains($message, 'has timed out');
    }
}
