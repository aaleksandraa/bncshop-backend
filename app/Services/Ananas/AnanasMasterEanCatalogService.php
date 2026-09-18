<?php

namespace App\Services\Ananas;

use App\Models\Product;

class AnanasMasterEanCatalogService
{
    private const BATCH_SIZE = 25;

    public function __construct(
        private readonly AnanasApiClient $apiClient,
        private readonly AnanasEligibilityPolicy $eligibilityPolicy,
    ) {}

    public function isInMasterCatalog(string $ean): bool
    {
        return $this->apiClient->eanExistsInMasterCatalog($ean);
    }

    /**
     * @return array<string, bool>
     */
    public function checkEans(array $eans): array
    {
        $unique = array_values(array_unique(array_filter(array_map(
            static fn (mixed $ean): ?string => is_string($ean) && trim($ean) !== '' ? trim($ean) : null,
            $eans,
        ))));

        if ($unique === []) {
            return [];
        }

        $result = [];

        foreach (array_chunk($unique, self::BATCH_SIZE) as $chunk) {
            foreach ($this->apiClient->checkEansExist($chunk) as $ean => $exists) {
                $result[(string) $ean] = (bool) $exists;
            }
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    public function configuredSeedEans(): array
    {
        $raw = config('bnc.ananas_probe_seed_eans', []);

        if (is_string($raw)) {
            $raw = array_filter(array_map(trim(...), explode(',', $raw)));
        }

        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $ean): ?string => is_string($ean) && trim($ean) !== '' ? trim($ean) : null,
            $raw,
        ))));
    }

    /**
     * Look up a BNC product by barcode and confirm master-catalog + export eligibility (probe rules).
     */
    public function findEligibleByBarcode(string $barcode): ?Product
    {
        $ean = trim($barcode);

        if ($ean === '') {
            return null;
        }

        if (! $this->isInMasterCatalog($ean)) {
            return null;
        }

        $product = $this->findLocalProductByBarcode($ean);

        if ($product === null) {
            return null;
        }

        if (! $this->eligibilityPolicy->evaluateProductData($product)->eligible) {
            return null;
        }

        return $product;
    }

    public function findFirstEligibleProductWithMasterEan(?int $maxProductsToScan = null): ?Product
    {
        return $this->searchEligibleWithMasterEan($maxProductsToScan)->product;
    }

    public function searchEligibleWithMasterEan(
        ?int $maxProductsToScan = null,
        int $limit = 1,
        ?string $nameContains = null,
    ): AnanasMasterEanSearchResult {
        $limit = max(1, $limit);
        $needle = $nameContains !== null ? mb_strtolower(trim($nameContains)) : '';

        $productsScanned = 0;
        $eligibleCandidates = 0;
        $eansChecked = 0;
        $masterCatalogHits = 0;
        $matches = [];

        $pendingEans = [];
        $productsByEan = [];

        $query = Product::query()
            ->where('is_public', true)
            ->where('status', 'active')
            ->whereNotNull('barcode')
            ->where('barcode', '!=', '')
            ->with([
                'images',
                'manufacturer',
                'attributeValues.attributeDefinition',
                'category' => fn ($query) => $query->withCount('products'),
            ])
            ->orderBy('id');

        $query->chunkById(200, function ($products) use (
            &$productsScanned,
            &$eligibleCandidates,
            &$eansChecked,
            &$masterCatalogHits,
            &$pendingEans,
            &$productsByEan,
            &$matches,
            $maxProductsToScan,
            $limit,
            $needle,
        ): bool {
            foreach ($products as $product) {
                if (count($matches) >= $limit) {
                    return false;
                }

                if ($maxProductsToScan !== null && $productsScanned >= $maxProductsToScan) {
                    return false;
                }

                $productsScanned++;

                if (! $product instanceof Product) {
                    continue;
                }

                if ($needle !== '' && ! str_contains(mb_strtolower((string) $product->name), $needle)) {
                    continue;
                }

                if (! $this->eligibilityPolicy->evaluateProductData($product)->eligible) {
                    continue;
                }

                $eligibleCandidates++;

                $ean = trim((string) $product->barcode);

                if ($ean === '') {
                    continue;
                }

                if (! isset($productsByEan[$ean])) {
                    $pendingEans[] = $ean;
                    $productsByEan[$ean] = $product;
                }

                if (count($pendingEans) >= self::BATCH_SIZE) {
                    $this->collectMatchesFromBatch(
                        $pendingEans,
                        $productsByEan,
                        $matches,
                        $limit,
                        $eansChecked,
                        $masterCatalogHits,
                    );

                    $pendingEans = [];
                    $productsByEan = [];
                }
            }

            return count($matches) < $limit;
        });

        if (count($matches) < $limit && $pendingEans !== []) {
            $this->collectMatchesFromBatch(
                $pendingEans,
                $productsByEan,
                $matches,
                $limit,
                $eansChecked,
                $masterCatalogHits,
            );
        }

        return new AnanasMasterEanSearchResult(
            product: $matches[0] ?? null,
            productsScanned: $productsScanned,
            eligibleCandidates: $eligibleCandidates,
            eansChecked: $eansChecked,
            masterCatalogHits: $masterCatalogHits,
            products: $matches,
        );
    }

    /**
     * Same as search but only checks configured / env seed EANs against local catalog first (fast path).
     */
    public function searchFromSeedEans(): AnanasMasterEanSearchResult
    {
        $seeds = $this->configuredSeedEans();
        $eansChecked = 0;
        $masterCatalogHits = 0;

        if ($seeds === []) {
            return new AnanasMasterEanSearchResult(product: null);
        }

        foreach (array_chunk($seeds, self::BATCH_SIZE) as $chunk) {
            $existsMap = $this->checkEans($chunk);
            $eansChecked += count($chunk);

            foreach ($existsMap as $ean => $exists) {
                if (! $exists) {
                    continue;
                }

                $masterCatalogHits++;

                $product = $this->findLocalProductByBarcode((string) $ean);

                if ($product === null) {
                    continue;
                }

                if ($this->eligibilityPolicy->evaluateProductData($product)->eligible) {
                    return new AnanasMasterEanSearchResult(
                        product: $product,
                        eansChecked: $eansChecked,
                        masterCatalogHits: $masterCatalogHits,
                    );
                }
            }
        }

        return new AnanasMasterEanSearchResult(
            product: null,
            eansChecked: $eansChecked,
            masterCatalogHits: $masterCatalogHits,
        );
    }

    private function findLocalProductByBarcode(string $ean): ?Product
    {
        $ean = trim($ean);

        if ($ean === '') {
            return null;
        }

        return Product::query()
            ->where('is_public', true)
            ->where('status', 'active')
            ->where('barcode', $ean)
            ->with(['images', 'manufacturer', 'attributeValues.attributeDefinition'])
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  list<string>  $eans
     * @param  array<string, Product>  $productsByEan
     * @param  list<Product>  $matches
     */
    private function collectMatchesFromBatch(
        array $eans,
        array $productsByEan,
        array &$matches,
        int $limit,
        int &$eansChecked,
        int &$masterCatalogHits,
    ): void {
        $unique = array_values(array_unique($eans));
        $eansChecked += count($unique);
        $existence = $this->checkEans($unique);

        foreach ($unique as $ean) {
            if (! ($existence[$ean] ?? false)) {
                continue;
            }

            $masterCatalogHits++;

            if (! isset($productsByEan[$ean])) {
                continue;
            }

            $matches[] = $productsByEan[$ean];

            if (count($matches) >= $limit) {
                return;
            }
        }
    }

    /**
     * @param  list<string>  $eans
     * @param  array<string, Product>  $productsByEan
     */
    private function firstMatchFromBatch(
        array $eans,
        array $productsByEan,
        int &$eansChecked,
        int &$masterCatalogHits,
    ): ?Product {
        $matches = [];
        $this->collectMatchesFromBatch($eans, $productsByEan, $matches, 1, $eansChecked, $masterCatalogHits);

        return $matches[0] ?? null;
    }
}
