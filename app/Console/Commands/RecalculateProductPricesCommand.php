<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Pricing\ProductPriceRecalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RecalculateProductPricesCommand extends Command
{
    protected $signature = 'bnc:recalculate-prices
                            {--supplier= : Supplier ID}
                            {--category= : Category ID}
                            {--product= : Product ID or slug (single product, for a quick check)}';

    protected $description = 'Recalculate product prices using supplier wholesale prices and margin rules';

    public function handle(ProductPriceRecalculator $recalculator): int
    {
        set_time_limit(0);
        ignore_user_abort(true);

        $startedAt = microtime(true);
        $productKey = $this->option('product');

        if (is_string($productKey) && $productKey !== '') {
            return $this->recalculateOne($recalculator, $productKey);
        }

        $supplierId = $this->option('supplier') !== null ? (int) $this->option('supplier') : null;
        $categoryId = $this->option('category') !== null ? (int) $this->option('category') : null;

        $this->logLine('Recalculating product prices...');

        $count = $recalculator->forAll($supplierId, $categoryId, function (int $count, int $lastId): void {
            if ($count % 500 !== 0) {
                return;
            }

            $this->logLine("Progress: {$count} products (last id {$lastId})");
        });

        $elapsed = number_format(microtime(true) - $startedAt, 1, '.', '');
        $this->logLine("Recalculated {$count} products in {$elapsed}s.");

        return self::SUCCESS;
    }

    private function recalculateOne(ProductPriceRecalculator $recalculator, string $productKey): int
    {
        $product = ctype_digit($productKey)
            ? Product::query()->find((int) $productKey)
            : Product::query()->where('slug', $productKey)->first();

        if (! $product) {
            $this->error("Product not found: {$productKey}");

            return self::FAILURE;
        }

        $before = (float) ($product->regular_price ?? 0);
        $recalculator->forProduct($product);
        $fresh = $product->fresh();

        $this->logLine(sprintf(
            'Product #%d %s: regular %s → %s KM, margin %s%%',
            $fresh->id,
            $fresh->slug,
            number_format($before, 2, '.', ''),
            number_format((float) $fresh->regular_price, 2, '.', ''),
            $fresh->margin_percentage !== null ? number_format((float) $fresh->margin_percentage, 2, '.', '') : '—',
        ));

        return self::SUCCESS;
    }

    private function logLine(string $message): void
    {
        $this->info($message);
        Log::info('[bnc:recalculate-prices] '.$message);
    }
}
