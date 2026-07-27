<?php

namespace App\Services\Catalog;

use App\Jobs\ReindexProductsJob;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ProductBulkService
{
    /**
     * @param  Collection<int, Product>  $products
     */
    public function reassignCategory(Collection $products, int $categoryId): int
    {
        $category = Category::query()->find($categoryId);

        if ($category === null) {
            throw new InvalidArgumentException('Odabrana kategorija ne postoji.');
        }

        $productIds = $products->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($productIds === []) {
            return 0;
        }

        $updated = Product::query()
            ->whereIn('id', $productIds)
            ->update(['category_id' => $categoryId]);

        ReindexProductsJob::dispatch($productIds);

        $cache = app(ProductReadCache::class);
        $cache->flushProducts();
        $cache->flushListAndFilters($categoryId);

        return $updated;
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    public function updateStatus(Collection $products, string $status): int
    {
        if (! in_array($status, ['active', 'inactive', 'archived'], true)) {
            throw new InvalidArgumentException('Nepoznat status proizvoda.');
        }

        $productIds = $products->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($productIds === []) {
            return 0;
        }

        $updated = Product::query()
            ->whereIn('id', $productIds)
            ->update(['status' => $status]);

        ReindexProductsJob::dispatch($productIds);
        app(ProductReadCache::class)->flushProducts();

        return $updated;
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    public function updateVisibility(Collection $products, bool $isPublic): int
    {
        $productIds = $products->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($productIds === []) {
            return 0;
        }

        $updated = Product::query()
            ->whereIn('id', $productIds)
            ->update(['is_public' => $isPublic]);

        ReindexProductsJob::dispatch($productIds);
        app(ProductReadCache::class)->flushProducts();

        return $updated;
    }
}
