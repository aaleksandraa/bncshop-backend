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
            $this->info('Sample products (expected vs stored price):');

            Product::query()
                ->where('price_locked', false)
                ->whereHas('supplierOffers', fn ($q) => $q->where('supplier_id', $supplier->id))
                ->with(['supplierOffers.supplier', 'category'])
                ->orderBy('id')
                ->limit(5)
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

        if ($this->option('run')) {
            RecalculateSupplierProductPricesJob::dispatch($supplier->id, $supplier->label());
            $this->newLine();
            $this->info('Background recalculation job dispatched.');
        }

        if ($this->option('sync')) {
            $this->newLine();
            $this->info('Running synchronous recalculation...');
            $count = app(\App\Services\Pricing\ProductPriceRecalculator::class)
                ->forSupplierAndCategory($supplier->id);
            $this->info("Recalculated {$count} products.");
        }

        if (! $this->option('run') && ! $this->option('sync')) {
            $this->newLine();
            $this->comment('Tips:');
            $this->line('  php artisan bnc:supplier-price-recalc-status startech --run   # queue job');
            $this->line('  php artisan bnc:supplier-price-recalc-status startech --sync  # run now (CLI)');
            $this->line('  php artisan bnc:recalculate-prices --supplier='.$supplier->id);
            $this->line('  php artisan queue:work --queue=default   # process pending jobs');
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
}
