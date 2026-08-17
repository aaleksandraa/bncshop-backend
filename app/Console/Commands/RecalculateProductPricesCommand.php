<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Pricing\PriceCalculator;
use App\Services\Pricing\ProductPriceRecalculator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RecalculateProductPricesCommand extends Command
{
    protected $signature = 'bnc:recalculate-prices
                            {--supplier= : Supplier ID}
                            {--category= : Category ID}
                            {--product= : Product ID or slug (single product, for a quick check)}
                            {--dry-run : Compare stored vs calculated prices without writing}';

    protected $description = 'Recalculate product prices using supplier wholesale prices and margin rules';

    public function handle(ProductPriceRecalculator $recalculator, PriceCalculator $calculator): int
    {
        set_time_limit(0);
        ignore_user_abort(true);

        $startedAt = microtime(true);
        $productKey = $this->option('product');

        if (is_string($productKey) && $productKey !== '') {
            return $this->option('dry-run')
                ? $this->inspectOne($calculator, $productKey)
                : $this->recalculateOne($recalculator, $productKey);
        }

        if ($this->option('dry-run')) {
            return $this->reportMismatches($calculator, $startedAt);
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
        $product = $this->findProduct($productKey);

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

    private function inspectOne(PriceCalculator $calculator, string $productKey): int
    {
        $product = $this->findProduct($productKey);

        if (! $product) {
            $this->error("Product not found: {$productKey}");

            return self::FAILURE;
        }

        $result = $calculator->calculate($product->loadMissing(['supplierOffers.supplier', 'category']));
        $stored = (float) ($product->regular_price ?? 0);
        $ok = round($stored, 2) === round($result->regularPrice, 2);

        $this->logLine(sprintf(
            '[%s] Product #%d %s — stored %.2f KM, expected %.2f KM, margin %s%%',
            $ok ? 'OK' : 'MISMATCH',
            $product->id,
            $product->slug,
            $stored,
            $result->regularPrice,
            $result->appliedMargin !== null ? number_format($result->appliedMargin, 2, '.', '') : '—',
        ));

        return self::SUCCESS;
    }

    private function reportMismatches(PriceCalculator $calculator, float $startedAt): int
    {
        $this->logLine('Dry run: comparing stored prices with nabavna × marža × PDV (no writes).');

        $checked = 0;
        $inSync = 0;
        $mismatch = 0;
        $samples = [];

        $this->productQuery()
            ->with(['supplierOffers.supplier', 'category'])
            ->chunkById(500, function ($products) use ($calculator, &$checked, &$inSync, &$mismatch, &$samples): void {
                foreach ($products as $product) {
                    $checked++;
                    $result = $calculator->calculate($product);
                    $stored = (float) ($product->regular_price ?? 0);
                    $ok = round($stored, 2) === round($result->regularPrice, 2);

                    if ($ok) {
                        $inSync++;
                    } else {
                        $mismatch++;

                        if (count($samples) < 10) {
                            $samples[] = sprintf(
                                '  #%d %s — stored %.2f → expected %.2f KM',
                                $product->id,
                                Str::limit((string) $product->name, 40),
                                $stored,
                                $result->regularPrice,
                            );
                        }
                    }

                    if ($checked % 500 === 0) {
                        $this->logLine("Progress: checked {$checked} (mismatch {$mismatch})");
                    }
                }
            });

        $elapsed = number_format(microtime(true) - $startedAt, 1, '.', '');
        $this->newLine();
        $this->logLine("Checked: {$checked}");
        $this->logLine("In sync: {$inSync}");
        $this->logLine("Mismatch: {$mismatch}");
        $this->logLine("Elapsed: {$elapsed}s");

        if ($samples !== []) {
            $this->newLine();
            $this->warn('Examples:');
            foreach ($samples as $sample) {
                $this->line($sample);
            }
        }

        return self::SUCCESS;
    }

    private function productQuery(): Builder
    {
        $query = Product::query()->where('price_locked', false);

        if ($this->option('supplier') !== null) {
            $supplierId = (int) $this->option('supplier');
            $query->whereHas('supplierOffers', fn (Builder $offer) => $offer->where('supplier_id', $supplierId));
        }

        if ($this->option('category') !== null) {
            $categoryId = (int) $this->option('category');
            $ids = [$categoryId];
            $pending = [$categoryId];

            while ($pending !== []) {
                $children = \App\Models\Category::query()
                    ->whereIn('parent_id', $pending)
                    ->pluck('id')
                    ->all();
                $pending = $children;
                $ids = array_merge($ids, $children);
            }

            $query->whereIn('category_id', array_values(array_unique($ids)));
        }

        return $query;
    }

    private function findProduct(string $productKey): ?Product
    {
        return ctype_digit($productKey)
            ? Product::query()->find((int) $productKey)
            : Product::query()->where('slug', $productKey)->first();
    }

    private function logLine(string $message): void
    {
        $this->info($message);
        Log::info('[bnc:recalculate-prices] '.$message);
    }
}
