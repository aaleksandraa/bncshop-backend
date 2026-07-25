<?php

namespace App\Services\B2b;

use App\Models\B2bCategory;
use Illuminate\Support\Facades\Cache;

class B2bReadCache
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function rememberCategories(int $ttlSeconds, callable $callback): array
    {
        return Cache::remember('b2b:categories:active', $ttlSeconds, $callback);
    }

    public function flushCategories(): void
    {
        Cache::forget('b2b:categories:active');
    }

    /**
     * @return array<string, mixed>
     */
    public function rememberCategoryFilters(string $slug, int $ttlSeconds, callable $callback): array
    {
        return Cache::remember("b2b:category-filters:{$slug}", $ttlSeconds, $callback);
    }

    public function flushCategoryFilters(?string $slug = null): void
    {
        if ($slug !== null) {
            Cache::forget("b2b:category-filters:{$slug}");

            return;
        }

        foreach (B2bCategory::query()->pluck('slug') as $categorySlug) {
            Cache::forget("b2b:category-filters:{$categorySlug}");
        }
    }
}
