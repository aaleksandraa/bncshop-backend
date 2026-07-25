<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ProductReadCache
{
    public function rememberList(string $cacheKey, int $ttlSeconds, callable $callback): array
    {
        return $this->tagged(['products', 'products:list'])
            ->remember($cacheKey, $ttlSeconds, $callback);
    }

    public function rememberWeeklyOffer(string $cacheKey, int $ttlSeconds, callable $callback): array
    {
        return $this->tagged(['homepage:weekly-offer'])
            ->remember($cacheKey, $ttlSeconds, $callback);
    }

    /**
     * @return array<string, mixed>
     */
    public function rememberProduct(string $slug, int $ttlSeconds, callable $callback): array
    {
        return $this->tagged(['products', "product:{$slug}"])
            ->remember("product:slug:{$slug}", $ttlSeconds, $callback);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rememberFilters(int $categoryId, int $ttlSeconds, callable $callback): array
    {
        return $this->tagged(['products', "category:{$categoryId}", 'filters'])
            ->remember("filters:cat:v2:{$categoryId}", $ttlSeconds, $callback);
    }

    public function rememberCategorySlug(string $slug, int $ttlSeconds, callable $callback): ?Category
    {
        return $this->tagged(['categories'])
            ->remember("category:slug:{$slug}", $ttlSeconds, $callback);
    }

    /**
     * @return Collection<int, Category>
     */
    public function rememberCategoryTree(int $ttlSeconds, callable $callback): Collection
    {
        return $this->tagged(['categories'])
            ->remember('categories:tree', $ttlSeconds, $callback);
    }

    /**
     * @return Collection<int, Category>
     */
    public function rememberCategoryNav(int $ttlSeconds, callable $callback): Collection
    {
        return $this->tagged(['categories'])
            ->remember('categories:nav', $ttlSeconds, $callback);
    }

    /**
     * @return array<string, mixed>
     */
    public function rememberPublicSettings(int $ttlSeconds, callable $callback): array
    {
        return $this->tagged(['settings'])
            ->remember('settings:public:v2', $ttlSeconds, $callback);
    }

    /**
     * @return array<string, mixed>
     */
    public function rememberLayoutShell(int $ttlSeconds, callable $callback): array
    {
        return $this->tagged(['layout', 'settings', 'menus', 'categories'])
            ->remember('layout:shell:v2', $ttlSeconds, $callback);
    }

    /**
     * @return array<string, mixed>
     */
    public function rememberMenu(string $slug, int $ttlSeconds, callable $callback): array
    {
        return $this->tagged(['menus', "menu:{$slug}"])
            ->remember("menu:slug:{$slug}", $ttlSeconds, $callback);
    }

    /**
     * @return array<string, mixed>
     */
    public function rememberPage(string $slug, int $ttlSeconds, callable $callback): array
    {
        return $this->tagged(['cms', "page:{$slug}"])
            ->remember("page:slug:{$slug}", $ttlSeconds, $callback);
    }

    /**
     * @return array<string, mixed>
     */
    public function rememberBlogPost(string $slug, int $ttlSeconds, callable $callback): array
    {
        return $this->tagged(['blog', "blog:{$slug}"])
            ->remember("blog:slug:{$slug}", $ttlSeconds, $callback);
    }

    /**
     * @return array<string, mixed>
     */
    public function rememberBlogList(string $cacheKey, int $ttlSeconds, callable $callback): array
    {
        return $this->tagged(['blog', 'blog:list'])
            ->remember("blog:list:{$cacheKey}", $ttlSeconds, $callback);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rememberManufacturers(string $cacheKey, int $ttlSeconds, callable $callback): array
    {
        return $this->tagged(['manufacturers'])
            ->remember("manufacturers:{$cacheKey}", $ttlSeconds, $callback);
    }

    public function forgetProduct(Product $product): void
    {
        if ($this->supportsTags()) {
            Cache::tags(["product:{$product->slug}"])->flush();
            $this->flushListAndFilters($product->category_id);

            return;
        }

        Cache::forget("product:slug:{$product->slug}");
    }

    public function forgetPage(string $slug): void
    {
        if ($this->supportsTags()) {
            Cache::tags(["page:{$slug}"])->flush();

            return;
        }

        Cache::forget("page:slug:{$slug}");
    }

    public function forgetBlogPost(string $slug): void
    {
        if ($this->supportsTags()) {
            Cache::tags(["blog:{$slug}"])->flush();

            return;
        }

        Cache::forget("blog:slug:{$slug}");
    }

    public function flushBlog(): void
    {
        if (! $this->supportsTags()) {
            return;
        }

        Cache::tags(['blog'])->flush();
    }

    public function flushListAndFilters(?int $categoryId = null): void
    {
        if (! $this->supportsTags()) {
            return;
        }

        Cache::tags(['products:list'])->flush();

        if ($categoryId) {
            Cache::tags(["category:{$categoryId}", 'filters'])->flush();
        }
    }

    public function flushCategories(): void
    {
        if (! $this->supportsTags()) {
            return;
        }

        Cache::tags(['categories'])->flush();
    }

    public function flushSettings(): void
    {
        if (! $this->supportsTags()) {
            return;
        }

        Cache::tags(['settings'])->flush();
    }

    public function flushLayout(): void
    {
        if (! $this->supportsTags()) {
            return;
        }

        Cache::tags(['layout'])->flush();
    }

    public function flushMenus(): void
    {
        if (! $this->supportsTags()) {
            return;
        }

        Cache::tags(['menus'])->flush();
    }

    public function flushCms(): void
    {
        if (! $this->supportsTags()) {
            return;
        }

        Cache::tags(['cms'])->flush();
    }

    public function flushManufacturers(): void
    {
        if (! $this->supportsTags()) {
            return;
        }

        Cache::tags(['manufacturers'])->flush();
    }

    public function rememberManufacturerBySlug(string $slug, int $ttlSeconds, callable $callback): ?Manufacturer
    {
        return $this->tagged(['manufacturers', "manufacturer:{$slug}"])
            ->remember("manufacturer:slug:{$slug}", $ttlSeconds, $callback);
    }

    public function flushProducts(): void
    {
        if (! $this->supportsTags()) {
            return;
        }

        Cache::tags(['products'])->flush();
    }

    public function flushWeeklyOffer(): void
    {
        if (! $this->supportsTags()) {
            return;
        }

        Cache::tags(['homepage:weekly-offer'])->flush();
    }

    public function flushAll(): void
    {
        if (! $this->supportsTags()) {
            return;
        }

        Cache::tags(['products'])->flush();
        Cache::tags(['categories'])->flush();
    }

    public function supportsTags(): bool
    {
        return method_exists(Cache::getStore(), 'tags');
    }

    /**
     * @param  array<int, string>  $tags
     */
    private function tagged(array $tags): \Illuminate\Contracts\Cache\Repository
    {
        if ($this->supportsTags()) {
            return Cache::tags($tags);
        }

        return Cache::store();
    }
}
