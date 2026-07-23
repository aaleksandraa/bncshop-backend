<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;

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

        return self::baseQuery($search)
            ->orderBy('path')
            ->limit($limit)
            ->get(['id', 'name', 'display_name', 'full_slug'])
            ->mapWithKeys(fn (Category $category): array => [
                (string) $category->id => self::formatOptionLabel($category),
            ])
            ->all();
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

    public static function formatOptionLabel(Category $category): string
    {
        $label = filled($category->display_name) ? (string) $category->display_name : (string) $category->name;

        return sprintf('%s — %s', $label, $category->full_slug);
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
