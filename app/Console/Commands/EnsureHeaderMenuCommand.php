<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Services\Menu\HeaderMenuSyncService;
use Illuminate\Console\Command;

class EnsureHeaderMenuCommand extends Command
{
    protected $signature = 'bnc:ensure-header-menu';

    protected $description = 'Ensure header menu has main category items and Ostalo (/kategorije)';

    /**
     * @var array<int, array{names: array<int, string>, slug_ends: array<int, string>, slug_paths: array<int, string>, label?: string|null}>
     */
    private const HEADER_CATEGORY_PRIORITY = [
        ['names' => ['Računari', 'Racunari'], 'slug_ends' => ['racunari'], 'slug_paths' => ['it-oprema/racunari', 'racunari'], 'label' => null],
        ['names' => ['Laptopi'], 'slug_ends' => ['laptopi'], 'slug_paths' => ['it-oprema/laptopi', 'laptopi'], 'label' => null],
        ['names' => ['Monitori'], 'slug_ends' => ['monitori'], 'slug_paths' => ['it-oprema/periferija/monitori', 'it-oprema/monitori', 'monitori'], 'label' => null],
        ['names' => ['Print', 'Printeri', 'Print i kancelarija'], 'slug_ends' => ['print-kancelarija', 'printeri', 'print'], 'slug_paths' => ['print-kancelarija', 'print-kancelarija/printeri', 'printeri'], 'label' => 'Print'],
        ['names' => ['IT tehnika', 'IT oprema'], 'slug_ends' => ['it-oprema'], 'slug_paths' => ['it-oprema'], 'label' => 'IT tehnika'],
        ['names' => ['Klime', 'Klima', 'Klima i grijanje'], 'slug_ends' => ['klime', 'klima', 'klima-grijanje'], 'slug_paths' => ['klima-grijanje', 'klime', 'klima'], 'label' => null],
        ['names' => ['Televizori', 'TV', 'TV, audio i video'], 'slug_ends' => ['televizori', 'tv', 'tv-audio-video'], 'slug_paths' => ['tv-audio-video', 'televizori', 'tv/televizori', 'tv'], 'label' => null],
        ['names' => ['Softver i licence', 'Softver', 'Licence'], 'slug_ends' => ['softver', 'softver-licence', 'softver-i-licence'], 'slug_paths' => ['softver-licence', 'softver-i-licence', 'softver-i-licenci', 'it-oprema/softver-i-licence', 'softver/licence', 'softver'], 'label' => 'Softver i licence'],
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

        $syncService = app(HeaderMenuSyncService::class);
        $sortOrder = (int) $menu->items()->whereNull('parent_id')->max('sort_order');
        $usedCategoryIds = [];
        $lastCategoryItem = null;
        $added = 0;

        foreach (self::HEADER_CATEGORY_PRIORITY as $priority) {
            $category = $this->findPriorityCategory($priority);

            if ($category === null || in_array($category->id, $usedCategoryIds, true)) {
                $this->warn('Skipped category priority: '.($priority['label'] ?? $priority['names'][0] ?? 'unknown'));

                continue;
            }

            $existing = $menu->items()
                ->whereNull('parent_id')
                ->where('type', MenuItem::TYPE_CATEGORY)
                ->where('category_id', $category->id)
                ->first();

            if ($existing === null) {
                $existing = $menu->items()
                    ->whereNull('parent_id')
                    ->where('type', MenuItem::TYPE_CATEGORY)
                    ->where(function ($query) use ($priority): void {
                        foreach ($priority['names'] as $name) {
                            $query->orWhere('label', $name);
                        }
                        if (! empty($priority['label'])) {
                            $query->orWhere('label', $priority['label']);
                        }
                    })
                    ->first();
            }

            if ($existing !== null) {
                $existing->update([
                    'category_id' => $category->id,
                    'type' => MenuItem::TYPE_CATEGORY,
                    'label' => $priority['label'] ?? $existing->label,
                    'is_active' => true,
                ]);
                $item = $existing->fresh();
            } else {
                $sortOrder++;
                $item = MenuItem::query()->create([
                    'menu_id' => $menu->id,
                    'type' => MenuItem::TYPE_CATEGORY,
                    'category_id' => $category->id,
                    'label' => $priority['label'],
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                ]);
                $added++;
            }

            $usedCategoryIds[] = $category->id;
            $syncService->syncCategoryChildren($menu, $item, $category->id, 2);
            $lastCategoryItem = $item;
            $this->line('OK: '.($priority['label'] ?? $category->name).' → '.$category->full_slug);
        }

        $this->ensureOstaloLink($menu, $lastCategoryItem);
        $this->info("Header menu updated ({$added} new category item(s) + Ostalo).");

        return self::SUCCESS;
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
