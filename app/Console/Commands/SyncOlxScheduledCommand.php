<?php

namespace App\Console\Commands;

use App\Jobs\RunOlxSyncJob;
use App\Services\Olx\OlxSyncSettings;
use Illuminate\Console\Command;

class SyncOlxScheduledCommand extends Command
{
    protected $signature = 'bnc:sync-olx-scheduled';

    protected $description = 'Dispatch scheduled OLX export sync if enabled';

    public function handle(OlxSyncSettings $settings): int
    {
        if (! $settings->isEnabled() || ! $settings->autoSyncEnabled()) {
            $this->line('OLX auto sync disabled.');

            return self::SUCCESS;
        }

        $source = $settings->apiSource();

        if ($source !== null && $settings->hasRunningBulkSyncJob($source->id)) {
            $this->line('OLX sync already running — skipping dispatch.');

            return self::SUCCESS;
        }

        RunOlxSyncJob::dispatch(false, null);
        $this->info('OLX incremental sync dispatched.');

        return self::SUCCESS;
    }
}
