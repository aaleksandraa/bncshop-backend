<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Console\Command;

class EnsureHeaderMenuCommand extends Command
{
    protected $signature = 'bnc:ensure-header-menu';

    protected $description = 'Ensure header menu has Softver i licence and Ostalo (/kategorije) after it';

    /**
     * @var array{names: array<int, string>, slug_ends: array<int, string>, slug_paths: array<int, string>, label?: string|null}
     */
    private const SOFTVER_PRIORITY = [
        'names' => ['Softver i licence', 'Softver', 'Licence'],
        'slug_ends' => ['softver', 'softver-licence', 'softver-i-licence'],
        'slug_paths' => [
            'softver-licence',
            'softver-i-licence',
            'softver-i-licenci',
            'it-oprema/softver-i-licence',
            'softver/licence',
            'softver',
        ],
        'label' => 'Softver i licence',
    ];

    public function handle(): int
    {
        $menu = Menu::query()
            ->where('slug', 'header')
            ->where('is_active', true)
            ->first();

        if ($menu === null) {
            $this->error('Header menu not found.');

            return self::FAILURE;
        }

        $softverItem = $this->ensureSoftverCategoryItem($menu);
        $this->ensureOstaloLink($menu, $softverItem);

        $this->info('Header menu updated (Softver i licence + Ostalo).');

        return self::SUCCESS;
    }

    private function ensureSoftverCategoryItem(Menu $menu): ?MenuItem
    {
        $existing = $menu->items()
            ->whereNull('parent_id')
            ->where(function ($query): void {
                $query->where('label', 'Softver i licence')
                    ->orWhere('label', 'Softver')
                    ->orWhereHas('category', function ($category): void {
                        $category->whereRaw('LOWER(full_slug) LIKE ?', ['%softver%']);
                    });
            })
            ->first();

        if ($existing !== null) {
            if ($existing->label !== 'Softver i licence') {
                $existing->update(['label' => 'Softver i licence']);
            }

            return $existing;
        }

        $category = $this->findPriorityCategory(self::SOFTVER_PRIORITY);

        if ($category === null) {
            $this->warn('Softver category not found in catalog — skipped.');

            return null;
        }

        $sortOrder = ((int) $menu->items()->whereNull('parent_id')->max('sort_order')) + 1;

        return MenuItem::query()->create([
            'menu_id' => $menu->id,
            'type' => MenuItem::TYPE_CATEGORY,
            'category_id' => $category->id,
            'label' => 'Softver i licence',
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }

    private function ensureOstaloLink(Menu $menu, ?MenuItem $afterItem): void
    {
        $existing = $menu->items()
            ->whereNull('parent_id')
            ->where('url', '/kategorije')
            ->where('label', 'Ostalo')
            ->first();

        $sortOrder = $afterItem !== null
            ? $afterItem->sort_order + 1
            : ((int) $menu->items()->whereNull('parent_id')->max('sort_order')) + 1;

        if ($existing !== null) {
            if ($existing->sort_order !== $sortOrder) {
                $existing->update(['sort_order' => $sortOrder]);
            }

            return;
        }

        MenuItem::query()->create([
            'menu_id' => $menu->id,
            'type' => MenuItem::TYPE_CUSTOM_LINK,
            'label' => 'Ostalo',
            'url' => '/kategorije',
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array{names: array<int, string>, slug_ends: array<int, string>, slug_paths: array<int, string>, label?: string|null}  $priority
     */
    private function findPriorityCategory(array $priority): ?Category
    {
        foreach ($priority['slug_paths'] as $slugPath) {
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

        foreach ($priority['names'] as $name) {
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

        foreach ($priority['slug_ends'] as $slugEnd) {
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
