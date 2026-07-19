<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCategoryOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_options_returns_only_categories_with_refurbished_products(): void
    {
        $refurbishedCategory = Category::factory()->create([
            'full_slug' => 'refurbished/monitori',
        ]);
        $emptyCategory = Category::factory()->create([
            'full_slug' => 'it-oprema/laptopi',
        ]);

        Product::factory()->create([
            'category_id' => $refurbishedCategory->id,
            'import_source' => 'eline',
            'eline_sifra' => '777',
            'is_refurbished' => true,
            'is_public' => true,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'category_id' => $emptyCategory->id,
            'import_source' => 'a1',
            'is_refurbished' => false,
            'is_new' => true,
            'is_public' => true,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/products/category-options?is_refurbished=1');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0', 'refurbished/monitori');
    }
}
