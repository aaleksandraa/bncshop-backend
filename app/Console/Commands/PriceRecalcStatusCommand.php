<?php

namespace App\Console\Commands;

use App\Services\Pricing\ProductPriceRecalcStatus;
use Illuminate\Console\Command;

class PriceRecalcStatusCommand extends Command
{
    protected $signature = 'bnc:price-recalc-status';

    protected $description = 'Show catalog price recalculation progress (queue + calculated_price coverage)';

    public function handle(ProductPriceRecalcStatus $status): int
    {
        $snapshot = $status->snapshot();

        $this->info('Catalog price recalculation');
        $this->line('  Total A1 products: '.$snapshot['total']);
        $this->line('  Recalculated (calculated_price set): '.$snapshot['done']);
        $this->line('  Waiting (calculated_price empty): '.$snapshot['pending']);
        $this->line('  Real mismatch (regular ≠ calculated): '.$snapshot['mismatch']);
        $this->line('  Pending jobs on default queue: '.$snapshot['queue_pending']);
        $this->comment('  (default queue size is ALL jobs, not product count)');

        if ($snapshot['queue_storm']) {
            $this->error('  → Queue is oversized. Do not start another recalculation.');
            $this->line('  → After deploy, leftover RecalculateAllProductPricesJob chunks should stop chaining and drain.');
        } elseif ($snapshot['in_progress']) {
            $this->comment('  → Queue has jobs. Waiting calculated_price count should fall if workers are on new code.');
        } elseif ($snapshot['stalled']) {
            $this->warn('  → Not running. Queue is empty but calculated_price is still missing.');
            $this->line('  → In admin: Proizvodi → Preračunaj sve cijene');
            $this->line('  → Or SSH: php artisan bnc:recalculate-prices --queue');
        } else {
            $this->comment('  → Recalculation finished.');
        }

        return self::SUCCESS;
    }
}
