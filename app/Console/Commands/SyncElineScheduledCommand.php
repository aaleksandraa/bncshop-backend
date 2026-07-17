<?php

namespace App\Console\Commands;

use App\Jobs\RunElineSyncJob;
use App\Models\ApiSource;
use Illuminate\Console\Command;

class SyncElineScheduledCommand extends Command
{
    protected $signature = 'bnc:sync-eline-scheduled';

    protected $description = 'Dispatch scheduled incremental eLine sync (used by scheduler)';

    public function handle(): int
    {
        $source = ApiSource::query()
            ->where('target_system_code', 'eline')
            ->where('is_active', true)
            ->first();

        if ($source === null) {
            $this->line('No active eLine source found.');

            return self::SUCCESS;
        }

        RunElineSyncJob::dispatch($source, fullSync: false, refreshCategories: false);
        $this->info("Incremental eLine sync dispatched for source #{$source->id} ({$source->name}).");

        return self::SUCCESS;
    }
}
