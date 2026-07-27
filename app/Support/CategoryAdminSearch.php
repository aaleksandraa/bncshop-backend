<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CategoryAdminSearch
{
    /**
     * @return array<string, string>
     */
    public static function optionsForSearch(string $search, int $limit = 50): array
    {
        $search = trim($search);

        if ($search === '') {
            return [];
        }

        $byId = self::categoryIndex();

        return self::baseQuery($search)
            ->withCount('products')
            ->orderBy('path')
            ->limit($limit)
            ->get(['id', 'name', 'display_name', 'full_slug', 'parent_id', 'depth', 'path'])
            ->mapWithKeys(fn (Category $category): array => [
                (string) $category->id => self::formatOptionLabel($category, $byId),
            ])
            ->all();
    }

    /**
     * @param  array<int|string>  $ids
     * @return array<string, string>
     */
    public static function labelsForIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $byId = self::categoryIndex();

        return Category::query()
            ->whereIn('id', $ids)
            ->withCount('products')
            ->get(['id', 'name', 'display_name', 'full_slug', 'parent_id', 'depth', 'path'])
            ->mapWithKeys(fn (Category $category): array => [
                (string) $category->id => self::formatOptionLabel($category, $byId),
            ])
            ->all();
    }

    public static function labelForId(int|string|null $id): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }

        $category = Category::query()
            ->withCount('products')
            ->find((int) $id);

        if ($category === null) {
            return null;
        }

        return self::formatOptionLabel($category, self::categoryIndex());
    }

    public static function applySearchTerm(Builder $query, string $search): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $pattern = '%'.$search.'%';

        return $query->where(function (Builder $inner) use ($search, $operator, $pattern): void {
            $inner->where('name', $operator, $pattern)
                ->orWhere('display_name', $operator, $pattern)
                ->orWhere('full_slug', $operator, $pattern);

            if (ctype_digit($search)) {
                $inner->orWhere('id', (int) $search);
            }
        });
    }

    /**
     * @param  Collection<int, Category>|null  $byId
     */
    public static function formatOptionLabel(Category $category, ?Collection $byId = null): string
    {
        $name = $category->publicName();
        $count = (int) ($category->products_count ?? 0);
        $countLabel = self::formatProductCount($count);
        $byId ??= self::categoryIndex();

        if ($category->parent_id === null) {
            return sprintf('%s — glavna kategorija %s', $name, $countLabel);
        }

        $parentPath = self::buildParentPath($category, $byId);

        return sprintf('%s — podkategorija: %s %s', $name, $parentPath, $countLabel);
    }

    public static function formatProductCount(int $count): string
    {
        $word = 'proizvoda';

        if ($count % 100 < 11 || $count % 100 > 14) {
            $word = match ($count % 10) {
                1 => 'proizvod',
                2, 3, 4 => 'proizvoda',
                default => 'proizvoda',
            };
        }

        return sprintf('(%d %s)', $count, $word);
    }

    /**
     * @param  Collection<int, Category>  $byId
     */
    private static function buildParentPath(Category $category, Collection $byId): string
    {
        $parts = [];
        $parentId = $category->parent_id;

        while ($parentId !== null) {
            $parent = $byId->get((int) $parentId);

            if ($parent === null) {
                break;
            }

            array_unshift($parts, $parent->publicName());
            $parentId = $parent->parent_id;
        }

        if ($parts === []) {
            return '—';
        }

        return implode(' › ', $parts);
    }

    /**
     * @return Collection<int, Category>
     */
    private static function categoryIndex(): Collection
    {
        return Category::query()
            ->active()
            ->select(['id', 'name', 'display_name', 'parent_id'])
            ->orderBy('path')
            ->get()
            ->keyBy('id');
    }

    /**
     * @return Builder<Category>
     */
    private static function baseQuery(string $search): Builder
    {
        return self::applySearchTerm(
            Category::query()->active(),
            $search,
        );
    }
}
