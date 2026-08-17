<?php

namespace App\Services\Pricing;

use App\Models\Product;
use App\Models\SupplierCategoryMarginRule;
use App\Services\Catalog\ProductReadCache;
use Illuminate\Database\Eloquent\Builder;

class ProductPriceRecalculator
{
    public function __construct(
        private readonly PriceCalculator $priceCalculator,
        private readonly ProductReadCache $productReadCache,
    ) {}

    public function forMarginRule(SupplierCategoryMarginRule $rule): int
    {
        $count = 0;
        $categoryIds = $rule->resolvedTargetCategoryIds();

        Product::query()
            ->where('price_locked', false)
            ->notFromEline()
            ->whereHas('supplierOffers', fn (Builder $offer) => $offer->where('supplier_id', $rule->supplier_id))
            ->whereIn('category_id', $categoryIds)
            ->chunkById(500, function ($products) use (&$count): void {
                foreach ($products as $product) {
                    $this->priceCalculator->recalculateAndPersist($product);
                    $count++;
                }
            });

        $this->productReadCache->flushAll();

        return $count;
    }

    public function forCategoryMarginRule(\App\Models\CategoryMarginRule $rule): int
    {
        $count = 0;
        $categoryIds = $rule->resolvedTargetCategoryIds();

        Product::query()
            ->where('price_locked', false)
            ->where('is_new', true)
            ->where(function ($query): void {
                $query->whereNull('import_source')
                    ->orWhere('import_source', '!=', 'eline');
            })
            ->whereIn('category_id', $categoryIds)
            ->chunkById(500, function ($products) use (&$count): void {
                foreach ($products as $product) {
                    $this->priceCalculator->recalculateAndPersist($product);
                    $count++;
                }
            });

        $this->productReadCache->flushAll();

        return $count;
    }

    public function forSupplierAndCategory(int $supplierId, ?int $categoryId = null): int
    {
        $count = 0;
        $afterProductId = 0;

        do {
            $processed = $this->forSupplierChunk($supplierId, $afterProductId, 500, $categoryId, $afterProductId);
            $count += $processed;
        } while ($processed === 500);

        $this->productReadCache->flushAll();

        return $count;
    }

    public function forSupplierChunk(
        int $supplierId,
        int $afterProductId,
        int $limit,
        ?int $categoryId = null,
        ?int &$lastProcessedId = null,
    ): int {
        $count = 0;
        $lastProcessedId = $afterProductId;

        $this->productQuery($supplierId, $categoryId)
            ->where('products.id', '>', $afterProductId)
            ->with(['supplierOffers.supplier', 'category'])
            ->orderBy('products.id')
            ->limit($limit)
            ->get()
            ->each(function (Product $product) use (&$count, &$lastProcessedId, $supplierId): void {
                try {
                    $this->priceCalculator->recalculateAndPersist($product);
                    $lastProcessedId = $product->id;
                    $count++;
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Product price recalculation failed.', [
                        'product_id' => $product->id,
                        'supplier_id' => $supplierId,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

        return $count;
    }

    public function forProduct(Product $product): void
    {
        $this->priceCalculator->recalculateAndPersist($product->fresh());
    }

    /**
     * @param  callable(int $count, int $lastProductId): void|null  $onProgress
     */
    public function forAll(?int $supplierId = null, ?int $categoryId = null, ?callable $onProgress = null): int
    {
        if ($supplierId !== null) {
            return $this->forSupplierAndCategory($supplierId, $categoryId);
        }

        $count = 0;
        $query = Product::query()
            ->where('price_locked', false)
            ->notFromEline()
            ->with(['supplierOffers.supplier', 'category']);

        if ($categoryId) {
            $query->whereIn('category_id', $this->categoryIdsWithDescendants($categoryId));
        }

        $query->chunkById(500, function ($products) use (&$count, $onProgress): void {
            foreach ($products as $product) {
                try {
                    $this->priceCalculator->recalculateAndPersist($product);
                    $count++;
                    $onProgress && $onProgress($count, (int) $product->id);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Product price recalculation failed.', [
                        'product_id' => $product->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        $this->productReadCache->flushAll();

        return $count;
    }

    private function productQuery(int $supplierId, ?int $categoryId): Builder
    {
        $query = Product::query()
            ->where('price_locked', false)
            ->notFromEline()
            ->whereHas('supplierOffers', fn (Builder $offer) => $offer->where('supplier_id', $supplierId));

        if ($categoryId) {
            $categoryIds = $this->categoryIdsWithDescendants($categoryId);

            $query->whereIn('category_id', $categoryIds);
        }

        return $query;
    }

    /**
     * @return array<int, int>
     */
    private function categoryIdsWithDescendants(int $categoryId): array
    {
        $ids = [$categoryId];
        $pending = [$categoryId];

        while ($pending !== []) {
            $children = \App\Models\Category::query()
                ->whereIn('parent_id', $pending)
                ->pluck('id')
                ->all();

            $pending = $children;
            $ids = array_merge($ids, $children);
        }

        return array_values(array_unique($ids));
    }
}
