<?php

namespace App\Services\Olx;

use App\Models\Product;

class OlxChangeDetector
{
    public function __construct(
        private readonly OlxExportScope $scope,
        private readonly OlxListingMapper $listingMapper,
    ) {}

    /**
     * Scan eligible products and return product IDs grouped by action.
     * Stores IDs only (not full models) to keep memory bounded on large catalogs.
     *
     * @return array{
     *     create: list<int>,
     *     update: list<int>,
     *     hide: list<int>,
     *     unhide: list<int>,
     *     unchanged: int,
     *     scanned: int
     * }
     */
    public function detect(bool $forceAll = false): array
    {
        /** @var list<int> $create */
        $create = [];
        /** @var list<int> $update */
        $update = [];
        /** @var list<int> $hide */
        $hide = [];
        /** @var list<int> $unhide */
        $unhide = [];
        $unchanged = 0;
        $scanned = 0;

        $eligibleIds = $this->scope->scopedCategoryIds();

        if ($eligibleIds === []) {
            return compact('create', 'update', 'hide', 'unhide', 'unchanged', 'scanned');
        }

        Product::query()
            ->with(['category.parent', 'images', 'attributeValues.attributeDefinition', 'manufacturer'])
            ->whereIn('category_id', $eligibleIds)
            ->where('is_public', true)
            ->where('status', 'active')
            ->chunkById(50, function ($products) use (&$create, &$update, &$hide, &$unhide, &$unchanged, &$scanned, $forceAll): void {
                foreach ($products as $product) {
                    $scanned++;

                    if (! $this->scope->isEligible($product)) {
                        continue;
                    }

                    $mapping = $this->scope->resolveCategoryMapping($product);

                    if ($mapping === null) {
                        continue;
                    }

                    $payload = $this->listingMapper->map($product, $mapping);
                    $hash = $this->listingMapper->fingerprintPayload($payload);
                    $hasListing = filled($product->olx_listing_id);

                    if (! $hasListing) {
                        if ($product->available_stock > 0) {
                            $create[] = (int) $product->id;
                        }

                        continue;
                    }

                    if ($product->available_stock <= 0 && $product->olx_listing_status !== 'hidden') {
                        $hide[] = (int) $product->id;

                        continue;
                    }

                    if ($product->available_stock > 0 && $product->olx_listing_status === 'hidden') {
                        $unhide[] = (int) $product->id;
                    }

                    if ($forceAll || $product->olx_export_hash !== $hash) {
                        $update[] = (int) $product->id;

                        continue;
                    }

                    $unchanged++;
                }
            });

        return compact('create', 'update', 'hide', 'unhide', 'unchanged', 'scanned');
    }
}
