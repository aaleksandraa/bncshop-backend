<?php

namespace App\Console\Commands;

use App\Models\OlxCategory;
use App\Services\Olx\OlxCategoryDiscoveryService;
use Illuminate\Console\Command;

class OlxDiscoverAttributesCommand extends Command
{
    protected $signature = 'bnc:olx-discover-attributes {category? : OLX category id}';

    protected $description = 'Discover OLX category attributes and cache them locally';

    public function handle(OlxCategoryDiscoveryService $discovery): int
    {
        $categoryId = $this->argument('category');

        if ($categoryId !== null) {
            $result = $discovery->discoverAttributesForCategory((int) $categoryId);
            $this->info("Category {$result['category_id']}: {$result['attributes']} attributes cached.");

            return self::SUCCESS;
        }

        $results = $discovery->discoverAllAttributes();
        $total = array_sum(array_column($results, 'attributes'));
        $this->info("Cached {$total} attributes across ".count($results).' categories.');

        return self::SUCCESS;
    }
}
