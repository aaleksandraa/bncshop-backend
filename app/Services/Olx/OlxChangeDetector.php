<?php

namespace App\Services\Olx;

use App\Models\Product;
use Illuminate\Support\Collection;

class OlxChangeDetector
{
    public function __construct(
        private readonly OlxExportScope $scope,
        private readonly OlxListingMapper $listingMapper,
    ) {}

    /**
     * @return array{
     *     create: Collection<int, Product>,
     *     update: Collection<int, Product>,
     *     hide: Collection<int, Product>,
     *     unhide: Collection<int, Product>,
     *     unchanged: int,
     *     scanned: int
     * }
     */
    public function detect(bool $forceAll = false): array
    {
        $create = collect();
        $update = collect();
        $hide = collect();
        $unhide = collect();
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
            ->chunkById(100, function ($products) use (&$create, &$update, &$hide, &$unhide, &$unchanged, &$scanned, $forceAll): void {
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
                            $create->push($product);
                        }

                        continue;
                    }

                    if ($product->available_stock <= 0 && $product->olx_listing_status !== 'hidden') {
                        $hide->push($product);

                        continue;
                    }

                    if ($product->available_stock > 0 && $product->olx_listing_status === 'hidden') {
                        $unhide->push($product);
                    }

                    if ($forceAll || $product->olx_export_hash !== $hash) {
                        $update->push($product);

                        continue;
                    }

                    $unchanged++;
                }
            });

        return compact('create', 'update', 'hide', 'unhide', 'unchanged', 'scanned');
    }
}
