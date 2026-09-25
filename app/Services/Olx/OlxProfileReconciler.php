<?php

namespace App\Services\Olx;

use App\Models\OlxListingRegistry;
use App\Models\Product;

class OlxProfileReconciler
{
    public function __construct(
        private readonly OlxApiClient $client,
        private readonly OlxSyncSettings $settings,
        private readonly OlxExportScope $scope,
    ) {}

    /**
     * Shop catalog is the source of truth: OLX ads without a sellable shop product are removed.
     *
     * @return array{
     *     delete_product_ids: list<int>,
     *     delete_listing_ids: list<int>,
     *     remote_scanned: int
     * }
     */
    public function plan(): array
    {
        $username = $this->settings->credentials()['username']
            ?: (string) config('bnc.olx_shop_username', 'bnc');

        $remoteIds = $this->client->listShopListingIds($username);
        $productsByListingId = $this->productsByListingId();

        $deleteProductIds = [];
        $deleteListingIds = [];

        foreach ($remoteIds as $listingId) {
            $product = $productsByListingId[$listingId] ?? $this->productFromRegistry($listingId);

            if ($product === null) {
                $deleteListingIds[] = $listingId;

                continue;
            }

            if (! $this->shouldKeepOnProfile($product)) {
                $deleteProductIds[] = (int) $product->id;
            }
        }

        return [
            'delete_product_ids' => array_values(array_unique($deleteProductIds)),
            'delete_listing_ids' => array_values(array_unique($deleteListingIds)),
            'remote_scanned' => count($remoteIds),
        ];
    }

    /**
     * @return array<int, Product>
     */
    private function productsByListingId(): array
    {
        $map = [];

        Product::query()
            ->whereNotNull('olx_listing_id')
            ->where('olx_listing_id', '!=', '')
            ->chunkById(200, function ($products) use (&$map): void {
                foreach ($products as $product) {
                    $listingId = (int) $product->olx_listing_id;

                    if ($listingId > 0) {
                        $map[$listingId] = $product;
                    }
                }
            });

        return $map;
    }

    private function productFromRegistry(int $listingId): ?Product
    {
        $productId = OlxListingRegistry::query()
            ->where('olx_listing_id', $listingId)
            ->value('product_id');

        if (! is_numeric($productId)) {
            return null;
        }

        return Product::query()->find((int) $productId);
    }

    private function shouldKeepOnProfile(Product $product): bool
    {
        return $this->scope->isEligible($product)
            && $this->scope->resolveCategoryMapping($product) !== null;
    }
}
