<?php

namespace Tests\Unit;

use App\Models\ApiSource;
use App\Models\Product;
use App\Services\Sync\ProductImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImporterUpsertResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_upsert_one_returns_inserted_for_new_product(): void
    {
        $source = ApiSource::query()->create([
            'name' => 'A1',
            'target_system_code' => 'bnc-shop',
            'base_url' => 'https://example.test',
            'is_active' => true,
        ]);

        $result = app(ProductImporter::class)->upsertOne([
            'productId' => '11111111-1111-1111-1111-111111111111',
            'name' => 'New product',
            'slug' => 'new-product',
            'isPublic' => true,
            'stock' => 5,
            'price' => 100,
        ], $source);

        $this->assertSame('inserted', $result->action);
        $this->assertSame('New product', $result->product->name);
        $this->assertTrue($result->product->is_public);
    }

    public function test_upsert_one_returns_updated_for_existing_product(): void
    {
        Product::query()->create([
            'external_product_id' => '22222222-2222-2222-2222-222222222222',
            'name' => 'Old name',
            'slug' => 'old-name',
            'is_public' => true,
            'status' => 'active',
            'api_stock' => 1,
            'available_stock' => 1,
            'stock_status' => 'in_stock',
        ]);

        $result = app(ProductImporter::class)->upsertOne([
            'productId' => '22222222-2222-2222-2222-222222222222',
            'name' => 'Updated name',
            'slug' => 'updated-name',
            'isPublic' => true,
            'stock' => 10,
            'price' => 150,
        ]);

        $this->assertSame('updated', $result->action);
        $this->assertContains('name', $result->changedFields);
        $this->assertSame('Updated name', $result->product->name);
    }

    public function test_upsert_one_returns_deactivated_when_is_public_becomes_false(): void
    {
        Product::query()->create([
            'external_product_id' => '33333333-3333-3333-3333-333333333333',
            'name' => 'Public product',
            'slug' => 'public-product',
            'is_public' => true,
            'status' => 'active',
            'api_stock' => 1,
            'available_stock' => 1,
            'stock_status' => 'in_stock',
        ]);

        $result = app(ProductImporter::class)->upsertOne([
            'productId' => '33333333-3333-3333-3333-333333333333',
            'name' => 'Public product',
            'slug' => 'public-product',
            'isPublic' => false,
            'stock' => 0,
            'price' => 150,
        ]);

        $this->assertSame('deactivated', $result->action);
        $this->assertFalse($result->product->is_public);
    }
}
