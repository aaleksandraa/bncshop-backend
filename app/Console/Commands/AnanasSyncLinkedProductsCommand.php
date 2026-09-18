<?php

namespace App\Console\Commands;

use App\Services\Ananas\AnanasCatalogWriteGuard;
use App\Services\Ananas\AnanasLinkedProductSyncService;
use App\Services\Ananas\AnanasSyncSettings;
use Illuminate\Console\Command;

class AnanasSyncLinkedProductsCommand extends Command
{
    protected $signature = 'bnc:ananas-sync-linked
                            {--limit=25 : Max linked mappings to bulk-update}
                            {--dry-run : Build update payloads without PUT}
                            {--confirm : Required for live PUT bulk update}
                            {--allow-production : Allow writes when ANANAS_ENV=production}';

    protected $description = 'PUT bulk stock/price/VAT for locally LINKED Ananas products';

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
            $this->error('Refusing bulk update without --confirm. Use --dry-run first.');

            return self::FAILURE;
        }

        if (! $dryRun && ! $writeGuard->isAllowed()) {
            $this->error('Catalog writes are disabled.');

            return self::FAILURE;
        }

        $result = $syncService->syncLinkedStockAndPrice(
            limit: (int) $this->option('limit'),
            dryRun: $dryRun,
            allowProduction: $allowProduction,
        );

        $this->info(sprintf(
            'Linked sync (%s): updated=%d skipped=%d',
            $dryRun ? 'dry-run' : 'live',
            $result['updated'],
            $result['skipped'],
        ));

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
