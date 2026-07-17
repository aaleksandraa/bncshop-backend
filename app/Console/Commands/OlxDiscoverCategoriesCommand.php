<?php

namespace App\Console\Commands;

use App\Services\Olx\OlxCategoryDiscoveryService;
use Illuminate\Console\Command;

class OlxDiscoverCategoriesCommand extends Command
{
    protected $signature = 'bnc:olx-discover-categories';

    protected $description = 'Discover OLX categories and cache them locally';

    public function handle(OlxCategoryDiscoveryService $discovery): int
    {
        $result = $discovery->discoverCategories();
        $this->info("Discovered {$result['discovered']} OLX categories.");

        foreach ($result['categories'] as $id => $path) {
            $this->line("  [{$id}] {$path}");
        }

        return self::SUCCESS;
    }
}
