<?php

namespace App\Console\Commands;

use App\Services\Ananas\AnanasProductTypeSyncService;
use Illuminate\Console\Command;

class AnanasRefreshProductTypesCommand extends Command
{
    protected $signature = 'bnc:ananas-refresh-product-types';

    protected $description = 'Fetch Ananas product types from API and cache them locally';

    public function handle(AnanasProductTypeSyncService $syncService): int
    {
        try {
            $types = $syncService->refreshFromApi();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Cached %d Ananas product type(s).', count($types)));

        foreach (array_slice($types, 0, 10) as $type) {
            $this->line('- '.$type);
        }

        if (count($types) > 10) {
            $this->line('...');
        }

        return self::SUCCESS;
    }
}
