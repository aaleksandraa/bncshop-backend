<?php

namespace App\Services\Ananas;

use App\Models\AnanasCategoryMapping;
use App\Models\Category;
use App\Models\Product;

class AnanasProbeProductFinder
{
    public function __construct(
        private readonly AnanasEligibilityPolicy $eligibilityPolicy,
    ) {}

    /**
     * @return array{
     *   category_ids: list<int>,
     *   total_in_scope: int,
     *   active_public: int,
     *   eligible: int,
     *   reasons: array<string, int>,
     *   first_eligible_product_id: int|null
     * }
     */
    public function diagnose(AnanasCategoryMapping $mapping, int $scanLimit = 500): array
    {
        $categoryIds = $this->scopedCategoryIds($mapping);
        $baseQuery = Product::query()->whereIn('category_id', $categoryIds === [] ? [-1] : $categoryIds);

        $totalInScope = (clone $baseQuery)->count();
        $activePublicQuery = (clone $baseQuery)
            ->where('is_public', true)
            ->where('status', 'active');

        $activePublic = (clone $activePublicQuery)->count();

        $reasons = [];
        $eligible = 0;
        $firstEligibleId = null;

        $activePublicQuery
            ->select(['id', 'category_id', 'barcode', 'is_public', 'status', 'is_refurbished', 'is_set', 'available_stock'])
            ->with(['images', 'attributeValues.attributeDefinition', 'manufacturer'])
            ->orderBy('id')
            ->limit($scanLimit)
            ->chunkById(100, function ($products) use (&$reasons, &$eligible, &$firstEligibleId): void {
                foreach ($products as $product) {
                    if (! $product instanceof Product) {
                        continue;
                    }

                    $result = $this->eligibilityPolicy->evaluateProductData($product);

                    if ($result->eligible) {
                        $eligible++;
                        $firstEligibleId ??= (int) $product->id;

                        continue;
                    }

                    $code = $result->reasonCode ?? 'NOT_ELIGIBLE';
                    $reasons[$code] = ($reasons[$code] ?? 0) + 1;
                }
            });

        ksort($reasons);

        return [
            'category_ids' => $categoryIds,
            'total_in_scope' => $totalInScope,
            'active_public' => $activePublic,
            'eligible' => $eligible,
            'reasons' => $reasons,
            'first_eligible_product_id' => $firstEligibleId,
        ];
    }

    public function findFirstEligible(AnanasCategoryMapping $mapping, int $scanLimit = 500): ?Product
    {
        return $this->findFirstEligibleInCategoryIds($this->scopedCategoryIds($mapping), $scanLimit);
    }

    public function findFirstEligibleGlobally(int $scanLimit = 5000): ?Product
    {
        return $this->findFirstEligibleInCategoryIds(null, $scanLimit);
    }

    /**
     * @return list<array{product_id: int, category_id: int|null, name: string, ean: string|null}>
     */
    public function listEligibleGlobally(int $limit = 10, int $scanLimit = 5000): array
    {
        $results = [];

        Product::query()
            ->where('is_public', true)
            ->where('status', 'active')
            ->with(['images', 'manufacturer', 'attributeValues.attributeDefinition'])
            ->orderBy('id')
            ->limit($scanLimit)
            ->chunkById(100, function ($products) use (&$results, $limit): bool {
                foreach ($products as $product) {
                    if (! $product instanceof Product) {
                        continue;
                    }

                    if (! $this->eligibilityPolicy->evaluateProductData($product)->eligible) {
                        continue;
                    }

                    $results[] = [
                        'product_id' => (int) $product->id,
                        'category_id' => $product->category_id !== null ? (int) $product->category_id : null,
                        'name' => (string) $product->name,
                        'ean' => $product->barcode,
                    ];

                    if (count($results) >= $limit) {
                        return false;
                    }
                }

                return true;
            });

        return $results;
    }

    /**
     * @param  list<int>|null  $categoryIds
     */
    private function findFirstEligibleInCategoryIds(?array $categoryIds, int $scanLimit): ?Product
    {
        $candidate = null;

        $query = Product::query()
            ->where('is_public', true)
            ->where('status', 'active')
            ->with(['images', 'manufacturer', 'attributeValues.attributeDefinition'])
            ->orderBy('id');

        if ($categoryIds !== null) {
            $query->whereIn('category_id', $categoryIds === [] ? [-1] : $categoryIds);
        }

        $query
            ->limit($scanLimit)
            ->chunkById(100, function ($products) use (&$candidate): bool {
                foreach ($products as $product) {
                    if (! $product instanceof Product) {
                        continue;
                    }

                    if ($this->eligibilityPolicy->evaluateProductData($product)->eligible) {
                        $candidate = $product;

                        return false;
                    }
                }

                return true;
            });

        return $candidate;
    }

    /**
     * @return list<int>
     */
    private function scopedCategoryIds(AnanasCategoryMapping $mapping): array
    {
        $categoryIds = [(int) $mapping->category_id];

        if ($mapping->include_descendants) {
            $parentMap = Category::query()->pluck('parent_id', 'id')->all();
            $childrenByParent = [];

            foreach ($parentMap as $id => $parentId) {
                if ($parentId !== null) {
                    $childrenByParent[(int) $parentId][] = (int) $id;
                }
            }

            $queue = [(int) $mapping->category_id];
            $descendants = [];

            while ($queue !== []) {
                $current = array_shift($queue);

                foreach ($childrenByParent[$current] ?? [] as $childId) {
                    $descendants[] = $childId;
                    $queue[] = $childId;
                }
            }

            $categoryIds = array_merge($categoryIds, $descendants);
        }

        return array_values(array_unique(array_filter($categoryIds)));
    }
}
