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

        if ($snapshot['in_progress']) {
            $this->comment('  → In progress. Refresh this command; the waiting count should fall.');
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
