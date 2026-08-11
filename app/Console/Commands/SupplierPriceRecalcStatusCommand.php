<?php

namespace App\Console\Commands;

use App\Jobs\RecalculateSupplierProductPricesJob;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SupplierPriceRecalcStatusCommand extends Command
{
    protected $signature = 'bnc:supplier-price-recalc-status
                            {supplier? : Supplier ID or code (e.g. startech)}
                            {--run : Dispatch background recalculation job now}
                            {--sync : Recalculate immediately in this process (no queue)}';

    protected $description = 'Check supplier price adjustment status, queue jobs, and sample product prices';

    public function handle(PriceCalculator $priceCalculator): int
    {
        $supplier = $this->resolveSupplier($this->argument('supplier'));

        if (! $supplier) {
            $this->error('Supplier not found. Available suppliers:');
            Supplier::query()
                ->orderBy('display_name')
                ->get(['id', 'code', 'display_name', 'price_adjustment_amount'])
                ->each(fn (Supplier $s) => $this->line(sprintf(
                    '  #%d  %-12s  %s  (adjustment: %s KM)',
                    $s->id,
                    $s->code ?? '—',
                    $s->label(),
                    number_format((float) $s->price_adjustment_amount, 2, '.', ''),
                )));

            return self::FAILURE;
        }

        $productCount = Product::query()
            ->where('price_locked', false)
            ->whereHas('supplierOffers', fn ($q) => $q->where('supplier_id', $supplier->id))
            ->count();

        $this->info("Supplier: {$supplier->label()} (#{$supplier->id}, code: {$supplier->code})");
        $this->line('Price adjustment: '.number_format((float) $supplier->price_adjustment_amount, 2, '.', '').' KM');
        $this->line("Products with offers (not price_locked): {$productCount}");
        $this->newLine();

        $this->reportQueueStatus($supplier->id);

        if ($productCount > 0) {
            $this->newLine();

            if ((float) $supplier->price_adjustment_amount > 0) {
                $this->reportPriceMismatchSummary($supplier, $priceCalculator);
            } else {
                $this->info('Sample products (expected vs stored price):');
                $this->reportProductSamples($supplier, $priceCalculator, 5);
            }
        }

        if ($this->option('run')) {
            RecalculateSupplierProductPricesJob::start($supplier->id, $supplier->label());
            $this->newLine();
            $this->info('Background recalculation started (processed in chunks of '.RecalculateSupplierProductPricesJob::CHUNK_SIZE.' products).');
            $this->line('Ensure Horizon/queue worker is running on the default queue.');
        }

        if ($this->option('sync')) {
            $this->newLine();
            $this->warn('--sync runs in foreground and may hit HTTP/gateway timeouts in Plesk web terminal.');
            $this->info('Prefer: php artisan bnc:supplier-price-recalc-status startech --run');
            $this->info('Running synchronous recalculation...');
            $count = app(\App\Services\Pricing\ProductPriceRecalculator::class)
                ->forSupplierAndCategory($supplier->id);
            $this->info("Recalculated {$count} products.");
            $this->newLine();
            $this->reportPriceMismatchSummary($supplier, $priceCalculator);
        }

        if (! $this->option('run') && ! $this->option('sync')) {
            $this->newLine();
            $this->comment('Tips:');
            $this->line('  php artisan bnc:supplier-price-recalc-status startech --run   # recommended (queue, no timeout)');
            $this->line('  php artisan bnc:supplier-price-recalc-status startech --sync  # SSH only, not Plesk web');
            $this->line('  php artisan bnc:recalculate-prices --supplier='.$supplier->id);
        }

        return self::SUCCESS;
    }

    private function resolveSupplier(?string $identifier): ?Supplier
    {
        if ($identifier === null) {
            return Supplier::query()->where('code', 'startech')->first()
                ?? Supplier::query()->where('price_adjustment_amount', '>', 0)->first();
        }

        if (ctype_digit($identifier)) {
            return Supplier::query()->find((int) $identifier);
        }

        return Supplier::query()
            ->where('code', $identifier)
            ->orWhere('display_name', 'ilike', $identifier)
            ->orWhere('name', 'ilike', $identifier)
            ->first();
    }

    private function reportQueueStatus(int $supplierId): void
    {
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        $this->info('Queue status:');
        $this->line("  Pending jobs (all): {$pendingJobs}");
        $this->line("  Failed jobs (all): {$failedJobs}");

        $matchingPending = 0;
        $matchingFailed = 0;

        foreach (DB::table('jobs')->orderBy('id')->get() as $job) {
            if ($this->jobMatchesSupplier($job->payload, $supplierId)) {
                $matchingPending++;
            }
        }

        foreach (DB::table('failed_jobs')->orderByDesc('id')->limit(50)->get() as $job) {
            if ($this->jobMatchesSupplier($job->payload, $supplierId)) {
                $matchingFailed++;
                $this->warn('  Failed recalc job found: '.($job->exception ? \Illuminate\Support\Str::before($job->exception, "\n") : 'unknown'));
            }
        }

        $this->line("  Pending recalc jobs for this supplier: {$matchingPending}");
        $this->line("  Failed recalc jobs for this supplier: {$matchingFailed}");

        if ($matchingPending > 0) {
            $this->warn('  → Job is waiting in queue. Ensure Horizon/queue worker is running.');
        }

        if ($matchingPending === 0 && $matchingFailed === 0 && $pendingJobs > 0) {
            $this->comment('  → Other jobs are pending; recalc job may have already run or not been dispatched.');
        }
    }

    private function jobMatchesSupplier(string $payload, int $supplierId): bool
    {
        if (! str_contains($payload, RecalculateSupplierProductPricesJob::class)) {
            return false;
        }

        return str_contains($payload, '"supplierId":'.$supplierId)
            || str_contains($payload, 's:10:\"supplierId\";i:'.$supplierId);
    }

    private function reportPriceMismatchSummary(Supplier $supplier, PriceCalculator $priceCalculator): void
    {
        $this->info('Scanning all supplier products for price mismatches...');

        $checked = 0;
        $mismatchCount = 0;
        $mismatchSamples = [];

        Product::query()
            ->where('price_locked', false)
            ->whereHas('supplierOffers', fn ($q) => $q->where('supplier_id', $supplier->id))
            ->with(['supplierOffers.supplier', 'category'])
            ->chunkById(200, function ($products) use ($priceCalculator, &$checked, &$mismatchCount, &$mismatchSamples): void {
                foreach ($products as $product) {
                    $checked++;
                    $result = $priceCalculator->calculate($product);
                    $stored = (float) ($product->regular_price ?? 0);
                    $expected = $result->regularPrice;

                    if (round($stored, 2) === round($expected, 2)) {
                        continue;
                    }

                    $mismatchCount++;

                    if (count($mismatchSamples) < 5) {
                        $mismatchSamples[] = [$product, $result, $stored, $expected];
                    }
                }
            });

        $inSync = $checked - $mismatchCount;
        $this->line("  Checked: {$checked}");
        $this->line("  In sync: {$inSync}");
        $this->line("  Mismatch: {$mismatchCount}");

        if ($mismatchCount > 0) {
            $this->warn('  → Some products still have old prices. Run with --sync to fix all.');
            $this->newLine();
            $this->info('Mismatch examples:');

            foreach ($mismatchSamples as [$product, $result, $stored, $expected]) {
                $this->line(sprintf(
                    '  [MISMATCH] #%d %s — stored: %.2f KM, expected: %.2f KM (supplier: %s, adj: %s)',
                    $product->id,
                    \Illuminate\Support\Str::limit($product->name, 40),
                    $stored,
                    $expected,
                    $result->supplierName ?? '—',
                    $result->appliedPriceAdjustment !== null
                        ? '+'.number_format($result->appliedPriceAdjustment, 2, '.', '').' KM'
                        : '—',
                ));
            }
        } else {
            $this->info('  → All checked products have correct prices.');
        }
    }

    private function reportProductSamples(Supplier $supplier, PriceCalculator $priceCalculator, int $limit): void
    {
        Product::query()
            ->where('price_locked', false)
            ->whereHas('supplierOffers', fn ($q) => $q->where('supplier_id', $supplier->id))
            ->with(['supplierOffers.supplier', 'category'])
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Product $product) use ($priceCalculator): void {
                $result = $priceCalculator->calculate($product);
                $stored = (float) ($product->regular_price ?? 0);
                $expected = $result->regularPrice;
                $match = round($stored, 2) === round($expected, 2) ? 'OK' : 'MISMATCH';

                $this->line(sprintf(
                    '  [%s] #%d %s — stored: %.2f KM, expected: %.2f KM (supplier: %s, adj: %s)',
                    $match,
                    $product->id,
                    \Illuminate\Support\Str::limit($product->name, 40),
                    $stored,
                    $expected,
                    $result->supplierName ?? '—',
                    $result->appliedPriceAdjustment !== null
                        ? '+'.number_format($result->appliedPriceAdjustment, 2, '.', '').' KM'
                        : '—',
                ));
            });
    }
}
