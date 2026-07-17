<?php

namespace App\Console\Commands;

use App\Jobs\RunElineSyncJob;
use App\Models\ApiSource;
use App\Services\Eline\ElineSyncOrchestrator;
use Illuminate\Console\Command;

class SyncElineCommand extends Command
{
    protected $signature = 'bnc:sync-eline
                            {--sync : Run synchronously instead of queue}
                            {--full : Import all mapped products (not just new/changed)}
                            {--refresh-categories : Refresh eLine category discovery before sync}';

    protected $description = 'Run eLine ERP sync (incremental by default: only new/changed products)';

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

        $fullSync = (bool) $this->option('full');
        $refreshCategories = (bool) $this->option('refresh-categories');

        $mode = $fullSync ? 'full' : 'incremental';
        $this->info("eLine {$mode} sync for source #{$source->id} ({$source->name})");

        if ($this->option('sync')) {
            $stats = $orchestrator->run($source, $fullSync, $refreshCategories);
            $this->line(json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        RunElineSyncJob::dispatch($source, $fullSync, $refreshCategories);
        $this->info('eLine sync job dispatched.');

        return self::SUCCESS;
    }
}
