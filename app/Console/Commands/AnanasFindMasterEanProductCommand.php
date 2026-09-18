<?php

namespace App\Console\Commands;

use App\Services\Ananas\AnanasMasterEanCatalogService;
use App\Services\Ananas\AnanasSyncSettings;
use Illuminate\Console\Command;

class AnanasFindMasterEanProductCommand extends Command
{
    protected $signature = 'bnc:ananas-find-master-ean-product
                            {--scan=2000 : Max products to scan when searching for a master-catalog EAN}';

    protected $description = 'Find first eligible BNC product whose EAN already exists in Ananas master catalog (best for category probes)';

    public function handle(AnanasMasterEanCatalogService $catalog, AnanasSyncSettings $settings): int
    {
        if (! $settings->hasCredentials()) {
            $this->error('Ananas credentials are not configured.');

            return self::FAILURE;
        }

        $scan = max(100, (int) $this->option('scan'));

        $this->info("Scanning up to {$scan} active public products for a master-catalog EAN…");

        $product = $catalog->findFirstEligibleProductWithMasterEan($scan);

        if ($product === null) {
            $this->warn('No eligible product with master-catalog EAN found in scan window.');
            $this->line('Increase --scan or import a known Ananas EAN product for probing.');

            return self::FAILURE;
        }

        $ean = trim((string) $product->barcode);

        $this->table(['Field', 'Value'], [
            ['Product ID', (string) $product->id],
            ['Name', (string) $product->name],
            ['EAN', $ean],
            ['Category ID', $product->category_id !== null ? (string) $product->category_id : '—'],
        ]);

        $this->newLine();
        $this->line('Example category probe (instant GET reconciliation expected):');
        $this->line('  php artisan bnc:ananas-probe-category <mapping_id> --product='.$product->id.' --master-ean-only');

        return self::SUCCESS;
    }
}
