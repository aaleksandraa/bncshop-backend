<?php

namespace App\Console\Commands;

use App\Services\Ananas\AnanasCatalogWriteGuard;
use App\Services\Ananas\AnanasLinkedProductSyncService;
use App\Services\Ananas\AnanasSyncSettings;
use Illuminate\Console\Command;

class AnanasPublishProductsCommand extends Command
{
    protected $signature = 'bnc:ananas-publish
                            {--limit=25 : Max READY_FOR_PUBLISH inventory rows}
                            {--inventory= : Comma-separated merchant inventory ids (e.g. 2566378,2566379)}
                            {--dry-run : List candidates without POST publish}
                            {--confirm : Required for live publish job}
                            {--allow-production : Allow writes when ANANAS_ENV=production}';

    protected $description = 'POST /product/publish for LINKED products in READY_FOR_PUBLISH (merchant inventory ids)';

    public function handle(
        AnanasLinkedProductSyncService $syncService,
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
            $this->error('Refusing publish without --confirm. Use --dry-run first.');

            return self::FAILURE;
        }

        if (! $dryRun && ! $writeGuard->isAllowed()) {
            $this->error('Catalog writes are disabled.');

            return self::FAILURE;
        }

        $inventory = $this->parseInventoryOption();

        if (trim((string) $this->option('inventory')) !== '' && $inventory === []) {
            $this->error('Invalid --inventory. Use numeric merchant inventory ids, e.g. 2567071,2567072');

            return self::FAILURE;
        }

        $result = $syncService->publishReadyLinked(
            limit: (int) $this->option('limit'),
            dryRun: $dryRun,
            allowProduction: $allowProduction,
            inventoryIds: $inventory,
        );

        $this->info(sprintf(
            'Publish (%s): items=%d progress=%s',
            $dryRun ? 'dry-run' : 'live',
            $result['published'],
            $result['progress_id'] ?? '—',
        ));

        if ($result['inventory_ids'] !== []) {
            $this->line('Merchant inventory IDs: '.implode(', ', $result['inventory_ids']));
        }

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        if (! $dryRun && $result['published'] > 0) {
            $this->newLine();
            $this->comment('Publish is asynchronous. Ananas emails when done. Then lookup and PUT regular prices if GET basePrice is 0:');
            $this->comment('  php artisan bnc:ananas-lookup-product');
            $this->comment('  php artisan bnc:ananas-sync-linked --force --vat=17 --dry-run');
            $this->comment('Akcija (discounts API) nije dio importa — samo ako se posebno zatraži.');
        }

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<int>
     */
    private function parseInventoryOption(): array
    {
        $raw = trim((string) $this->option('inventory'));

        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('intval', explode(',', $raw)), fn (int $id): bool => $id > 0));
    }
}
