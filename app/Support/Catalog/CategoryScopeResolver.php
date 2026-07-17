<?php

namespace App\Support\Catalog;

use App\Models\Category;

class CategoryScopeResolver
{
    /**
     * @return array<int, int>
     */
    public static function descendantIds(int $categoryId, bool $includeSelf = false): array
    {
        $ids = $includeSelf ? [$categoryId] : [];
        $pending = [$categoryId];

        while ($pending !== []) {
            $children = Category::query()
                ->whereIn('parent_id', $pending)
                ->pluck('id')
                ->all();

            $ids = array_merge($ids, $children);
            $pending = $children;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<int, string>
     */
    public static function descendantOptions(int $categoryId): array
    {
        $options = [];
        self::collectDescendantOptions($categoryId, 0, $options);

        return $options;
    }

    /**
     * @param  array<int, string>  $options
     */
    private static function collectDescendantOptions(int $parentId, int $depth, array &$options): void
    {
        $children = Category::query()
            ->where('parent_id', $parentId)
            ->orderBy('name')
            ->get();

        foreach ($children as $child) {
            $prefix = $depth > 0 ? str_repeat('— ', $depth) : '';
            $options[$child->id] = $prefix.$child->publicName();
            self::collectDescendantOptions($child->id, $depth + 1, $options);
        }
    }
}
