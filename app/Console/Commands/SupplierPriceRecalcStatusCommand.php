<?php

namespace App\Console\Commands;

use App\Jobs\RecalculateSupplierProductPricesJob;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class SupplierPriceRecalcStatusCommand extends Command
{
    protected $signature = 'bnc:supplier-price-recalc-status
                            {supplier? : Supplier ID or code (e.g. startech)}
                            {--run : Dispatch background recalculation job now}
                            {--sync : Recalculate immediately in this process (no queue)}
                            {--full : Scan all products for price mismatches (slow)}';

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
        $estimatedChunks = (int) ceil($productCount / RecalculateSupplierProductPricesJob::CHUNK_SIZE);
        $this->newLine();

        if ($this->option('run')) {
            $pendingBefore = $this->defaultQueueSize();
            RecalculateSupplierProductPricesJob::start($supplier->id, $supplier->label());
            $pendingAfter = $this->defaultQueueSize();

            $this->info('Background recalculation queued.');
            $this->line('  Chunks: ~'.$estimatedChunks.' × '.RecalculateSupplierProductPricesJob::CHUNK_SIZE.' products');
            $this->line('  Default queue size before: '.$pendingBefore);
            $this->line('  Default queue size after:  '.$pendingAfter);
            $this->newLine();
            $this->reportQueueStatus($supplier->id);
            $this->newLine();
            $this->comment('Wait 5–10 minutes, then verify:');
            $this->line('  php artisan bnc:supplier-price-recalc-status startech --full');

            return self::SUCCESS;
        }

        $this->reportQueueStatus($supplier->id);

        if ($productCount > 0) {
            $this->newLine();

            if ($this->option('full') && (float) $supplier->price_adjustment_amount > 0) {
                $this->reportPriceMismatchSummary($supplier, $priceCalculator);
            } elseif ((float) $supplier->price_adjustment_amount > 0) {
                $this->info('Sample products (use --full to scan entire catalog):');
                $this->reportProductSamples($supplier, $priceCalculator, 5);
            } else {
                $this->info('Sample products (expected vs stored price):');
                $this->reportProductSamples($supplier, $priceCalculator, 5);
            }
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
            $this->line('  php artisan bnc:supplier-price-recalc-status startech --run   # queue recalculation');
            $this->line('  php artisan bnc:supplier-price-recalc-status startech --full  # verify all prices');
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
        $connection = (string) config('queue.default');
        $pendingJobs = $this->defaultQueueSize();
        $failedJobs = DB::table('failed_jobs')->count();

        $this->info('Queue status:');
        $this->line("  Connection: {$connection}");
        $this->line("  Pending jobs on default queue: {$pendingJobs}");
        $this->line("  Failed jobs (all, database log): {$failedJobs}");

        if ($connection === 'redis') {
            $this->comment('  → Jobs live in Redis. "jobs" SQL table is not used.');
            $this->line('  → Check Horizon: /horizon or supervisorctl status bncshop-horizon');
        }

        $matchingFailed = 0;

        foreach (DB::table('failed_jobs')->orderByDesc('id')->limit(100)->get() as $job) {
            if ($this->jobMatchesSupplier($job->payload, $supplierId)) {
                $matchingFailed++;
                $this->warn('  Failed recalc job: '.($job->exception ? \Illuminate\Support\Str::before($job->exception, "\n") : 'unknown'));
            }
        }

        if ($matchingFailed > 0) {
            $this->warn("  Failed recalc jobs for this supplier: {$matchingFailed}");
        }

        if ($pendingJobs > 0 && $connection === 'redis') {
            $this->warn('  → Recalculation may still be in progress. Wait and run --full to verify.');
        }

        if ($pendingJobs === 0 && $connection === 'redis') {
            $this->comment('  → No jobs waiting on default queue (idle or Horizon not processing).');
        }
    }

    private function defaultQueueSize(): int
    {
        try {
            return Queue::connection(config('queue.default'))->size('default');
        } catch (\Throwable) {
            return DB::table('jobs')->count();
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
            $this->warn('  → Some products still have old prices. Run with --run to queue recalculation.');
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
