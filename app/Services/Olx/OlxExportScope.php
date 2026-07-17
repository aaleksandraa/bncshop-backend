<?php

namespace App\Services\Olx;

use App\Models\Category;
use App\Models\OlxCategoryMapping;
use App\Models\OlxListingRegistry;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OlxExportScope
{
    /** @var Collection<int, OlxCategoryMapping>|null */
    private ?Collection $enabledMappingsCache = null;

    /** @var array<int, int>|null */
    private ?array $scopedCategoryIdsCache = null;

    /** @var array<int, true>|null */
    private ?array $scopedCategoryIdSetCache = null;

    /** @var array<int, int|null>|null */
    private ?array $parentByCategoryIdCache = null;

    /** @var array<int, array<int, int>>|null */
    private ?array $childrenByParentIdCache = null;

    /** @var array<int, OlxCategoryMapping>|null */
    private ?array $mappingByCategoryIdCache = null;

    /** @var array<int, true>|null */
    private ?array $legacyListingIdSetCache = null;

    /**
     * @return Collection<int, OlxCategoryMapping>
     */
    public function enabledMappings(): Collection
    {
        if ($this->enabledMappingsCache === null) {
            $this->enabledMappingsCache = OlxCategoryMapping::query()
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

    public function baseQuery(): Builder
    {
        $categoryIds = $this->scopedCategoryIds();
        $legacyListingIds = array_keys($this->legacyListingIdSet());
        $legacyProductIds = OlxListingRegistry::query()
            ->where('sync_mode', OlxListingRegistry::SYNC_MODE_LEGACY)
            ->whereNotNull('product_id')
            ->pluck('product_id')
            ->all();

        return Product::query()
            ->where('is_public', true)
            ->where('status', 'active')
            ->whereIn('category_id', $categoryIds === [] ? [-1] : $categoryIds)
            ->where(function (Builder $query): void {
                $query->whereNull('olx_export_enabled')
                    ->orWhere('olx_export_enabled', true);
            })
            ->whereNotIn('id', $legacyProductIds === [] ? [-1] : $legacyProductIds)
            ->where(function (Builder $query) use ($legacyListingIds): void {
                $query->whereNull('olx_listing_id');

                if ($legacyListingIds !== []) {
                    $query->orWhereNotIn('olx_listing_id', $legacyListingIds);
                } else {
                    $query->orWhereNotNull('olx_listing_id');
                }
            })
            ->where(function (Builder $query): void {
                $query->where('olx_managed', true)
                    ->orWhereNull('olx_listing_id');
            });
    }

    public function isEligible(Product $product): bool
    {
        if (! $product->is_public || $product->status !== 'active') {
            return false;
        }

        if ($product->olx_export_enabled === false) {
            return false;
        }

        if (! isset($this->scopedCategoryIdSet()[(int) $product->category_id])) {
            return false;
        }

        return ! $this->isLegacyProtected($product);
    }

    public function isLegacyProtected(Product $product): bool
    {
        if ($product->olx_managed === false && filled($product->olx_listing_id)) {
            return true;
        }

        if (! filled($product->olx_listing_id)) {
            return false;
        }

        return isset($this->legacyListingIdSet()[(int) $product->olx_listing_id]);
    }

    public function resolveCategoryMapping(Product $product): ?OlxCategoryMapping
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
     * @return array<int, OlxCategoryMapping>
     */
    private function mappingByCategoryId(): array
    {
        if ($this->mappingByCategoryIdCache === null) {
            $this->mappingByCategoryIdCache = $this->enabledMappings()
                ->keyBy(fn (OlxCategoryMapping $mapping): int => (int) $mapping->category_id)
                ->all();
        }

        return $this->mappingByCategoryIdCache;
    }

    /**
     * @return array<int, true>
     */
    private function legacyListingIdSet(): array
    {
        if ($this->legacyListingIdSetCache === null) {
            $this->legacyListingIdSetCache = OlxListingRegistry::query()
                ->where('sync_mode', OlxListingRegistry::SYNC_MODE_LEGACY)
                ->pluck('olx_listing_id')
                ->mapWithKeys(fn ($id): array => [(int) $id => true])
                ->all();
        }

        return $this->legacyListingIdSetCache;
    }
}
