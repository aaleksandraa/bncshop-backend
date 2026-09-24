<?php

namespace App\Console\Commands;

use App\Jobs\RunElineContentReconcileJob;
use App\Models\ApiSource;
use Illuminate\Console\Command;

class SyncElineContentScheduledCommand extends Command
{
    protected $signature = 'bnc:sync-eline-content-scheduled';

    protected $description = 'Dispatch scheduled eLine content reconcile (used by scheduler)';

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

        RunElineContentReconcileJob::dispatch($source);
        $this->info("eLine content reconcile dispatched for source #{$source->id} ({$source->name}).");

        return self::SUCCESS;
    }
}
