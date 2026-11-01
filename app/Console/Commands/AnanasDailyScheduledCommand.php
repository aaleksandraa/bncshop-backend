<?php

namespace App\Console\Commands;

use App\Models\AnanasSyncRun;
use App\Services\Ananas\AnanasDailyOrchestrator;
use App\Services\Ananas\AnanasSyncRunService;
use Illuminate\Console\Command;

class AnanasDailyScheduledCommand extends Command
{
    protected $signature = 'bnc:ananas-daily {job : catalog|takedown|price_stock|discounts|import|intraday_stock}';

    protected $description = 'Dispatch a scheduled Ananas daily job when gates allow';

    public function handle(AnanasDailyOrchestrator $orchestrator, AnanasSyncRunService $runs): int
    {
        $job = strtolower(trim((string) $this->argument('job')));

        $map = [
            'catalog' => AnanasSyncRun::JOB_CATALOG,
            'takedown' => AnanasSyncRun::JOB_TAKEDOWN,
            'price_stock' => AnanasSyncRun::JOB_PRICE_STOCK,
            'price-stock' => AnanasSyncRun::JOB_PRICE_STOCK,
            'discounts' => AnanasSyncRun::JOB_DISCOUNTS,
            'import' => AnanasSyncRun::JOB_IMPORT,
            'intraday_stock' => AnanasSyncRun::JOB_INTRADAY_STOCK,
            'stock' => AnanasSyncRun::JOB_INTRADAY_STOCK,
        ];

        $jobKey = $map[$job] ?? null;

        if ($jobKey === null) {
            $this->error('Unknown job. Use catalog, price_stock, discounts, import, or intraday_stock.');

            return self::FAILURE;
        }

        $runs->recoverStaleRuns();

        $run = $orchestrator->dispatchIfAllowed($jobKey);

        if ($run === null) {
            $this->line('Ananas daily job not dispatched (disabled, locked, or gated).');

            return self::SUCCESS;
        }

        $this->info(sprintf('Ananas daily job %s queued as run #%d.', $jobKey, $run->id));

        return self::SUCCESS;
    }
}
