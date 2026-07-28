<?php

namespace App\Services\Menu;

use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;

class HeaderMenuSyncService
{
    private const HEADER_CHILD_LIMIT = 24;

    private const HEADER_MAX_DEPTH = 3;

    public function syncChildrenForCategory(Category $category): void
    {
        $menu = Menu::query()
            ->where('slug', 'header')
            ->where('is_active', true)
            ->first();

        if ($menu === null) {
            return;
        }

        $parentItem = MenuItem::query()
            ->where('menu_id', $menu->id)
            ->where('type', MenuItem::TYPE_CATEGORY)
            ->where('category_id', $category->id)
            ->first();

        if ($parentItem === null) {
            return;
        }

        $this->syncCategoryChildren($menu, $parentItem, $category->id, 2);
    }

    public function syncCategoryChildren(Menu $menu, MenuItem $parentItem, int $categoryId, int $depth): void
    {
        if ($depth > self::HEADER_MAX_DEPTH) {
            return;
        }

        $existingChildren = $menu->items()
            ->where('parent_id', $parentItem->id)
            ->where('type', MenuItem::TYPE_CATEGORY)
            ->get()
            ->keyBy('category_id');

        $children = Category::query()
            ->active()
            ->where('parent_id', $categoryId)
            ->orderByRaw("COALESCE(NULLIF(display_name, ''), name)")
            ->limit(self::HEADER_CHILD_LIMIT)
            ->get();

        $activeChildIds = $children->pluck('id')->all();

        foreach ($existingChildren as $existing) {
            if (! in_array((int) $existing->category_id, $activeChildIds, true)) {
                $this->deactivateMenuItemTree($existing);
            }
        }

        $sortOrder = 0;

        foreach ($children as $child) {
            $existing = $existingChildren->get($child->id);

            if ($existing !== null) {
                $existing->update([
                    'label' => null,
                    'sort_order' => $sortOrder++,
                    'is_active' => true,
                ]);
                $childItem = $existing;
            } else {
                $childItem = MenuItem::query()->create([
                    'menu_id' => $menu->id,
                    'parent_id' => $parentItem->id,
                    'type' => MenuItem::TYPE_CATEGORY,
                    'category_id' => $child->id,
                    'label' => null,
                    'sort_order' => $sortOrder++,
                    'is_active' => true,
                ]);
            }

            $this->syncCategoryChildren($menu, $childItem, $child->id, $depth + 1);
        }
    }

    private function deactivateMenuItemTree(MenuItem $item): void
    {
        $item->update(['is_active' => false]);

        MenuItem::query()
            ->where('parent_id', $item->id)
            ->get()
            ->each(function (MenuItem $child): void {
                $this->deactivateMenuItemTree($child);
            });
    }
}
