<?php

namespace Tests\Unit;

use App\Models\Manufacturer;
use App\Models\Product;
use App\Services\Catalog\ProductListingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProductListingSearchFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_fallback_returns_products_matching_query(): void
    {
        $samsung = Manufacturer::query()->create([
            'external_manufacturer_id' => (string) fake()->uuid(),
            'name' => 'Samsung',
            'slug' => 'samsung',
        ]);

        $hp = Manufacturer::query()->create([
            'external_manufacturer_id' => (string) fake()->uuid(),
            'name' => 'HP',
            'slug' => 'hp',
        ]);

        Product::query()->create([
            'external_product_id' => (string) fake()->uuid(),
            'manufacturer_id' => $samsung->id,
            'name' => 'Samsung Galaxy telefon',
            'slug' => 'samsung-galaxy-telefon',
            'sku' => 'SAM-001',
            'is_public' => true,
            'status' => 'active',
            'display_price' => 999,
            'regular_price' => 999,
            'available_stock' => 5,
            'stock_status' => 'in_stock',
        ]);

        Product::query()->create([
            'external_product_id' => (string) fake()->uuid(),
            'manufacturer_id' => $hp->id,
            'name' => 'HP laptop',
            'slug' => 'hp-laptop',
            'sku' => 'HP-001',
            'is_public' => true,
            'status' => 'active',
            'display_price' => 1499,
            'regular_price' => 1499,
            'available_stock' => 2,
            'stock_status' => 'in_stock',
        ]);

        $request = Request::create('/api/v1/search', 'GET', ['q' => 'samsung']);
        $payload = app(ProductListingService::class)->listViaDatabase($request, 24);

        $this->assertSame(1, $payload['total']);
        $this->assertSame('Samsung Galaxy telefon', $payload['items'][0]['name']);
    }
}
