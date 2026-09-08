<?php

namespace App\Services\Ananas;

use App\Models\AnanasCategoryMapping;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AnanasExportScope
{
    /** @var Collection<int, AnanasCategoryMapping>|null */
    private ?Collection $enabledMappingsCache = null;

    /** @var array<int, int>|null */
    private ?array $scopedCategoryIdsCache = null;

    /** @var array<int, true>|null */
    private ?array $scopedCategoryIdSetCache = null;

    /** @var array<int, int|null>|null */
    private ?array $parentByCategoryIdCache = null;

    /** @var array<int, array<int, int>>|null */
    private ?array $childrenByParentIdCache = null;

    /** @var array<int, AnanasCategoryMapping>|null */
    private ?array $mappingByCategoryIdCache = null;

    /**
     * @return Collection<int, AnanasCategoryMapping>
     */
    public function enabledMappings(): Collection
    {
        if ($this->enabledMappingsCache === null) {
            $this->enabledMappingsCache = AnanasCategoryMapping::query()
                ->with('category')
                ->where('is_enabled', true)
                ->get();
        }

        return $this->enabledMappingsCache;
    }

    /**
     * @return array<int, int>
     */
    public function scopedCategoryIds(): array
    {
        if ($this->scopedCategoryIdsCache !== null) {
            return $this->scopedCategoryIdsCache;
        }

        $ids = [];

        foreach ($this->enabledMappings() as $mapping) {
            $ids[] = (int) $mapping->category_id;

            if ($mapping->include_descendants) {
                $ids = array_merge($ids, $this->descendantCategoryIds((int) $mapping->category_id));
            }
        }

        $this->scopedCategoryIdsCache = array_values(array_unique(array_filter($ids)));

        return $this->scopedCategoryIdsCache;
    }

    public function baseQuery(): Builder
    {
        $categoryIds = $this->scopedCategoryIds();

        return Product::query()
            ->where('is_public', true)
            ->where('status', 'active')
            ->whereIn('category_id', $categoryIds === [] ? [-1] : $categoryIds);
    }

    public function isInScopedCategory(Product $product): bool
    {
        if ($product->category_id === null) {
            return false;
        }

        return isset($this->scopedCategoryIdSet()[(int) $product->category_id]);
    }

    public function resolveCategoryMapping(Product $product): ?AnanasCategoryMapping
    {
        $categoryId = $product->category_id !== null ? (int) $product->category_id : null;
        $parentMap = $this->parentByCategoryId();
        $mappingByCategory = $this->mappingByCategoryId();

        while ($categoryId !== null) {
            if (isset($mappingByCategory[$categoryId])) {
                return $mappingByCategory[$categoryId];
            }

            $categoryId = $parentMap[$categoryId] ?? null;
        }

        return null;
    }

    /**
     * @return array<int, int>
     */
    private function descendantCategoryIds(int $categoryId): array
    {
        $childrenByParent = $this->childrenByParentId();
        $ids = [];
        $queue = [$categoryId];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($childrenByParent[$current] ?? [] as $childId) {
                $ids[] = $childId;
                $queue[] = $childId;
            }
        }

        return $ids;
    }

    /**
     * @return array<int, true>
     */
    private function scopedCategoryIdSet(): array
    {
        if ($this->scopedCategoryIdSetCache === null) {
            $this->scopedCategoryIdSetCache = array_fill_keys($this->scopedCategoryIds(), true);
        }

        return $this->scopedCategoryIdSetCache;
    }

    /**
     * @return array<int, int|null>
     */
    private function parentByCategoryId(): array
    {
        if ($this->parentByCategoryIdCache === null) {
            $this->parentByCategoryIdCache = Category::query()
                ->pluck('parent_id', 'id')
                ->map(fn ($parentId) => $parentId !== null ? (int) $parentId : null)
                ->all();
        }

        return $this->parentByCategoryIdCache;
    }

    /**
     * @return array<int, array<int, int>>
     */
    private function childrenByParentId(): array
    {
        if ($this->childrenByParentIdCache === null) {
            $index = [];

            foreach ($this->parentByCategoryId() as $id => $parentId) {
                if ($parentId === null) {
                    continue;
                }

                $index[$parentId][] = (int) $id;
            }

            $this->childrenByParentIdCache = $index;
        }

        return $this->childrenByParentIdCache;
    }

    /**
     * @return array<int, AnanasCategoryMapping>
     */
    private function mappingByCategoryId(): array
    {
        if ($this->mappingByCategoryIdCache === null) {
            $this->mappingByCategoryIdCache = $this->enabledMappings()
                ->keyBy(fn (AnanasCategoryMapping $mapping): int => (int) $mapping->category_id)
                ->all();
        }

        return $this->mappingByCategoryIdCache;
    }
}
