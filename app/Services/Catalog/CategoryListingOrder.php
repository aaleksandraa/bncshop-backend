<?php

namespace App\Services\Catalog;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CategoryListingOrder
{
    /** @var array<int, string> */
    private const KLIMA_CHILD_SLUG_ORDER = [
        'klime',
        'grijanje-tijela',
        'grijanja-tijela',
        'grijanje',
    ];

    /**
     * @param  Collection<int, Category>  $categories
     * @return Collection<int, Category>
     */
    public function sortDirectChildren(Category $parent, Collection $categories): Collection
    {
        if (! $this->isKlimaParent($parent)) {
            return $categories->sortBy(fn (Category $category): string => $category->name, SORT_NATURAL | SORT_FLAG_CASE)->values();
        }

        return $categories
            ->sort(function (Category $a, Category $b): int {
                $aRank = $this->childSlugRank($a);
                $bRank = $this->childSlugRank($b);

                if ($aRank !== $bRank) {
                    return $aRank <=> $bRank;
                }

                return strcasecmp($a->name, $b->name);
            })
            ->values();
    }

    public function applyProductOrdering(Builder $query, Category $parent): void
    {
        $rankMap = $this->buildCategoryRankMap($parent);

        if ($rankMap === null) {
            return;
        }

        $cases = collect($rankMap)
            ->map(fn (int $rank, int $categoryId): string => "WHEN {$categoryId} THEN {$rank}")
            ->implode(' ');

        $query->orderByRaw("CASE category_id {$cases} ELSE 999 END");
    }

    /**
     * @return array<int, int>|null
     */
    public function buildCategoryRankMap(Category $parent): ?array
    {
        if (! $this->isKlimaParent($parent)) {
            return null;
        }

        $directChildren = Category::query()
            ->active()
            ->where('parent_id', $parent->id)
            ->get();

        $sortedChildren = $this->sortDirectChildren($parent, $directChildren);
        $rankMap = [];

        $treeCategories = Category::query()
            ->active()
            ->where(function (Builder $query) use ($parent): void {
                $query->where('id', $parent->id)
                    ->orWhere('path', 'like', $parent->path.'/%');
            })
            ->get(['id', 'parent_id']);

        $childrenByParent = $treeCategories
            ->groupBy(fn (Category $category): int => (int) ($category->parent_id ?? 0))
            ->map(fn (Collection $group) => $group->pluck('id')->values());

        foreach ($sortedChildren as $index => $child) {
            $this->assignRankToTree((int) $child->id, $index, $rankMap, $childrenByParent);
        }

        $rankMap[(int) $parent->id] = $sortedChildren->count();

        return $rankMap;
    }

    /**
     * @param  array<int, int>  $rankMap
     */
    private function assignRankToTree(
        int $categoryId,
        int $rank,
        array &$rankMap,
        Collection $childrenByParent,
    ): void {
        $rankMap[$categoryId] = $rank;

        foreach ($childrenByParent->get($categoryId, collect()) as $childId) {
            $this->assignRankToTree((int) $childId, $rank, $rankMap, $childrenByParent);
        }
    }

    private function isKlimaParent(Category $category): bool
    {
        $slug = mb_strtolower($category->full_slug);
        $name = mb_strtolower($category->name);

        return str_contains($slug, 'klima-grijanje')
            || str_contains($slug, 'klime')
            || str_contains($slug, 'klima')
            || str_contains($name, 'klima')
            || str_contains($name, 'grijanje');
    }

    private function childSlugRank(Category $category): int
    {
        $slug = mb_strtolower($category->full_slug);
        $name = mb_strtolower($category->name);

        foreach (self::KLIMA_CHILD_SLUG_ORDER as $index => $needle) {
            if (
                str_ends_with($slug, '/'.$needle)
                || $slug === $needle
                || str_contains($slug, '/'.$needle.'/')
                || str_contains($name, $needle)
            ) {
                return $index;
            }
        }

        return PHP_INT_MAX;
    }
}
