<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductIndexPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_list_endpoint_uses_limited_query_count(): void
    {
        $category = Category::factory()->create(['full_slug' => 'laptopi']);

        Product::query()->create([
            'external_product_id' => 'perf-1',
            'category_id' => $category->id,
            'name' => 'Laptop 1',
            'slug' => 'laptop-1',
            'status' => 'active',
            'is_public' => true,
            'regular_price' => 1000,
            'display_price' => 1000,
            'available_stock' => 1,
        ]);

        DB::enableQueryLog();

        $response = $this->getJson('/api/v1/products?category=laptopi&per_page=24');

        $queryCount = count(DB::getQueryLog());

        $response->assertOk();
        $this->assertLessThanOrEqual(6, $queryCount);
        $this->assertArrayHasKey('on_sale', $response->json('data.0'));
        $this->assertArrayNotHasKey('description', $response->json('data.0'));
    }
}
