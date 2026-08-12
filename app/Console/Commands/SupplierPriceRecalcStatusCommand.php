<?php

namespace App\Console\Commands;

use App\Jobs\RecalculateSupplierProductPricesJob;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Pricing\PriceCalculator;
use App\Services\Pricing\SupplierOfferSelector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class SupplierPriceRecalcStatusCommand extends Command
{
    protected $signature = 'bnc:supplier-price-recalc-status
                            {supplier? : Supplier ID or code (e.g. startech)}
                            {--run : Dispatch background recalculation job now}
                            {--sync : Recalculate immediately in this process (no queue)}
                            {--full : Scan all products for price mismatches (slow)}
                            {--product= : Debug a single product slug}
                            {--fix : With --product, persist recalculated prices immediately}
                            {--fix-all : Recalculate and persist all supplier products now (SSH/nohup)}';

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

        if ($productSlug = $this->option('product')) {
            if ($this->option('fix')) {
                $this->fixProduct($productSlug, $priceCalculator);

                return self::SUCCESS;
            }

            $this->debugProduct($productSlug, $priceCalculator);

            return self::SUCCESS;
        }

        if ($this->option('fix-all')) {
            $this->info('Recalculating all supplier products synchronously...');
            $this->line('Use SSH with nohup if this takes longer than a few minutes.');
            $count = app(\App\Services\Pricing\ProductPriceRecalculator::class)
                ->forSupplierAndCategory($supplier->id);
            $this->info("Recalculated {$count} products.");
            $this->newLine();
            $this->reportPriceMismatchSummary($supplier, $priceCalculator);

            return self::SUCCESS;
        }

        if ($this->option('run')) {
            $pendingBefore = $this->defaultQueueSize();
            $chunks = RecalculateSupplierProductPricesJob::start($supplier->id, $supplier->label());
            $pendingAfter = $this->defaultQueueSize();

            $this->info('Background recalculation queued.');
            $this->line('  Chunks dispatched: '.$chunks.' × '.RecalculateSupplierProductPricesJob::CHUNK_SIZE.' products (independent jobs)');
            $this->line('  Default queue size before: '.$pendingBefore);
            $this->line('  Default queue size after:  '.$pendingAfter);
            $this->newLine();
            $this->reportQueueStatus($supplier->id);
            $this->newLine();
            $this->comment('Wait until "Pending jobs on default queue: 0", then verify:');
            $this->line('  php artisan bnc:supplier-price-recalc-status startech --full');

            return self::SUCCESS;
        }

        $this->reportQueueStatus($supplier->id);

        if ($productCount > 0) {
            $this->newLine();

            if ((float) $supplier->price_adjustment_amount > 0 && $this->option('full')) {
                $pending = $this->defaultQueueSize();
                if ($pending > 0) {
                    $this->warn("Recalculation still in progress ({$pending} jobs on default queue).");
                    $this->line('Wait until pending queue is 0, then run --full again.');
                } else {
                    $this->reportPriceMismatchSummary($supplier, $priceCalculator);
                }
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
            $this->line('  php artisan bnc:supplier-price-recalc-status startech --fix-all # SSH, all at once');
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

        $offerSelector = app(SupplierOfferSelector::class);
        $adjustment = (float) $supplier->price_adjustment_amount;

        $checked = 0;
        $inSync = 0;
        $regularMismatch = 0;
        $displayOnlyMismatch = 0;
        $startechPricing = 0;
        $otherSupplierPricing = 0;
        $mismatchSamples = [];

        Product::query()
            ->where('price_locked', false)
            ->whereHas('supplierOffers', fn ($q) => $q->where('supplier_id', $supplier->id))
            ->with(['supplierOffers.supplier', 'category'])
            ->chunkById(200, function ($products) use (
                $priceCalculator,
                $offerSelector,
                $supplier,
                $adjustment,
                &$checked,
                &$inSync,
                &$regularMismatch,
                &$displayOnlyMismatch,
                &$startechPricing,
                &$otherSupplierPricing,
                &$mismatchSamples,
            ): void {
                foreach ($products as $product) {
                    $checked++;
                    $result = $priceCalculator->calculate($product);
                    $selectedOffer = $offerSelector->select($product);
                    $storedRegular = (float) ($product->regular_price ?? 0);
                    $storedDisplay = (float) ($product->display_price ?? 0);
                    $expectedRegular = $result->regularPrice;
                    $expectedDisplay = $result->displayPrice;

                    if ($selectedOffer?->supplier_id === $supplier->id) {
                        $startechPricing++;
                    } else {
                        $otherSupplierPricing++;
                    }

                    $regularOk = round($storedRegular, 2) === round($expectedRegular, 2);
                    $displayOk = round($storedDisplay, 2) === round($expectedDisplay, 2);

                    if ($regularOk && $displayOk) {
                        $inSync++;

                        continue;
                    }

                    if ($regularOk && ! $displayOk) {
                        $displayOnlyMismatch++;
                    } else {
                        $regularMismatch++;
                    }

                    if (count($mismatchSamples) < 8) {
                        $kind = $regularOk ? 'display-only' : 'regular';
                        $mismatchSamples[] = [$kind, $product, $result, $storedRegular, $storedDisplay, $expectedRegular, $expectedDisplay, $selectedOffer];
                    }
                }
            });

        $totalMismatch = $regularMismatch + $displayOnlyMismatch;

        $this->line("  Checked: {$checked}");
        $this->line("  In sync: {$inSync}");
        $this->line("  Mismatch: {$totalMismatch}");
        $this->line("    regular price wrong: {$regularMismatch}");
        $this->line("    display only (fake akcija): {$displayOnlyMismatch}");

        if ($adjustment > 0) {
            $this->newLine();
            $this->line('  Pricing source for products with Startech offer:');
            $this->line("    Startech selected (+{$adjustment} KM applies): {$startechPricing}");
            $this->line("    Other supplier selected (no +{$adjustment} KM): {$otherSupplierPricing}");
        }

        if ($totalMismatch > 0) {
            $this->newLine();
            $this->warn('  → Run --run to queue recalculation, or --fix-all via SSH for immediate fix.');
            $this->newLine();
            $this->info('Mismatch examples:');

            foreach ($mismatchSamples as [$kind, $product, $result, $storedRegular, $storedDisplay, $expectedRegular, $expectedDisplay, $selectedOffer]) {
                $pricingNote = $selectedOffer?->supplier_id === $supplier->id
                    ? 'Startech pricing'
                    : 'other supplier: '.($selectedOffer?->supplier?->display_name ?? $selectedOffer?->supplier?->name ?? '—');

                $this->line(sprintf(
                    '  [%s/%s] #%d %s — regular: %.2f→%.2f KM, display: %.2f→%.2f KM (%s)',
                    strtoupper($kind),
                    $pricingNote,
                    $product->id,
                    \Illuminate\Support\Str::limit($product->name, 35),
                    $storedRegular,
                    $expectedRegular,
                    $storedDisplay,
                    $expectedDisplay,
                    $result->supplierName ?? '—',
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

    private function debugProduct(string $slug, PriceCalculator $priceCalculator): void
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->with(['supplierOffers.supplier', 'category', 'manufacturer'])
            ->first();

        if (! $product) {
            $this->error("Product not found: {$slug}");

            return;
        }

        $result = $priceCalculator->calculate($product);
        $discount = app(\App\Services\Pricing\DiscountEngine::class)->bestForProduct($product);

        $this->info("Product: {$product->name} (#{$product->id})");
        $this->line('  Slug: '.$product->slug);
        $this->newLine();
        $this->info('Stored in database:');
        $this->line('  regular_price: '.number_format((float) $product->regular_price, 2, '.', '').' KM');
        $this->line('  display_price: '.number_format((float) $product->display_price, 2, '.', '').' KM');
        $this->line('  api_price: '.number_format((float) ($product->api_price ?? 0), 2, '.', '').' KM');
        $this->line('  api_final_price: '.number_format((float) ($product->api_final_price ?? 0), 2, '.', '').' KM');
        $this->line('  on_sale (db): '.($product->on_sale ? 'yes' : 'no'));
        $this->newLine();
        $this->info('Calculated now:');
        $this->line('  regular_price: '.number_format($result->regularPrice, 2, '.', '').' KM');
        $this->line('  display_price: '.number_format($result->displayPrice, 2, '.', '').' KM');
        $this->line('  on_sale: '.($result->onSale ? 'yes' : 'no'));
        $this->line('  discount_source: '.$result->discountSource);
        $this->line('  supplier: '.($result->supplierName ?? '—'));
        $this->line('  adjustment: '.($result->appliedPriceAdjustment !== null
            ? '+'.number_format($result->appliedPriceAdjustment, 2, '.', '').' KM'
            : '—'));
        $this->line('  margin: '.($result->appliedMargin !== null ? $result->appliedMargin.'%' : '—'));
        $this->line('  wholesale: '.($result->wholesalePrice !== null
            ? number_format($result->wholesalePrice, 2, '.', '').' KM'
            : '—'));

        if ($discount) {
            $this->newLine();
            $this->warn('Active shop discount detected:');
            $this->line('  ID: '.$discount->id.' | type: '.$discount->type.' | '.$discount->discount_type.' '.$discount->value);
            $this->line('  badge: '.($discount->badge_text ?? '—'));
        }

        $storedRegular = (float) $product->regular_price;
        $storedDisplay = (float) $product->display_price;
        $needsPersist = round($storedRegular, 2) !== round($result->regularPrice, 2)
            || round($storedDisplay, 2) !== round($result->displayPrice, 2);

        if ($needsPersist) {
            $this->newLine();
            $this->warn('Database is out of sync with calculator — run recalculation or re-save product.');
        } elseif ($result->onSale && $result->discountSource === 'none') {
            $this->newLine();
            $this->warn('Inverted sale (display < regular) without discount — stale display_price likely.');
        } elseif (! $result->onSale) {
            $this->newLine();
            $this->info('Expected storefront: single price '.number_format($result->displayPrice, 2, '.', '').' KM');
        }
    }

    private function fixProduct(string $slug, PriceCalculator $priceCalculator): void
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->with(['supplierOffers.supplier', 'category'])
            ->first();

        if (! $product) {
            $this->error("Product not found: {$slug}");

            return;
        }

        $beforeRegular = (float) $product->regular_price;
        $beforeDisplay = (float) $product->display_price;

        $result = $priceCalculator->recalculateAndPersist($product->fresh(['supplierOffers.supplier', 'category']));

        $this->info("Fixed: {$product->name} (#{$product->id})");
        $this->line('  regular_price: '.number_format($beforeRegular, 2, '.', '').' → '.number_format($result->regularPrice, 2, '.', '').' KM');
        $this->line('  display_price: '.number_format($beforeDisplay, 2, '.', '').' → '.number_format($result->displayPrice, 2, '.', '').' KM');
        $this->line('  on_sale: '.($result->onSale ? 'yes' : 'no'));
    }
}
