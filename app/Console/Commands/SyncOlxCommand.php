<?php

namespace App\Console\Commands;

use App\Jobs\RunOlxSyncJob;
use App\Services\Olx\OlxSyncOrchestrator;
use App\Services\Olx\OlxSyncSettings;
use Illuminate\Console\Command;

class SyncOlxCommand extends Command
{
    protected $signature = 'bnc:sync-olx
                            {--sync : Run synchronously instead of queue}
                            {--full : Force recompute all managed listings}
                            {--product= : Sync single product id}';

    protected $description = 'Run OLX export sync (incremental by default)';

    public function handle(OlxSyncOrchestrator $orchestrator, OlxSyncSettings $settings): int
    {
        if (! $settings->isEnabled()) {
            $this->warn('OLX export is disabled in admin settings.');

            return self::FAILURE;
        }

        $fullSync = (bool) $this->option('full');
        $productId = $this->option('product') !== null ? (int) $this->option('product') : null;

        if ($this->option('sync')) {
            $stats = $orchestrator->run($fullSync, $productId);
            $this->line(json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        RunOlxSyncJob::dispatch($fullSync, $productId);
        $this->info('OLX sync job dispatched.');

        return self::SUCCESS;
    }
}
