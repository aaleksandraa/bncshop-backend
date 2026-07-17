<?php

namespace App\Console\Commands;

use App\Jobs\RunApiSyncJob;
use App\Models\ApiSource;
use App\Services\Sync\IncrementalSyncScheduler;
use App\Services\Sync\SyncHealthChecker;
use Illuminate\Console\Command;

class SyncScheduledCommand extends Command
{
    protected $signature = 'bnc:sync-scheduled
                            {--force : Dispatch even if interval has not elapsed}';

    protected $description = 'Dispatch incremental sync jobs for API sources whose interval has elapsed';

    public function handle(IncrementalSyncScheduler $scheduler, SyncHealthChecker $health): int
    {
        $released = $health->releaseStaleRunningJobs();
        if ($released > 0) {
            $this->warn("Released {$released} stale running sync job(s).");
        }

        $sources = $this->option('force')
            ? ApiSource::query()->where('is_active', true)->get()->filter(
                fn (ApiSource $source): bool => $source->last_successful_sync_at !== null
                    && ! $scheduler->hasRunningJob($source)
            )
            : $scheduler->dueSources();

        if ($sources->isEmpty()) {
            $this->line('No API sources due for incremental sync.');

            return self::SUCCESS;
        }

        foreach ($sources as $source) {
            RunApiSyncJob::dispatch($source, fullSync: false, skipMetadata: true);

            $this->info("Incremental sync dispatched for source #{$source->id} ({$source->name}).");
        }

        return self::SUCCESS;
    }
}
