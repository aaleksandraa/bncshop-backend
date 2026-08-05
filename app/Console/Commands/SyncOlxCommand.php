<?php

namespace App\Console\Commands;

use App\Jobs\RunOlxSyncJob;
use App\Services\Olx\OlxDailyCreateLimiter;
use App\Services\Olx\OlxSyncOrchestrator;
use App\Services\Olx\OlxSyncSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncOlxCommand extends Command
{
    protected $signature = 'bnc:sync-olx
                            {--sync : Run synchronously instead of queue}
                            {--full : Force recompute all managed listings}
                            {--product= : Sync single product id}
                            {--max-creates= : Max new OLX listings this run (e.g. 350 for full daily quota)}
                            {--full-quota : Use entire remaining daily create limit this run}
                            {--clear-lock : Clear legacy ShouldBeUnique Redis lock before dispatch}';

    protected $description = 'Run OLX export sync (incremental by default)';

    public function handle(
        OlxSyncOrchestrator $orchestrator,
        OlxSyncSettings $settings,
        OlxDailyCreateLimiter $createLimiter,
    ): int {
        if (! $settings->isEnabled()) {
            $this->warn('OLX export is disabled in admin settings.');

            return self::FAILURE;
        }

        $fullSync = (bool) $this->option('full');
        $productId = $this->option('product') !== null ? (int) $this->option('product') : null;

        if ($productId === null && $settings->hasRunningBulkSyncJob()) {
            $this->warn('OLX sync već radi — preskačem dispatch. Provjerite Import jobove.');

            return self::SUCCESS;
        }

        if ($this->option('clear-lock')) {
            foreach (['olx-sync-all-incremental', 'olx-sync-all-full'] as $suffix) {
                Cache::forget('laravel_unique_job:App\\Jobs\\RunOlxSyncJob:'.$suffix);
            }
            $this->line('Legacy unique lock očišćen.');
        }

        $maxCreatesPerRun = $this->resolveMaxCreatesPerRun($createLimiter);

        if ($maxCreatesPerRun !== null) {
            $snapshot = $createLimiter->snapshot($maxCreatesPerRun);
            $this->line(sprintf(
                'Create quota this run: %d (danas %d/%d, max po run-u %d)',
                $snapshot['allowed_this_run'],
                $snapshot['creates_today'],
                $snapshot['daily_limit'],
                $snapshot['max_per_run'],
            ));
        }

        if ($this->option('sync')) {
            $stats = $orchestrator->run($fullSync, $productId, $maxCreatesPerRun);
            $this->line(json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        RunOlxSyncJob::dispatch($fullSync, $productId, $maxCreatesPerRun);
        $this->info('OLX sync job dispatched.');

        return self::SUCCESS;
    }

    private function resolveMaxCreatesPerRun(OlxDailyCreateLimiter $createLimiter): ?int
    {
        if ($this->option('full-quota')) {
            return $createLimiter->dailyLimit();
        }

        if ($this->option('max-creates') === null) {
            return null;
        }

        return max(1, (int) $this->option('max-creates'));
    }
}
