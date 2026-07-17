<?php

namespace App\Console\Commands;

use App\Services\Pricing\ProductPriceRecalculator;
use Illuminate\Console\Command;

class RecalculateProductPricesCommand extends Command
{
    protected $signature = 'bnc:recalculate-prices
                            {--supplier= : Supplier ID}
                            {--category= : Category ID}';

    protected $description = 'Recalculate product prices using supplier wholesale prices and margin rules';

    public function handle(ProductPriceRecalculator $recalculator): int
    {
        $supplierId = $this->option('supplier') !== null ? (int) $this->option('supplier') : null;
        $categoryId = $this->option('category') !== null ? (int) $this->option('category') : null;

        $this->info('Recalculating product prices...');

        $count = $recalculator->forAll($supplierId, $categoryId);

        $this->info("Recalculated {$count} products.");

        return self::SUCCESS;
    }
}
