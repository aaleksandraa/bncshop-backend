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
                            {--inventory= : Comma-separated merchant inventory ids}
                            {--force : PUT even when local price/stock hashes match}
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

        $inventoryRaw = trim((string) $this->option('inventory'));
        $inventory = array_values(array_unique(array_filter(
            array_map('intval', explode(',', $inventoryRaw)),
            static fn (int $id): bool => $id > 0,
        )));

        if ($inventoryRaw !== '' && $inventory === []) {
            $this->error('Invalid --inventory. Use numeric merchant inventory ids, e.g. 2567071,2567072');

            return self::FAILURE;
        }

        $result = $syncService->syncLinkedStockAndPrice(
            limit: (int) $this->option('limit'),
            dryRun: $dryRun,
            allowProduction: $allowProduction,
            inventoryIds: $inventory,
            force: (bool) $this->option('force'),
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

        if (($result['items'] ?? []) !== []) {
            $this->newLine();
            $this->table(
                ['Inv ID', 'BNC', 'EAN', 'basePrice', 'stock'],
                array_map(static function (array $row): array {
                    return [
                        (string) ($row['id'] ?? '—'),
                        (string) ($row['product_id'] ?? '—'),
                        (string) ($row['ean'] ?? '—'),
                        isset($row['basePrice']) ? number_format((float) $row['basePrice'], 2, '.', '') : '—',
                        (string) ($row['stockLevel'] ?? '—'),
                    ];
                }, $result['items']),
            );
            $this->comment('Ananas may apply a new basePrice after midnight (00:01). Lookup until GET basePrice > 0, then schedule akcija.');
        }

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
