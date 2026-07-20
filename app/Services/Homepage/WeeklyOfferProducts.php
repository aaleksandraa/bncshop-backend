<?php

namespace App\Services\Homepage;

use App\Http\Resources\ProductCardResource;
use App\Models\Product;
use App\Services\Catalog\ProductReadCache;

class WeeklyOfferProducts
{
    public function __construct(
        private readonly HomepageSettings $homepageSettings,
        private readonly ProductReadCache $productReadCache,
    ) {}

    /**
     * @return array{config: array<string, mixed>, products: array<int, array<string, mixed>>}
     */
    public function payload(): array
    {
        $config = $this->homepageSettings->weeklyOffer();

        if (! ($config['enabled'] ?? false)) {
            return [
                'config' => $config,
                'products' => [],
            ];
        }

        $productIds = array_slice(
            array_values(array_map(intval(...), (array) ($config['product_ids'] ?? []))),
            0,
            max(1, min(6, (int) ($config['product_limit'] ?? 1))),
        );

        if ($productIds === []) {
            return [
                'config' => $config,
                'products' => [],
            ];
        }

        $cacheKey = 'homepage:weekly-offer:'.md5(implode(',', $productIds));

        $products = $this->productReadCache->rememberWeeklyOffer($cacheKey, 120, function () use ($productIds): array {
            return $this->loadProducts($productIds);
        });

        return [
            'config' => $config,
            'products' => $products,
        ];
    }

    /**
     * @param  array<int, int>  $productIds
     * @return array<int, array<string, mixed>>
     */
    private function loadProducts(array $productIds): array
    {
        $products = Product::query()
            ->select([
                'id',
                'slug',
                'name',
                'short_description',
                'display_price',
                'regular_price',
                'stock_status',
                'available_stock',
                'is_new',
                'is_gaming',
                'is_refurbished',
                'on_sale',
                'manufacturer_id',
                'category_id',
                'default_image_id',
            ])
            ->with([
                'manufacturer:id,name,slug,logo_path,logo_url',
                'category:id,name,full_slug',
                'defaultImage:id,product_id,local_path,public_url,image_url,source_url,is_primary,sort_order,width,height',
            ])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        return collect($productIds)
            ->map(fn (int $id): ?Product => $products->get($id))
            ->filter()
            ->values()
            ->map(fn (Product $product): array => (new ProductCardResource($product))->resolve())
            ->all();
    }
}
