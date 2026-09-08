<?php

namespace App\Console\Commands;

use App\Services\Ananas\AnanasProbeProductFinder;
use Illuminate\Console\Command;

class AnanasFindEligibleProductsCommand extends Command
{
    protected $signature = 'bnc:ananas-find-eligible-products
                            {--limit=10 : Number of eligible products to list}
                            {--scan= : Max products to scan (default: entire catalog)}';

    protected $description = 'List eligible Ananas export products anywhere in catalog (for category probe fallback)';

    public function handle(AnanasProbeProductFinder $finder): int
    {
        $scanOption = $this->option('scan');
        $scanLimit = is_string($scanOption) && $scanOption !== '' ? (int) $scanOption : null;

        $items = $finder->listEligibleGlobally(
            limit: (int) $this->option('limit'),
            scanLimit: $scanLimit,
        );

        if ($items === []) {
            $this->warn('No eligible products found in entire catalog.');
            $this->line('This is a local data check — Stage API availability (e.g. after 17h) does NOT affect this command.');
            $this->line('Run full blocker report: php artisan bnc:ananas-catalog-eligibility-report');
            $this->line('Verify Stage API separately: php artisan bnc:ananas-test-connection');

            return self::FAILURE;
        }

        $this->info('Eligible products for Ananas probe/import ('.count($items).' found)');
        $this->newLine();
        $this->table(
            ['Product ID', 'Category ID', 'EAN', 'Name'],
            collect($items)->map(fn (array $row): array => [
                (string) $row['product_id'],
                (string) ($row['category_id'] ?? '—'),
                (string) ($row['ean'] ?? '—'),
                mb_substr((string) $row['name'], 0, 60),
            ])->all(),
        );

        $firstId = $items[0]['product_id'];
        $this->newLine();
        $this->line('Category probe only validates Ananas productType/category strings — product BNC category can differ.');
        $this->line("Example: php artisan bnc:ananas-probe-category 1 --product={$firstId} --dry-run");
        $this->line('Or: php artisan bnc:ananas-probe-category 1 --any-eligible --dry-run');

        return self::SUCCESS;
    }
}
