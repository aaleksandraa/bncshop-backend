<?php

namespace App\Services\Homepage;

use App\Http\Resources\CategoryTreeResource;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;

class HomepageCategoryChips
{
    public function __construct(
        private readonly HomepageSettings $homepageSettings,
    ) {}

    /**
     * @return array{config: array<string, mixed>, categories: array<int, array<string, mixed>>}
     */
    public function payload(): array
    {
        $config = $this->homepageSettings->categoryChips();

        if (! ($config['enabled'] ?? false)) {
            return [
                'config' => $config,
                'categories' => [],
            ];
        }

        $limit = max(1, min(12, (int) ($config['category_limit'] ?? 6)));
        $categoryIds = array_slice(
            $this->homepageSettings->resolvedCategoryChipIds($config),
            0,
            $limit,
        );

        if ($categoryIds === []) {
            return [
                'config' => $config,
                'categories' => [],
            ];
        }

        $cacheKey = 'homepage:category-chips:'.md5(implode(',', $categoryIds));

        $categories = Cache::remember($cacheKey, 300, fn (): array => $this->loadCategories($categoryIds));

        return [
            'config' => $config,
            'categories' => $categories,
        ];
    }

    /**
     * @param  array<int, int>  $categoryIds
     * @return array<int, array<string, mixed>>
     */
    private function loadCategories(array $categoryIds): array
    {
        $categories = Category::query()
            ->active()
            ->whereIn('id', $categoryIds)
            ->get()
            ->keyBy('id');

        return collect($categoryIds)
            ->map(fn (int $id): ?Category => $categories->get($id))
            ->filter()
            ->values()
            ->map(fn (Category $category): array => (new CategoryTreeResource($category))->resolve())
            ->all();
    }
}
