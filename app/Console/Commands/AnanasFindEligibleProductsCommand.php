<?php

namespace App\Console\Commands;

use App\Services\Ananas\AnanasProbeProductFinder;
use Illuminate\Console\Command;

class AnanasFindEligibleProductsCommand extends Command
{
    protected $signature = 'bnc:ananas-find-eligible-products
                            {--limit=10 : Number of eligible products to list}
                            {--scan=5000 : Max products to scan in catalog}';

    protected $description = 'List eligible Ananas export products anywhere in catalog (for category probe fallback)';

    public function handle(AnanasProbeProductFinder $finder): int
    {
        $items = $finder->listEligibleGlobally(
            limit: (int) $this->option('limit'),
            scanLimit: (int) $this->option('scan'),
        );

        if ($items === []) {
            $this->warn('No eligible products found in scanned catalog sample.');
            $this->line('Products need valid 8/13-digit EAN, image URL, package weight attribute, and positive price.');

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
