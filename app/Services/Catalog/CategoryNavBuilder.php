<?php

namespace App\Services\Catalog;

use App\Http\Resources\CategoryNavResource;
use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryNavBuilder
{
    public function __construct(
        private readonly ProductReadCache $productReadCache,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildPayload(): array
    {
        $categories = $this->productReadCache->rememberCategoryNav(300, function () {
            return Category::query()
                ->active()
                ->select([
                    'id',
                    'name',
                    'display_name',
                    'full_slug',
                    'parent_id',
                    'depth',
                    'path',
                ])
                ->orderBy('path')
                ->get();
        });

        return CategoryNavResource::collection($this->buildTree($categories))->resolve();
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return Collection<int, Category>
     */
    private function buildTree(Collection $categories): Collection
    {
        $childrenByParent = $categories->groupBy(
            fn (Category $category): int => (int) ($category->parent_id ?? 0),
        );

        $attachChildren = function (Category $category) use (&$attachChildren, $childrenByParent): Category {
            $children = ($childrenByParent[(int) $category->id] ?? collect())
                ->sortBy(fn (Category $child): string => $child->publicName(), SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->each(fn (Category $child) => $attachChildren($child));

            $category->setRelation('children', $children);

            return $category;
        };

        return ($childrenByParent[0] ?? collect())
            ->sortBy(fn (Category $category): string => $category->publicName(), SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->each(fn (Category $category) => $attachChildren($category));
    }
}
