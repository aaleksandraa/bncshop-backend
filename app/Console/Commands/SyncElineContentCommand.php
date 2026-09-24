<?php

namespace App\Console\Commands;

use App\Jobs\RunElineContentReconcileJob;
use App\Models\ApiSource;
use App\Services\Eline\ElineSyncOrchestrator;
use Illuminate\Console\Command;

class SyncElineContentCommand extends Command
{
    protected $signature = 'bnc:sync-eline-content
                            {--sync : Run synchronously instead of queue}';

    protected $description = 'Reconcile eLine product name and description against the feed (nightly content pass)';

    public function handle(ElineSyncOrchestrator $orchestrator): int
    {
        $source = ApiSource::query()
            ->where('target_system_code', 'eline')
            ->where('is_active', true)
            ->first();

        if ($source === null) {
            $this->error('No active eLine API source found.');

            return self::FAILURE;
        }

        $this->info("eLine content reconcile for source #{$source->id} ({$source->name})");

        if ($this->option('sync')) {
            $stats = $orchestrator->runContentReconcile($source);
            $this->line(json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        RunElineContentReconcileJob::dispatch($source);
        $this->info('eLine content reconcile job dispatched.');

        return self::SUCCESS;
    }
}
