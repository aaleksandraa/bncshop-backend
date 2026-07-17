<?php

namespace Tests\Unit;

use App\Http\Resources\ProductCardResource;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCardResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_resource_exposes_listing_fields_only(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'HP', 'slug' => 'hp']);
        $category = Category::factory()->create(['name' => 'Skeneri', 'full_slug' => 'skeneri']);

        $product = Product::query()->create([
            'external_product_id' => 'card-1',
            'manufacturer_id' => $manufacturer->id,
            'category_id' => $category->id,
            'name' => 'Test product',
            'slug' => 'test-product',
            'short_description' => 'Short',
            'description' => 'Very long description that should not appear in card payload',
            'status' => 'active',
            'is_public' => true,
            'regular_price' => 100,
            'display_price' => 80,
            'on_sale' => true,
            'available_stock' => 5,
        ]);

        $image = ProductImage::query()->create([
            'product_id' => $product->id,
            'image_url' => 'https://example.com/image.jpg',
            'public_url' => 'https://example.com/public.jpg',
            'local_path' => 'products/test/image.jpg',
            'status' => 'active',
        ]);

        $product->update(['default_image_id' => $image->id]);
        $product->load(['manufacturer', 'category', 'defaultImage']);

        $payload = (new ProductCardResource($product))->resolve();

        $this->assertSame('Test product', $payload['name']);
        $this->assertSame('HP', $payload['manufacturer']['name']);
        $this->assertTrue($payload['on_sale']);
        $this->assertArrayHasKey('default_image', $payload);
        $this->assertArrayNotHasKey('description', $payload);
        $this->assertArrayNotHasKey('images', $payload);
    }
}
