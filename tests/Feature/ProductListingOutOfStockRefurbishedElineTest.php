<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\CatalogListingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductListingOutOfStockRefurbishedElineTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_endpoint_hides_out_of_stock_refurbished_and_eline_by_default(): void
    {
        $category = Category::factory()->create();

        $visible = Product::factory()->create([
            'category_id' => $category->id,
            'import_source' => 'a1',
            'is_refurbished' => false,
            'available_stock' => 5,
            'is_public' => true,
            'status' => 'active',
            'name' => 'A1 laptop na stanju',
        ]);

        Product::factory()->create([
            'category_id' => $category->id,
            'import_source' => 'eline',
            'eline_sifra' => '111',
            'is_refurbished' => true,
            'available_stock' => 0,
            'is_public' => true,
            'status' => 'active',
            'name' => 'Refurbished eLine bez zaliha',
        ]);

        Product::factory()->create([
            'category_id' => $category->id,
            'import_source' => 'eline',
            'eline_sifra' => '222',
            'is_refurbished' => false,
            'available_stock' => 0,
            'is_public' => true,
            'status' => 'active',
            'name' => 'Novi eLine bez zaliha',
        ]);

        $response = $this->getJson('/api/v1/products?category='.$category->full_slug);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $visible->id);
    }

    public function test_products_endpoint_can_show_out_of_stock_refurbished_and_eline_when_disabled(): void
    {
        app(CatalogListingSettings::class)->save([
            'hide_out_of_stock_refurbished_eline' => false,
        ]);

        $category = Category::factory()->create();

        Product::factory()->create([
            'category_id' => $category->id,
            'import_source' => 'eline',
            'eline_sifra' => '333',
            'is_refurbished' => true,
            'available_stock' => 0,
            'is_public' => true,
            'status' => 'active',
            'name' => 'Refurbished eLine bez zaliha',
        ]);

        $response = $this->getJson('/api/v1/products?category='.$category->full_slug);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Refurbished eLine bez zaliha');
    }
}
