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

    public function test_stock_update_without_attributes_does_not_wipe_weight_specs(): void
    {
        $importer = app(ProductImporter::class);

        $importer->upsertOne([
            'productId' => '44444444-4444-4444-4444-444444444444',
            'name' => 'ASRock board',
            'slug' => 'asrock-board',
            'isPublic' => true,
            'stock' => 2,
            'price' => 120,
            'attributes' => [[
                'attributeId' => 'aaaa1111-1111-1111-1111-111111111111',
                'attributeName' => 'Bruto težina',
                'value' => '0.5 kg',
            ]],
        ]);

        $importer->upsertOne([
            'productId' => '44444444-4444-4444-4444-444444444444',
            'name' => 'ASRock board',
            'slug' => 'asrock-board',
            'isPublic' => true,
            'stock' => 3,
            'price' => 120,
        ]);

        $product = Product::query()->where('external_product_id', '44444444-4444-4444-4444-444444444444')->first();
        $this->assertNotNull($product);
        $this->assertSame('0.5 kg', $product->attributeValues()->first()?->raw_value);
    }

    public function test_nested_measurement_attribute_is_stored_as_value_plus_unit(): void
    {
        app(ProductImporter::class)->upsertOne([
            'productId' => '55555555-5555-5555-5555-555555555555',
            'name' => 'SSD',
            'slug' => 'ssd-weight',
            'isPublic' => true,
            'stock' => 1,
            'price' => 50,
            'attributes' => [[
                'attributeId' => 'bbbb2222-2222-2222-2222-222222222222',
                'attributeName' => 'Težina',
                'value' => ['value' => 184, 'unit' => 'g'],
            ]],
        ]);

        $product = Product::query()->where('external_product_id', '55555555-5555-5555-5555-555555555555')->first();
        $this->assertSame('184 g', $product?->attributeValues()->first()?->raw_value);
    }
}
