<?php

namespace App\Console\Commands;

use App\Models\AnanasSyncRun;
use App\Services\Ananas\AnanasDailyOrchestrator;
use Illuminate\Console\Command;

class AnanasPullCatalogCommand extends Command
{
    protected $signature = 'bnc:ananas-pull-catalog {--confirm : Execute live (otherwise dry info only)}';

    protected $description = 'Pull full Ananas GET /products catalog (daily job entry point)';

    public function handle(AnanasDailyOrchestrator $orchestrator): int
    {
        if (! $this->option('confirm')) {
            $this->warn('Use --confirm to queue the catalog pull job.');

            return self::SUCCESS;
        }

        $run = $orchestrator->dispatchIfAllowed(AnanasSyncRun::JOB_CATALOG, 'cli');

        if ($run === null) {
            $this->error('Could not queue catalog job (locked or already running).');

            return self::FAILURE;
        }

        $this->info('Catalog pull queued as run #'.$run->id);

        return self::SUCCESS;
    }
}
