<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\Catalog\ProductReadCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProductReadCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_forget_product_works_without_throwing_on_array_cache(): void
    {
        $product = Product::query()->create([
            'external_product_id' => 'cache-1',
            'name' => 'Cached',
            'slug' => 'cached-product',
            'status' => 'active',
            'is_public' => true,
            'regular_price' => 10,
            'display_price' => 10,
        ]);

        Cache::put('product:slug:cached-product', ['slug' => 'cached-product'], 60);

        app(ProductReadCache::class)->forgetProduct($product);

        $this->assertTrue(true);
    }
}
