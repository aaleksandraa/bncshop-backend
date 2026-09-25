<?php

namespace App\Console\Commands;

use App\Services\Ananas\AnanasCatalogWriteGuard;
use App\Services\Ananas\AnanasDiscountService;
use App\Services\Ananas\AnanasSyncSettings;
use Illuminate\Console\Command;

class AnanasScheduleDiscountCommand extends Command
{
    protected $signature = 'bnc:ananas-schedule-discount
                            {--inventory= : Comma-separated merchant inventory ids (default: all LINKED READY_FOR_PUBLISH/PUBLISHED)}
                            {--type=SALE : SALE | SEASONAL_SALE | CLEARANCE_SALE}
                            {--percent=10 : Percent off BNC regularPrice (min 5; ignored if --price or BNC sale applies)}
                            {--days=7 : Inclusive duration for SALE/SEASONAL (SALE max 30)}
                            {--price= : Absolute discountPrice (same numeric as import basePrice)}
                            {--currency= : BAM|EUR|RSD (default BAM; merchant inventory currency, no FX)}
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

        $inventoryRaw = trim((string) $this->option('inventory'));
        $inventory = $discountService->normalizeInventoryIds(
            array_map('intval', explode(',', $inventoryRaw)),
        );

        if ($inventoryRaw !== '' && $inventory === []) {
            $this->error('Invalid --inventory. Use numeric merchant inventory ids, e.g. 2567071,2567072');

            return self::FAILURE;
        }

        if ($inventory === []) {
            $inventory = $discountService->actionableInventoryIds(limit: (int) $this->option('limit'));
        }

        if ($inventory === []) {
            $this->warn('No LINKED READY_FOR_PUBLISH/PUBLISHED inventories. Reconcile the 2 listed SKUs first.');

            return self::FAILURE;
        }

        $priceOption = $this->option('price');
        $absolute = is_string($priceOption) && trim($priceOption) !== '' ? (float) $priceOption : null;
        $currencyOption = trim((string) $this->option('currency'));

        $result = $discountService->schedule(
            inventoryIds: $inventory,
            type: (string) $this->option('type'),
            percentOff: (int) $this->option('percent'),
            days: (int) $this->option('days'),
            absolutePrice: $absolute,
            useBncSale: ! $this->option('no-bnc-sale'),
            dryRun: $dryRun,
            allowProduction: $allowProduction,
            currency: $currencyOption !== '' ? $currencyOption : null,
        );

        $this->info(sprintf(
            'Ananas discount %s (%s)',
            $dryRun ? 'dry-run' : 'live',
            $settings->environment(),
        ));
        $shownCurrency = $result['payloads'][0]['discountPriceCurrency'] ?? $settings->discountCurrency();
        $this->line('Currency field: '.$shownCurrency.' (merchant inventory). Regular column is Ananas catalog basePrice when known.');
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
                        array_key_exists('success', $row)
                            ? (($row['success'] ?? false)
                                ? (string) ($row['discount_id'] ?: 'ok')
                                : (string) ($row['error'] ?? 'fail'))
                            : 'preview',
                    ];
                }, $result['results']),
            );
        }

        foreach ($result['skipped'] as $skip) {
            $this->warn($skip);
        }

        if (! $dryRun && $result['raw'] !== null) {
            $this->newLine();
            $this->line('Raw POST /payment/api/v1/merchant-integration/discounts response:');
            $encoded = json_encode($result['raw'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->output->writeln(
                is_string($encoded) ? $encoded : '(unencodable)',
                \Symfony\Component\Console\Output\OutputInterface::OUTPUT_RAW,
            );
        }

        $unpublishedError = false;
        foreach ($result['results'] as $row) {
            $err = strtolower((string) ($row['error'] ?? ''));
            if ($err !== '' && (str_contains($err, 'not published') || str_contains($err, 'unpublished'))) {
                $unpublishedError = true;
                break;
            }
        }

        if ($unpublishedError) {
            $this->newLine();
            $this->warn('Ananas rejected the akcija because the product is not published yet. Wait for status PUBLISHED, then retry.');
            $this->comment('  php artisan bnc:ananas-lookup-product');
        }

        if (! $dryRun && $result['scheduled'] > 0) {
            $this->newLine();
            $this->comment('List remote akcije:');
            $this->comment('  php artisan bnc:ananas-list-discounts');
        }

        return $result['failed'] === 0 && ($result['scheduled'] > 0 || $dryRun) ? self::SUCCESS : self::FAILURE;
    }
}
