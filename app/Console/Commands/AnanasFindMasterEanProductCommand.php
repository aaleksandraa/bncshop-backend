<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Ananas\AnanasEligibilityPolicy;
use App\Services\Ananas\AnanasMasterEanCatalogService;
use App\Services\Ananas\AnanasMasterEanSearchResult;
use App\Services\Ananas\AnanasSyncSettings;
use Illuminate\Console\Command;

class AnanasFindMasterEanProductCommand extends Command
{
    protected $signature = 'bnc:ananas-find-master-ean-product
                            {--scan=2000 : Max active public rows to scan (ignored with --all)}
                            {--all : Scan entire catalog (can take several minutes)}
                            {--ean= : Check a specific barcode: master catalog + local eligible product}
                            {--skip-seeds : Do not try ANANAS_PROBE_SEED_EANS before scanning}';

    protected $description = 'Find first eligible BNC product whose EAN already exists in Ananas master catalog (best for category probes)';

    public function handle(
        AnanasMasterEanCatalogService $catalog,
        AnanasSyncSettings $settings,
        AnanasEligibilityPolicy $eligibilityPolicy,
    ): int {
        if (! $settings->hasCredentials()) {
            $this->error('Ananas credentials are not configured.');

            return self::FAILURE;
        }

        $eanOption = $this->option('ean');

        if (is_string($eanOption) && trim($eanOption) !== '') {
            return $this->handleSpecificEan(trim($eanOption), $catalog, $eligibilityPolicy);
        }

        $result = null;

        if (! (bool) $this->option('skip-seeds')) {
            $this->info('Trying configured seed EANs (ANANAS_PROBE_SEED_EANS)…');
            $result = $catalog->searchFromSeedEans();

            if ($result->found()) {
                $this->line('Match found via seed EAN list.');

                return $this->printProduct($result);
            }
        }

        $maxScan = (bool) $this->option('all') ? null : max(100, (int) $this->option('scan'));

        if ($maxScan === null) {
            $this->info('Scanning all active public products with barcode (eligible only, batched ean/exists)…');
        } else {
            $this->info("Scanning up to {$maxScan} active public products for a master-catalog EAN…");
        }

        $result = $catalog->searchEligibleWithMasterEan($maxScan);

        if (! $result->found()) {
            $this->printFailureHints($result, $catalog);

            return self::FAILURE;
        }

        return $this->printProduct($result);
    }

    private function handleSpecificEan(
        string $ean,
        AnanasMasterEanCatalogService $catalog,
        AnanasEligibilityPolicy $eligibilityPolicy,
    ): int {
        $this->info("Checking EAN {$ean} against Ananas master catalog…");

        $inMaster = $catalog->isInMasterCatalog($ean);
        $this->line('Master catalog: '.($inMaster ? 'yes' : 'no'));

        if (! $inMaster) {
            $this->warn('This EAN is not in Ananas master catalog — category probe will need onboarding, not instant GET.');

            return self::FAILURE;
        }

        $product = Product::query()
            ->where('is_public', true)
            ->where('status', 'active')
            ->where('barcode', $ean)
            ->with(['images', 'manufacturer', 'attributeValues.attributeDefinition'])
            ->orderBy('id')
            ->first();

        if ($product === null) {
            $this->warn('EAN exists on Ananas but no active public BNC product has this barcode.');
            $this->line('Add/sync a product with this barcode or pick another master EAN from bnc:ananas-check-ean tests.');

            return self::FAILURE;
        }

        $eligibility = $eligibilityPolicy->evaluateProductData($product);

        if (! $eligibility->eligible) {
            $this->warn(sprintf(
                'Product #%d has master EAN but is not probe-eligible: %s',
                $product->id,
                $eligibility->reasonCode ?? 'NOT_ELIGIBLE',
            ));
            $this->line('Fix blockers (weight, image, EAN format, etc.) then re-run with the same --ean.');

            return self::FAILURE;
        }

        return $this->printProduct(new AnanasMasterEanSearchResult(product: $product));
    }

    private function printProduct(AnanasMasterEanSearchResult $result): int
    {
        $product = $result->product;

        if ($product === null) {
            return self::FAILURE;
        }

        $ean = trim((string) $product->barcode);

        $this->table(['Field', 'Value'], [
            ['Product ID', (string) $product->id],
            ['Name', (string) $product->name],
            ['EAN', $ean],
            ['Category ID', $product->category_id !== null ? (string) $product->category_id : '—'],
        ]);

        if ($result->productsScanned > 0 || $result->eansChecked > 0) {
            $this->newLine();
            $this->line(sprintf(
                'Search stats: scanned=%d eligible=%d eans_checked=%d master_hits=%d',
                $result->productsScanned,
                $result->eligibleCandidates,
                $result->eansChecked,
                $result->masterCatalogHits,
            ));
        }

        $this->newLine();
        $this->line('Example category probe (instant GET reconciliation expected):');
        $this->line('  php artisan bnc:ananas-probe-category <mapping_id> --product='.$product->id.' --master-ean-only');

        return self::SUCCESS;
    }

    private function printFailureHints(AnanasMasterEanSearchResult $result, AnanasMasterEanCatalogService $catalog): void
    {
        $this->warn('No eligible product with master-catalog EAN found.');

        $this->line(sprintf(
            'Stats: scanned=%d eligible=%d distinct_eans_checked=%d master_catalog_hits=%d',
            $result->productsScanned,
            $result->eligibleCandidates,
            $result->eansChecked,
            $result->masterCatalogHits,
        ));

        if ($result->masterCatalogHits > 0) {
            $this->line('Some EANs exist on Ananas but matching products failed eligibility — try --ean=<barcode> after bnc:ananas-check-ean.');
        }

        $seeds = $catalog->configuredSeedEans();

        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1) Full scan: php artisan bnc:ananas-find-master-ean-product --all');
        $this->line('  2) Known Ananas EAN: php artisan bnc:ananas-find-master-ean-product --ean=9788644105886');
        $this->line('  3) Set ANANAS_PROBE_SEED_EANS=comma,separated,eans'.($seeds === [] ? ' (not configured yet)' : ''));

        if ($result->masterCatalogHits === 0 && $result->eansChecked > 0) {
            $this->newLine();
            $this->comment(
                'Typical BNC IT barcodes are not in Ananas master catalog. For category validation you can still probe with any eligible product (awaiting onboarding), or sync one product whose EAN Ananas already knows.',
            );
        }
    }
}
