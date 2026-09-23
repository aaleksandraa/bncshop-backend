<?php

namespace App\Console\Commands;

use App\Jobs\RunOlxSyncJob;
use App\Services\Olx\OlxSyncSettings;
use Illuminate\Console\Command;

class SyncOlxStockScheduledCommand extends Command
{
    protected $signature = 'bnc:sync-olx-stock';

    protected $description = 'Dispatch hourly OLX stock/status sync (hide, unhide, delete) without the daily create cap';

    public function handle(OlxSyncSettings $settings): int
    {
        if (! $settings->isEnabled() || ! $settings->autoSyncEnabled()) {
            $this->line('OLX auto sync disabled.');

            return self::SUCCESS;
        }

        $source = $settings->apiSource();

        if ($source !== null && $settings->hasRunningBulkSyncJob($source->id, includeStock: true)) {
            $this->line('OLX sync already running — skipping stock dispatch.');

            return self::SUCCESS;
        }

        RunOlxSyncJob::dispatch(false, null, null, null, true);
        $this->info('OLX stock sync dispatched.');

        return self::SUCCESS;
    }
}
