<?php

namespace App\Console\Commands;

use App\Services\Marketing\MarketingContactSyncService;
use Illuminate\Console\Command;

class SyncMarketingContactsCommand extends Command
{
    protected $signature = 'marketing-contacts:sync';

    protected $description = 'Sinhronizuje kupce (registrovane i goste) u marketing kontakte';

    public function handle(MarketingContactSyncService $syncService): int
    {
        $count = $syncService->syncAll();

        $this->info("Sinhronizovano {$count} kontakata.");

        return self::SUCCESS;
    }
}
