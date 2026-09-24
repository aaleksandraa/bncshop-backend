<?php

namespace App\Console\Commands;

use App\Services\Ananas\AnanasCatalogWriteGuard;
use App\Services\Ananas\AnanasDiscountPolicy;
use App\Services\Ananas\AnanasDiscountService;
use App\Services\Ananas\AnanasSyncSettings;
use Illuminate\Console\Command;

class AnanasScheduleDiscountCommand extends Command
{
    protected $signature = 'bnc:ananas-schedule-discount
                            {--inventory= : Comma-separated merchant inventory ids (default: all LINKED READY_FOR_PUBLISH/PUBLISHED)}
                            {--type=SALE : SALE | SEASONAL_SALE | CLEARANCE_SALE}
                            {--percent=10 : Percent off BNC regularPrice (min 5; ignored if --price or BNC sale applies)}
                            {--days=7 : Inclusive duration for SALE/SEASONAL (SALE max 31)}
                            {--price= : Absolute discountPrice (same numeric as import basePrice)}
                            {--no-bnc-sale : Do not use BNC displayPrice even if the product is on sale}
                            {--limit=25 : Max inventories when --inventory is empty}
                            {--dry-run : Build payloads without POST}
                            {--confirm : Required for live POST /discounts}
                            {--allow-production : Allow writes when ANANAS_ENV=production}';

    protected $description = 'Schedule Ananas payment discounts (akcija) for listed merchant inventory ids';

    public function handle(
        AnanasDiscountService $discountService,
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
            $this->error('Refusing discount POST without --confirm. Use --dry-run first.');

            return self::FAILURE;
        }

        if (! $dryRun && ! $writeGuard->isAllowed()) {
            $this->error('Catalog writes are disabled.');

            return self::FAILURE;
        }

        $inventory = $discountService->normalizeInventoryIds(
            array_map('intval', explode(',', (string) $this->option('inventory'))),
        );

        if ($inventory === []) {
            $inventory = $discountService->actionableInventoryIds(limit: (int) $this->option('limit'));
        }

        if ($inventory === []) {
            $this->warn('No LINKED READY_FOR_PUBLISH/PUBLISHED inventories. Reconcile the 2 listed SKUs first.');

            return self::FAILURE;
        }

        $priceOption = $this->option('price');
        $absolute = is_string($priceOption) && trim($priceOption) !== '' ? (float) $priceOption : null;

        $result = $discountService->schedule(
            inventoryIds: $inventory,
            type: (string) $this->option('type'),
            percentOff: (int) $this->option('percent'),
            days: (int) $this->option('days'),
            absolutePrice: $absolute,
            useBncSale: ! $this->option('no-bnc-sale'),
            dryRun: $dryRun,
            allowProduction: $allowProduction,
        );

        $this->info(sprintf(
            'Ananas discount %s (%s)',
            $dryRun ? 'dry-run' : 'live',
            $settings->environment(),
        ));
        $this->line('Currency field: '.AnanasDiscountPolicy::CURRENCY_RSD.' (API enum; numeric price matches import basePrice).');
        $this->line('Scheduled: '.$result['scheduled']);
        $this->line('Failed: '.$result['failed']);

        if ($result['results'] !== []) {
            $this->newLine();
            $this->table(
                ['Inv ID', 'BNC', 'EAN', 'Remote', 'Regular', 'Discount', 'Type', 'From', 'To', 'Result'],
                array_map(static function (array $row): array {
                    $payload = $row['payload'] ?? [];

                    return [
                        (string) $row['merchant_inventory_id'],
                        (string) $row['product_id'],
                        (string) ($row['ean'] ?: '—'),
                        (string) ($row['remote_status'] ?: '—'),
                        number_format((float) $row['regular_price'], 2, '.', ''),
                        number_format((float) $row['discount_price'], 2, '.', ''),
                        (string) ($payload['discountType'] ?? ''),
                        (string) ($payload['dateFrom'] ?? ''),
                        (string) ($payload['dateTo'] ?? '—'),
                        isset($row['discount_id'])
                            ? (($row['success'] ?? false) ? (string) $row['discount_id'] : (string) ($row['error'] ?? 'fail'))
                            : 'preview',
                    ];
                }, $result['results']),
            );
        }

        foreach ($result['skipped'] as $skip) {
            $this->warn($skip);
        }

        if (! $dryRun && $result['scheduled'] > 0) {
            $this->newLine();
            $this->comment('List remote akcije:');
            $this->comment('  php artisan bnc:ananas-list-discounts');
        }

        return $result['failed'] === 0 && ($result['scheduled'] > 0 || $dryRun) ? self::SUCCESS : self::FAILURE;
    }
}
