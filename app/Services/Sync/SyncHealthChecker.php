<?php

namespace App\Services\Sync;

use App\Jobs\RunApiSyncJob;
use App\Jobs\RunOlxSyncJob;
use App\Models\ApiImportJob;
use App\Models\ApiSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Throwable;

class SyncHealthChecker
{
    public function __construct(
        private readonly IncrementalSyncScheduler $scheduler,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forSource(ApiSource $source): array
    {
        $nextSyncAt = $source->nextSyncAt();
        $isOverdue = $nextSyncAt !== null
            && $source->auto_sync_enabled
            && $source->is_active
            && $source->usesIntegrationApiImport()
            && $nextSyncAt->isPast()
            && ! $this->scheduler->hasRunningJob($source);

        $runningJob = ApiImportJob::query()
            ->where('api_source_id', $source->id)
            ->where('status', 'running')
            ->latest()
            ->first();

        $runningProgress = $runningJob ? $this->runningJobProgress($runningJob) : null;

        return [
            'next_sync_at' => $nextSyncAt,
            'is_overdue' => $isOverdue,
            'overdue_since' => $isOverdue ? $nextSyncAt : null,
            'overdue_human' => $isOverdue ? $nextSyncAt->diffForHumans(now(), true) : null,
            'is_due' => $this->scheduler->isDue($source),
            'has_running_job' => $runningJob !== null,
            'running_job' => $runningJob ? [
                'id' => $runningJob->id,
                'type' => $runningJob->type,
                'started_at' => $runningJob->started_at,
                'running_for' => $runningJob->started_at?->diffForHumans(now(), true),
                'progress' => $runningProgress,
            ] : null,
            'issues' => $this->detectIssues($source, $isOverdue, $runningJob, $runningProgress),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function runningJobProgress(ApiImportJob $job): ?array
    {
        $items = $job->items()->orderByDesc('page')->limit(1)->get();

        if ($items->isEmpty()) {
            return [
                'pages' => 0,
                'products' => 0,
                'status' => 'waiting_for_first_page',
            ];
        }

        $lastItem = $items->first();
        $totalPages = $job->items()->count();
        $totalProducts = (int) $job->items()->sum('records_count');

        return [
            'pages' => $totalPages,
            'products' => $totalProducts,
            'last_page' => $lastItem->page,
            'last_page_duration_sec' => round(($lastItem->duration_ms ?? 0) / 1000, 1),
            'status' => ($lastItem->duration_ms ?? 0) > 120000 ? 'slow_api_page' : 'processing',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function infrastructure(): array
    {
        $queueConnection = config('queue.default');
        $defaultQueueSize = null;
        $syncQueueSize = null;
        $analyticsQueueSize = null;
        $redisOk = false;

        try {
            $redisOk = (bool) Redis::ping();
            if ($queueConnection === 'redis') {
                $defaultQueueSize = (int) Redis::llen('queues:default');
                $syncQueueSize = (int) Redis::llen('queues:sync');
                $analyticsQueueSize = (int) Redis::llen('queues:analytics');
            }
        } catch (Throwable) {
            $redisOk = false;
        }

        return [
            'queue_connection' => $queueConnection,
            'redis_ok' => $redisOk,
            'default_queue_size' => $defaultQueueSize,
            'sync_queue_size' => $syncQueueSize,
            'analytics_queue_size' => $analyticsQueueSize,
            'scheduler_command' => 'bnc:sync-scheduled',
            'scheduler_interval' => 'every 5 minutes (requires cron schedule:run)',
            'worker_recommendation' => 'php artisan queue:work redis --queue=sync,default,scout,analytics --timeout=14400',
        ];
    }

    public const STALE_RUNNING_JOB_MESSAGE = 'Job marked failed: exceeded maximum running time (worker may have crashed).';

    public function releaseStaleRunningJobs(?int $maxIdleMinutes = null): int
    {
        $idleMinutes = max(1, $maxIdleMinutes ?? (int) config('bnc.a1_sync_stale_idle_minutes', 45));
        $cutoff = now()->subMinutes($idleMinutes);
        $released = 0;

        $jobs = ApiImportJob::query()
            ->where('status', 'running')
            ->where('started_at', '<', $cutoff)
            ->get();

        foreach ($jobs as $job) {
            if ($this->lastJobActivityAt($job)?->gte($cutoff)) {
                continue;
            }

            if (in_array($job->type, ['olx_incremental', 'olx_full'], true)) {
                $olxCutoff = now()->subMinutes(max(
                    $idleMinutes,
                    (int) config('bnc.olx_sync_stale_idle_minutes', 90),
                ));

                if ($this->lastJobActivityAt($job)?->gte($olxCutoff)) {
                    continue;
                }

                $pending = data_get($job->stats, 'pending');
                $hasPending = is_array($pending) && (
                    ($pending['create'] ?? []) !== []
                    || ($pending['update'] ?? []) !== []
                    || ($pending['hide'] ?? []) !== []
                    || ($pending['unhide'] ?? []) !== []
                );

                if ($hasPending) {
                    RunOlxSyncJob::dispatch(
                        $job->type === 'olx_full',
                        null,
                        is_numeric(data_get($job->stats, 'limits.max_per_run'))
                            ? (int) data_get($job->stats, 'limits.max_per_run')
                            : null,
                        $job->id,
                    );
                    $job->touch();

                    continue;
                }
            }

            $lastPage = $job->items()->max('page');
            $source = $job->apiSource;

            if ($lastPage !== null && $source?->usesIntegrationApiImport()) {
                RunApiSyncJob::dispatch(
                    $source,
                    fullSync: $job->type === 'full',
                    startProductPage: (int) $lastPage + 1,
                    skipMetadata: true,
                    importJobId: $job->id,
                    modifiedAfter: is_string(data_get($job->stats, 'modified_after'))
                        ? data_get($job->stats, 'modified_after')
                        : null,
                );

                $job->forceFill([
                    'stats' => array_merge($job->stats ?? [], [
                        'resumed_from_idle' => true,
                        'next_page' => (int) $lastPage + 1,
                    ]),
                ])->save();
                $job->touch();

                continue;
            }

            $job->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => self::STALE_RUNNING_JOB_MESSAGE,
            ]);
            $released++;
        }

        return $released;
    }

    private function lastJobActivityAt(ApiImportJob $job): ?Carbon
    {
        $lastItemAt = $job->items()->max('created_at');
        $timestamps = array_filter([
            $job->updated_at,
            $job->started_at,
            $lastItemAt !== null ? Carbon::parse($lastItemAt) : null,
        ]);

        if ($timestamps === []) {
            return null;
        }

        return collect($timestamps)->sort()->last();
    }

    /**
     * @param  array<string, mixed>|null  $runningProgress
     * @return list<string>
     */
    private function detectIssues(ApiSource $source, bool $isOverdue, ?ApiImportJob $runningJob, ?array $runningProgress = null): array
    {
        $issues = [];

        if ($source->last_successful_sync_at === null) {
            $issues[] = 'Potreban je inicijalni puni sync (last_successful_sync_at je prazan).';
        }

        if (! $source->auto_sync_enabled) {
            $issues[] = 'Automatski sync je isključen (auto_sync_enabled = false).';
        }

        if (! $source->is_active) {
            $issues[] = 'API izvor nije aktivan.';
        }

        if ($isOverdue) {
            $issues[] = 'Sync je zakasnio — provjerite da li cron pokreće schedule:run i da li queue worker radi na sync redu.';
        }

        if ($runningJob !== null) {
            if ($runningProgress !== null && ($runningProgress['status'] ?? '') === 'slow_api_page') {
                $issues[] = sprintf(
                    'A1 API sporo odgovara (stranica %d traje ~%ss). Sync nije zaglavljen — čeka se A1 server.',
                    $runningProgress['last_page'] ?? 0,
                    $runningProgress['last_page_duration_sec'] ?? '?',
                );
            } elseif ($this->lastJobActivityAt($runningJob)?->lt(now()->subMinutes(
                max(1, (int) config('bnc.a1_sync_stale_idle_minutes', 45))
            ))) {
                $issues[] = "Job #{$runningJob->id} nema aktivnosti predugo — moguće zaglavljen worker.";
            } elseif ($runningJob->started_at?->lt(now()->subHours(3))) {
                $issues[] = "Job #{$runningJob->id} još uvijek radi (catch-up od zadnjeg uspješnog synca). Neće se prekinuti dok ima napretka.";
            }
        }

        if ($this->scheduler->hasRecentFailure($source)) {
            $issues[] = 'Zadnji sync je pao — automatski retry je pauziran ~'
                .config('bnc.a1_sync_failure_cooldown_minutes', 30)
                .' min da se ne opterećuje A1 API. Ručno pokrenite sync ili sačekajte cooldown.';
        }

        if ($source->connection_status === 'error' && $source->last_error) {
            $lastError = $source->last_error;
            if (str_contains($lastError, '504 Gateway Time-out') || str_contains($lastError, '502 Bad Gateway')) {
                $issues[] = 'A1 Technoshop API (nginx) vraća timeout — problem je na njihovoj strani ili upit traje predugo. Sync koristi manje stranice (max '
                    .config('bnc.a1_api_max_page_size', 50)
                    .' proizvoda, inkrementalno '
                    .config('bnc.a1_api_incremental_page_size', 25)
                    .') i automatski retry.';
            }
            $issues[] = 'Zadnja greška konekcije: '.$lastError;
        }

        if (config('queue.default') === 'redis') {
            try {
                $defaultSize = (int) Redis::llen('queues:default');
                $syncQueueSize = (int) Redis::llen('queues:sync');
                $analyticsSize = (int) Redis::llen('queues:analytics');

                $syncJobsInDefault = $this->countJobsInQueue('default', 'App\\Jobs\\RunApiSyncJob');

                if ($syncQueueSize > 0 && $runningJob === null) {
                    $issues[] = "Sync queue ima {$syncQueueSize} job(ova) — pokrenite worker sa --queue=sync,...";
                }

                if ($syncJobsInDefault > 0 && $runningJob === null) {
                    $issues[] = "{$syncJobsInDefault} sync job(ova) čeka u default redu (stariji dispatch prije sync queue fixa). Pokrenite worker ili ponovo dispatch-ujte sync.";
                }

                if ($defaultSize > 100 && $runningJob === null && $syncJobsInDefault === 0) {
                    $issues[] = "Redis default queue ima {$defaultSize} jobova (legacy backlog). Sync ide na poseban sync queue — ovo ne blokira novi sync ako worker radi sync prvi.";
                }

                if ($analyticsSize > 500) {
                    $issues[] = "Analytics queue ima {$analyticsSize} jobova. Razmotrite php artisan queue:clear redis --queue=analytics u dev okruženju.";
                } elseif ($defaultSize > 100 && $runningJob === null) {
                    $issues[] = "Default queue ima {$defaultSize} legacy analytics/scout jobova. Novi analytics idu na analytics queue. Očistite: queue:clear redis --queue=default (dev).";
                }
            } catch (Throwable) {
                $issues[] = 'Redis nije dostupan — queue jobovi se neće procesirati.';
            }
        }

        return $issues;
    }

    private function countJobsInQueue(string $queue, string $jobClass): int
    {
        $count = 0;
        $total = (int) Redis::llen("queues:{$queue}");

        for ($i = 0; $i < $total; $i++) {
            $raw = Redis::lindex("queues:{$queue}", $i);
            $payload = json_decode($raw, true);
            if (($payload['displayName'] ?? '') === $jobClass) {
                $count++;
            }
        }

        return $count;
    }
}
