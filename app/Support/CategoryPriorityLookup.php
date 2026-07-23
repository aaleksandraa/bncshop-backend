<?php

namespace App\Support;

use App\Models\Category;

class CategoryPriorityLookup
{
    /**
     * @param  array<int, array{names?: array<int, string>, slug_ends?: array<int, string>, slug_paths?: array<int, string>}>  $priorities
     * @return array<int, int>
     */
    public static function resolveIds(array $priorities): array
    {
        $ids = [];

        foreach ($priorities as $priority) {
            $category = self::find($priority);

            if ($category === null || in_array($category->id, $ids, true)) {
                continue;
            }

            $ids[] = $category->id;
        }

        return $ids;
    }

    /**
     * @param  array{names?: array<int, string>, slug_ends?: array<int, string>, slug_paths?: array<int, string>}  $priority
     */
    public static function find(array $priority): ?Category
    {
        foreach ($priority['slug_paths'] ?? [] as $slugPath) {
            $category = Category::query()
                ->active()
                ->whereRaw('LOWER(full_slug) = ?', [mb_strtolower($slugPath)])
                ->orderBy('depth')
                ->orderBy('path')
                ->first();

            if ($category !== null) {
                return $category;
            }
        }

        foreach ($priority['names'] ?? [] as $name) {
            $category = Category::query()
                ->active()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->orderBy('depth')
                ->orderBy('path')
                ->first();

            if ($category !== null) {
                return $category;
            }
        }

        foreach ($priority['slug_ends'] ?? [] as $slugEnd) {
            $needle = mb_strtolower($slugEnd);

            $category = Category::query()
                ->active()
                ->where(function ($query) use ($needle): void {
                    $query->whereRaw('LOWER(full_slug) = ?', [$needle])
                        ->orWhereRaw('LOWER(full_slug) LIKE ?', ['%/'.$needle]);
                })
                ->orderBy('depth')
                ->orderBy('path')
                ->first();

            if ($category !== null) {
                return $category;
            }
        }

        return null;
    }
}
