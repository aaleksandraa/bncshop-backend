<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Catalog\ProductReadCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProductCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_forget_product_clears_cached_payload(): void
    {
        $product = Product::factory()->create([
            'slug' => 'cache-test-product',
            'is_public' => true,
            'status' => 'active',
        ]);

        $cache = app(ProductReadCache::class);
        $cache->rememberProduct('cache-test-product', 900, fn (): array => [
            'slug' => 'cache-test-product',
            'name' => 'Cached',
        ]);

        $cache->forgetProduct($product);

        $this->assertNull(Cache::get('product:slug:cache-test-product'));
    }

    public function test_product_read_cache_remembers_resolved_list_payload(): void
    {
        Product::factory()->count(2)->create([
            'is_public' => true,
            'status' => 'active',
        ]);

        $cache = app(ProductReadCache::class);

        $first = $cache->rememberList('test:list', 60, fn (): array => [
            'items' => [['id' => 1, 'name' => 'A']],
            'total' => 1,
            'per_page' => 24,
            'current_page' => 1,
            'last_page' => 1,
        ]);

        $second = $cache->rememberList('test:list', 60, fn (): array => [
            'items' => [['id' => 2, 'name' => 'Should not run']],
            'total' => 1,
            'per_page' => 24,
            'current_page' => 1,
            'last_page' => 1,
        ]);

        $this->assertSame('A', $first['items'][0]['name']);
        $this->assertSame('A', $second['items'][0]['name']);
    }
}
