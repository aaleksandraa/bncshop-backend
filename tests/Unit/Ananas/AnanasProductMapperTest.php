<?php

namespace Tests\Unit\Ananas;

use App\Models\AnanasCategoryMapping;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductImage;
use App\Services\Ananas\AnanasEligibilityPolicy;
use App\Services\Ananas\AnanasProductMapper;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AnanasProductMapperTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bnc.ananas_vat_rate' => 20]);
    }

    public function test_mapper_uses_price_calculator_regular_price(): void
    {
        [$product, $mapping] = $this->createExportReadyProduct(regularPrice: 199.99);

        $calculator = $this->createMock(PriceCalculator::class);
        $calculator->method('calculate')->willReturn(new \App\Services\Pricing\PriceResult(
            displayPrice: 179.99,
            regularPrice: 199.99,
            onSale: true,
        ));

        $mapper = new AnanasProductMapper(
            app(AnanasEligibilityPolicy::class),
            app(\App\Services\Ananas\AnanasPackageWeightResolver::class),
            $calculator,
        );

        $payload = $mapper->map($product, $mapping);

        $this->assertSame(199.99, $payload['basePrice']);
        $this->assertSame(20, $payload['vat']);
        $this->assertSame('KG', $payload['packageWeightUnit']);
        $this->assertSame('ITShop', $payload['productType']);
    }

    public function test_mapper_throws_when_product_not_eligible(): void
    {
        $product = Product::factory()->create([
            'is_refurbished' => true,
        ]);

        $mapping = AnanasCategoryMapping::query()->create([
            'category_id' => $product->category_id,
            'ananas_product_type' => 'ITShop',
            'is_enabled' => true,
        ]);

        $this->expectException(RuntimeException::class);

        app(AnanasProductMapper::class)->map($product, $mapping);
    }

    /**
     * @return array{0: Product, 1: AnanasCategoryMapping}
     */
    private function createExportReadyProduct(float $regularPrice = 150.0): array
    {
        $category = Category::factory()->create();

        $mapping = AnanasCategoryMapping::query()->create([
            'category_id' => $category->id,
            'ananas_product_type' => 'ITShop',
            'ananas_category' => 'Test category',
            'is_enabled' => true,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'barcode' => '1234567890123',
            'regular_price' => $regularPrice,
            'display_price' => $regularPrice,
            'available_stock' => 3,
            'is_refurbished' => false,
            'is_set' => false,
        ]);

        ProductImage::query()->create([
            'product_id' => $product->id,
            'image_url' => 'https://cdn.example.test/product.jpg',
            'public_url' => 'https://cdn.example.test/product.jpg',
            'status' => 'active',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $definition = AttributeDefinition::query()->create([
            'external_attribute_id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Težina',
            'display_name' => 'Težina',
            'internal_type' => 'text',
            'is_public' => true,
        ]);

        ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $definition->id,
            'attribute_name_snapshot' => 'Težina',
            'raw_value' => '1.2 kg',
            'normalized_value' => '1.2',
            'normalized_type' => 'number',
        ]);

        return [$product->fresh(['images', 'attributeValues.attributeDefinition', 'manufacturer']), $mapping];
    }
}
