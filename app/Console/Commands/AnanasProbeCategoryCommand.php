<?php

namespace App\Console\Commands;

use App\Models\AnanasCategoryMapping;
use App\Models\AnanasCategoryProbe;
use App\Models\Product;
use App\Services\Ananas\AnanasCatalogWriteGuard;
use App\Services\Ananas\AnanasCategoryProbeService;
use App\Services\Ananas\AnanasSyncSettings;
use Illuminate\Console\Command;

class AnanasProbeCategoryCommand extends Command
{
    protected $signature = 'bnc:ananas-probe-category
                            {mapping : Ananas category mapping ID}
                            {--product= : Specific BNC product ID to use for probe}
                            {--wait=60 : Max seconds to poll GET /products after import}
                            {--recheck= : Re-evaluate an existing probe ID without re-importing}
                            {--dry-run : Build payload only, do not POST import}
                            {--allow-production : Allow writes when ANANAS_ENV=production}';

    protected $description = 'Empirically validate Ananas category string via controlled Stage import + GET reconciliation';

    public function handle(
        AnanasCategoryProbeService $probeService,
        AnanasSyncSettings $settings,
        AnanasCatalogWriteGuard $writeGuard,
    ): int {
        if (! $settings->hasCredentials()) {
            $this->error('Ananas credentials are not configured.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $allowProduction = (bool) $this->option('allow-production');

        if (! $dryRun && ! $writeGuard->isAllowed()) {
            $this->error('Catalog writes are disabled. Set ANANAS_ALLOW_CATALOG_WRITES=true or enable in Ananas admin settings.');

            return self::FAILURE;
        }

        $recheckId = $this->option('recheck');

        if (is_string($recheckId) && $recheckId !== '') {
            return $this->recheckProbe($probeService, (int) $recheckId, (int) $this->option('wait'));
        }

        $mappingId = (int) $this->argument('mapping');

        if ($mappingId <= 0) {
            $this->error('Invalid mapping ID. Use a numeric ID from bnc:ananas-list-category-mappings (not the literal text {mapping_id}).');
            $this->listAvailableMappings();

            return self::FAILURE;
        }

        $mapping = AnanasCategoryMapping::query()
            ->with('category')
            ->find($mappingId);

        if ($mapping === null) {
            $this->error("Category mapping #{$mappingId} not found.");
            $this->listAvailableMappings();

            return self::FAILURE;
        }

        $product = null;
        $productId = $this->option('product');

        if ($productId !== null && $productId !== '') {
            $product = Product::query()->find((int) $productId);
        }

        $this->info(sprintf(
            'Ananas category probe (%s, catalog writes %s)',
            $settings->environment(),
            $writeGuard->isAllowed() ? 'enabled' : 'disabled',
        ));
        $this->line('BNC category: '.($mapping->category?->name ?? $mapping->category_id));
        $this->line('productType candidate: '.$mapping->ananas_product_type);
        $this->line('category candidate: '.($mapping->ananas_category ?: '(empty)'));

        try {
            $result = $probeService->probe(
                mapping: $mapping,
                product: $product,
                allowProduction: $allowProduction,
                dryRun: $dryRun,
                waitSeconds: (int) $this->option('wait'),
            );
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'No eligible product found')) {
                $this->printProbeDiagnostics($probeService, $mapping);
            }

            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(['Field', 'Value'], [
            ['Probe ID', $result->probeId > 0 ? (string) $result->probeId : '—'],
            ['Product ID', (string) $result->productId],
            ['Status', $result->status],
            ['Progress UUID', $result->progressId ?? '—'],
            ['Observed productType', $result->observedProductType ?? '—'],
            ['Observed categories', $result->observedCategories !== [] ? implode(', ', $result->observedCategories) : '—'],
            ['Remote product ID', $result->remoteProductId ?? '—'],
        ]);

        if ($result->message !== null) {
            $this->newLine();
            $this->line($result->message);
        }

        if ($result->isPending()) {
            $this->warn('Probe pending — re-run with --recheck=<probe_id> or bnc:ananas-reconcile-products after async import completes.');
        }

        return $result->isValidated() ? self::SUCCESS : ($result->isPending() ? self::SUCCESS : self::FAILURE);
    }

    private function listAvailableMappings(): void
    {
        $mappings = AnanasCategoryMapping::query()
            ->with('category')
            ->orderBy('id')
            ->limit(20)
            ->get();

        if ($mappings->isEmpty()) {
            $this->newLine();
            $this->warn('No mappings in database yet.');
            $this->line('Create one in Admin → Ananas → Mapiranje kategorija, then run:');
            $this->line('  php artisan bnc:ananas-list-category-mappings');

            return;
        }

        $this->newLine();
        $this->line('Available mappings:');

        foreach ($mappings as $mapping) {
            if (! $mapping instanceof AnanasCategoryMapping) {
                continue;
            }

            $this->line(sprintf(
                '  #%d  %s  →  productType=%s, category=%s',
                $mapping->id,
                $mapping->category?->name ?? 'category '.$mapping->category_id,
                $mapping->ananas_product_type,
                $mapping->ananas_category ?: '(empty)',
            ));
        }

        $this->newLine();
        $this->line('Or run: php artisan bnc:ananas-list-category-mappings');
    }

    private function printProbeDiagnostics(AnanasCategoryProbeService $probeService, AnanasCategoryMapping $mapping): void
    {
        $diagnosis = $probeService->diagnoseProbeCandidates($mapping);

        $this->newLine();
        $this->warn('Probe diagnostics:');
        $this->line('  Total in scope: '.$diagnosis['total_in_scope']);
        $this->line('  Active + public: '.$diagnosis['active_public']);
        $this->line('  Eligible: '.$diagnosis['eligible']);

        if ($diagnosis['reasons'] !== []) {
            $this->line('  Blockers: '.collect($diagnosis['reasons'])
                ->map(fn (int $count, string $code): string => "{$code}={$count}")
                ->implode(', '));
        }

        if ($diagnosis['first_eligible_product_id'] !== null) {
            $productId = $diagnosis['first_eligible_product_id'];
            $this->line("  Try: php artisan bnc:ananas-probe-category {$mapping->id} --product={$productId} --dry-run");
        } else {
            $this->line('  Run: php artisan bnc:ananas-find-probe-product '.$mapping->id);
        }
    }

    private function recheckProbe(AnanasCategoryProbeService $probeService, int $probeId, int $waitSeconds): int
    {
        $probe = AnanasCategoryProbe::query()->find($probeId);

        if ($probe === null) {
            $this->error("Probe {$probeId} not found.");

            return self::FAILURE;
        }

        try {
            $result = $probeService->recheckProbe($probe, $waitSeconds > 0 ? $waitSeconds : null);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Recheck probe {$probeId}: {$result->status}");
        $this->line($result->message ?? '');

        return $result->isValidated() ? self::SUCCESS : self::FAILURE;
    }
}
