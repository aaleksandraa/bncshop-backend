<?php

namespace App\Services\Homepage;

use App\Http\Resources\ProductCardResource;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class HomepageFeaturedProducts
{
    public function __construct(
        private readonly HomepageSettings $homepageSettings,
    ) {}

    /**
     * @return array{config: array<string, mixed>, products: array<int, array<string, mixed>>}
     */
    public function payload(): array
    {
        $config = $this->homepageSettings->featuredProducts();
        $tilesEnabled = (bool) ($config['tiles_enabled'] ?? false);
        $rowsEnabled = (bool) ($config['rows_enabled'] ?? false);

        if (! $tilesEnabled && ! $rowsEnabled) {
            return [
                'config' => $config,
                'products' => [],
            ];
        }

        $tilesLimit = max(0, min(8, (int) ($config['tiles_limit'] ?? 4)));
        $rowsLimit = max(0, min(6, (int) ($config['rows_limit'] ?? 2)));
        $needed = ($tilesEnabled ? $tilesLimit : 0) + ($rowsEnabled ? $rowsLimit : 0);

        if ($needed === 0) {
            return [
                'config' => $config,
                'products' => [],
            ];
        }

        $productIds = $this->homepageSettings->resolvedFeaturedProductIds($config, $needed);

        if ($productIds === []) {
            return [
                'config' => $config,
                'products' => [],
            ];
        }

        $cacheKey = 'homepage:featured-products:'.md5(implode(',', $productIds));

        $products = Cache::remember($cacheKey, 120, fn (): array => $this->loadProducts($productIds));

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
