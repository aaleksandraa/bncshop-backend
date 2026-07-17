<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductListingRefurbishedFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_endpoint_filters_refurbished_items(): void
    {
        $category = Category::factory()->create();

        Product::factory()->create([
            'category_id' => $category->id,
            'import_source' => 'a1',
            'is_refurbished' => false,
            'is_new' => true,
            'is_public' => true,
            'status' => 'active',
            'name' => 'Novi A1 laptop',
        ]);

        Product::factory()->create([
            'category_id' => $category->id,
            'import_source' => 'eline',
            'eline_sifra' => '555',
            'is_refurbished' => true,
            'is_new' => false,
            'is_public' => true,
            'status' => 'active',
            'name' => 'Refurbished eLine desktop',
        ]);

        $response = $this->getJson('/api/v1/products?is_refurbished=1');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Refurbished eLine desktop');
    }
}
