<?php

namespace App\Console\Commands;

use App\Services\Ananas\AnanasCatalogWriteGuard;
use App\Services\Ananas\AnanasProductImportService;
use App\Services\Ananas\AnanasSyncSettings;
use Illuminate\Console\Command;

class AnanasImportProductsCommand extends Command
{
    protected $signature = 'bnc:ananas-import-products
                            {--limit=10 : Max products in one import batch}
                            {--product= : Import a single product by BNC ID}
                            {--dry-run : Build payloads without POST import}
                            {--confirm : Required for actual POST import}
                            {--allow-production : Allow writes when ANANAS_ENV=production}';

    protected $description = 'Controlled Ananas POST import for eligible products in enabled category mappings';

    public function handle(
        AnanasProductImportService $importService,
        AnanasSyncSettings $settings,
        AnanasCatalogWriteGuard $writeGuard,
    ): int {
        if (! $settings->hasCredentials()) {
            $this->error('Ananas credentials are not configured.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $confirm = (bool) $this->option('confirm');
        $allowProduction = (bool) $this->option('allow-production');

        if (! $dryRun && ! $confirm) {
            $this->error('Refusing to POST import without --confirm. Use --dry-run to preview payloads first.');

            return self::FAILURE;
        }

        if (! $dryRun && ! $writeGuard->isAllowed()) {
            $this->error('Catalog writes are disabled. Set ANANAS_ALLOW_CATALOG_WRITES=true or enable in Ananas admin settings.');

            return self::FAILURE;
        }

        $productOption = $this->option('product');
        $productId = is_string($productOption) && $productOption !== '' ? (int) $productOption : null;

        $this->info(sprintf(
            'Ananas import batch (%s, %s)',
            $settings->environment(),
            $dryRun ? 'dry-run' : 'live',
        ));

        try {
            $result = $importService->importBatch(
                limit: (int) $this->option('limit'),
                productId: $productId,
                allowProduction: $allowProduction,
                dryRun: $dryRun,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Submitted: '.$result['submitted']);
        $this->line('Skipped: '.$result['skipped']);
        $this->line('Progress UUID: '.($result['progress_id'] ?? '—'));

        if ($result['product_ids'] !== []) {
            $this->line('Product IDs: '.implode(', ', $result['product_ids']));
        }

        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        if (! $dryRun && $result['submitted'] > 0) {
            $this->newLine();
            $this->comment('Import is asynchronous. Run bnc:ananas-reconcile-products after processing completes.');
        }

        return $result['submitted'] > 0 || $dryRun ? self::SUCCESS : self::FAILURE;
    }
}
