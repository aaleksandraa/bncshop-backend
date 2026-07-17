<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Services\Pricing\PricingCache;
use Illuminate\Support\Collection;

class CategoryScopeResolver
{
    public function __construct(
        private readonly PricingCache $pricingCache,
    ) {}
    /**
     * @param  array<int, int|string>  $categoryIds
     */
    public function matchesAnyCategory(Product $product, array $categoryIds, bool $includeSubcategories = false): bool
    {
        if ($product->category_id === null || $categoryIds === []) {
            return false;
        }

        $normalizedIds = array_map(intval(...), $categoryIds);
        $productCategoryId = (int) $product->category_id;

        if (in_array($productCategoryId, $normalizedIds, true)) {
            return true;
        }

        if (! $includeSubcategories) {
            return false;
        }

        $allowedIds = $this->expandWithDescendants($normalizedIds);

        return in_array($productCategoryId, $allowedIds, true);
    }

    /**
     * @param  array<int, int>  $categoryIds
     * @return array<int, int>
     */
    public function expandWithDescendants(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $categories = $this->pricingCache->rememberCategoryTree(
            fn () => Category::query()
                ->select(['id', 'parent_id'])
                ->get()
                ->keyBy('id')
        );

        $childrenByParent = $categories->groupBy('parent_id');
        $allowed = [];

        foreach ($categoryIds as $categoryId) {
            $this->collectDescendants((int) $categoryId, $childrenByParent, $allowed);
        }

        return array_values(array_unique($allowed));
    }

    /**
     * @param  Collection<int|string, Collection<int, Category>>  $childrenByParent
     * @param  array<int, int>  $allowed
     */
    private function collectDescendants(int $categoryId, Collection $childrenByParent, array &$allowed): void
    {
        $allowed[] = $categoryId;

        foreach ($childrenByParent->get($categoryId, collect()) as $child) {
            $this->collectDescendants((int) $child->id, $childrenByParent, $allowed);
        }
    }
}
