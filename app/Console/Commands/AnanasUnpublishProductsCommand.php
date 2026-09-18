<?php

namespace App\Console\Commands;

use App\Services\Ananas\AnanasCatalogWriteGuard;
use App\Services\Ananas\AnanasLinkedProductSyncService;
use App\Services\Ananas\AnanasSyncSettings;
use Illuminate\Console\Command;

class AnanasUnpublishProductsCommand extends Command
{
    protected $signature = 'bnc:ananas-unpublish
                            {--limit=25 : Max PUBLISHED inventory rows}
                            {--dry-run : Count candidates only}
                            {--confirm : Required for live unpublish job}
                            {--allow-production : Allow writes when ANANAS_ENV=production}';

    protected $description = 'Submit Ananas unpublish job for LINKED products in PUBLISHED status';

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
            $this->error('Refusing unpublish without --confirm. Use --dry-run first.');

            return self::FAILURE;
        }

        if (! $dryRun && ! $writeGuard->isAllowed()) {
            $this->error('Catalog writes are disabled.');

            return self::FAILURE;
        }

        $result = $syncService->unpublishPublished(
            limit: (int) $this->option('limit'),
            dryRun: $dryRun,
            allowProduction: $allowProduction,
        );

        $this->info(sprintf(
            'Unpublish (%s): items=%d progress=%s',
            $dryRun ? 'dry-run' : 'live',
            $result['published'],
            $result['progress_id'] ?? '—',
        ));

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
