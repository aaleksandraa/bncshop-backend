<?php

namespace App\Console\Commands;

use App\Models\AnanasCategoryMapping;
use App\Models\Product;
use App\Services\Ananas\AnanasEligibilityPolicy;
use App\Services\Ananas\AnanasExportScope;
use App\Services\Ananas\AnanasMasterEanCatalogService;
use App\Services\Ananas\AnanasMasterEanSearchResult;
use App\Services\Ananas\AnanasSyncSettings;
use App\Support\CategoryAdminSearch;
use Illuminate\Console\Command;

class AnanasFindMasterEanProductCommand extends Command
{
    protected $signature = 'bnc:ananas-find-master-ean-product
                            {--scan=2000 : Max active public rows to scan (ignored with --all)}
                            {--all : Scan entire catalog (can take several minutes)}
                            {--limit=20 : How many master-EAN matches to list}
                            {--contains= : Filter product name (case-insensitive substring, e.g. laptop)}
                            {--ean= : Check a specific barcode: master catalog + local eligible product}
                            {--skip-seeds : Do not try ANANAS_PROBE_SEED_EANS before scanning}';

    protected $description = 'List eligible BNC products whose EAN already exists in Ananas master catalog (best for category probes)';

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

        $limit = max(1, (int) $this->option('limit'));
        $containsOption = $this->option('contains');
        $nameContains = is_string($containsOption) && trim($containsOption) !== ''
            ? trim($containsOption)
            : null;

        $skipSeeds = (bool) $this->option('skip-seeds') || $limit > 1 || $nameContains !== null;

        if (! $skipSeeds) {
            $this->info('Trying configured seed EANs (ANANAS_PROBE_SEED_EANS)…');
            $result = $catalog->searchFromSeedEans();

            if ($result->found()) {
                $this->line('Match found via seed EAN list.');

                return $this->printProduct($result);
            }
        }

        $maxScan = (bool) $this->option('all') ? null : max(100, (int) $this->option('scan'));

        if ($maxScan === null) {
            $this->info(sprintf(
                'Scanning all active public products for up to %d master-catalog EAN match(es)%s…',
                $limit,
                $nameContains !== null ? ' matching "'.$nameContains.'"' : '',
            ));
        } else {
            $this->info(sprintf(
                'Scanning up to %d active public products for up to %d master-catalog EAN match(es)%s…',
                $maxScan,
                $limit,
                $nameContains !== null ? ' matching "'.$nameContains.'"' : '',
            ));
        }

        $result = $catalog->searchEligibleWithMasterEan($maxScan, $limit, $nameContains);

        if (! $result->found()) {
            $this->printFailureHints($result, $catalog, $nameContains);

            return self::FAILURE;
        }

        $products = $result->products !== [] ? $result->products : array_filter([$result->product]);

        if (count($products) === 1) {
            return $this->printProduct($result);
        }

        return $this->printProductList($products, $result);
    }

    /**
     * @param  list<Product>  $products
     */
    private function printProductList(array $products, AnanasMasterEanSearchResult $result): int
    {
        $rows = [];

        foreach ($products as $product) {
            if (! $product instanceof Product) {
                continue;
            }

            $categoryLabel = '—';

            if ($product->category !== null) {
                $categoryLabel = CategoryAdminSearch::formatOptionLabel($product->category);
            } elseif ($product->category_id !== null) {
                $categoryLabel = '#'.$product->category_id;
            }

            $rows[] = [
                (string) $product->id,
                mb_substr((string) $product->name, 0, 55),
                trim((string) $product->barcode),
                $product->category_id !== null ? (string) $product->category_id : '—',
                mb_substr($categoryLabel, 0, 70),
            ];
        }

        $this->info('Master-catalog EAN products ('.count($rows).' listed)');
        $this->table(['ID', 'Name', 'EAN', 'Cat ID', 'BNC category'], $rows);

        $this->newLine();
        $this->line(sprintf(
            'Search stats: scanned=%d eligible=%d eans_checked=%d master_hits=%d',
            $result->productsScanned,
            $result->eligibleCandidates,
            $result->eansChecked,
            $result->masterCatalogHits,
        ));

        $first = $products[0] ?? null;

        $this->newLine();
        $this->line('Pick a product whose BNC category matches the Ananas string you are validating (do not use a TV stand to probe Laptopi).');
        $this->line('Filter later: php artisan bnc:ananas-find-master-ean-product --all --limit=20 --contains=laptop');

        if ($first instanceof Product) {
            $this->line('Example probe:');
            $this->line('  php artisan bnc:ananas-list-category-mappings');
            $this->line('  php artisan bnc:ananas-probe-category 1 --product='.$first->id.' --master-ean-only --dry-run');
        }

        return self::SUCCESS;
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
            ->with(['images', 'manufacturer', 'attributeValues.attributeDefinition', 'category'])
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

        return $this->printProduct(new AnanasMasterEanSearchResult(product: $product, products: [$product]));
    }

    private function printProduct(AnanasMasterEanSearchResult $result): int
    {
        $product = $result->product;

        if ($product === null) {
            return self::FAILURE;
        }

        $ean = trim((string) $product->barcode);
        $categoryLabel = $product->category !== null
            ? CategoryAdminSearch::formatOptionLabel($product->category)
            : ($product->category_id !== null ? '#'.$product->category_id : '—');

        $this->table(['Field', 'Value'], [
            ['Product ID', (string) $product->id],
            ['Name', (string) $product->name],
            ['EAN', $ean],
            ['Category ID', $product->category_id !== null ? (string) $product->category_id : '—'],
            ['BNC category', $categoryLabel],
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
        $this->line('Category probe (master EAN → GET /products should appear quickly):');

        $mapping = app(AnanasExportScope::class)->resolveCategoryMapping($product);

        if ($mapping === null && $product->category_id !== null) {
            $mapping = AnanasCategoryMapping::query()
                ->where('category_id', $product->category_id)
                ->orderBy('id')
                ->first();
        }

        $mappingId = $mapping instanceof AnanasCategoryMapping ? (int) $mapping->id : null;

        if ($mappingId !== null) {
            $this->line(sprintf(
                '  php artisan bnc:ananas-probe-category %d --product=%d --master-ean-only --wait=90',
                $mappingId,
                $product->id,
            ));

            if ($mapping instanceof AnanasCategoryMapping) {
                $this->line(sprintf(
                    '  (mapping: productType=%s, category=%s)',
                    $mapping->ananas_product_type,
                    $mapping->ananas_category ?: '(empty)',
                ));
            }
        } else {
            $this->line('  php artisan bnc:ananas-list-category-mappings');
            $this->line('  php artisan bnc:ananas-probe-category <mapping_id> --product='.$product->id.' --master-ean-only --wait=90');
            $this->comment('  Tip: for validating a specific Ananas category string, product BNC category should match that string (do not probe Laptopi with a TV stand).');
        }

        $this->line('  Dry-run first: add --dry-run');
        $this->line('  List more: php artisan bnc:ananas-find-master-ean-product --all --limit=20 --contains=laptop');

        return self::SUCCESS;
    }

    private function printFailureHints(
        AnanasMasterEanSearchResult $result,
        AnanasMasterEanCatalogService $catalog,
        ?string $nameContains,
    ): void {
        $this->warn('No eligible product with master-catalog EAN found.');

        $this->line(sprintf(
            'Stats: scanned=%d eligible=%d distinct_eans_checked=%d master_catalog_hits=%d',
            $result->productsScanned,
            $result->eligibleCandidates,
            $result->eansChecked,
            $result->masterCatalogHits,
        ));

        if ($nameContains !== null) {
            $this->line('Name filter was --contains='.$nameContains.' — retry without it, or try another keyword.');
        }

        if ($result->masterCatalogHits > 0) {
            $this->line('Some EANs exist on Ananas but matching products failed eligibility — try --ean=<barcode> after bnc:ananas-check-ean.');
        }

        $seeds = $catalog->configuredSeedEans();

        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1) Full scan: php artisan bnc:ananas-find-master-ean-product --all --limit=20');
        $this->line('  2) Name filter: php artisan bnc:ananas-find-master-ean-product --all --contains=laptop');
        $this->line('  3) Known Ananas EAN: php artisan bnc:ananas-find-master-ean-product --ean=9788644105886');
        $this->line('  4) Set ANANAS_PROBE_SEED_EANS=comma,separated,eans'.($seeds === [] ? ' (not configured yet)' : ''));
    }
}
