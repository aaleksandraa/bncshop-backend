<?php

namespace Tests\Unit\Ananas;

use App\Models\AnanasCategoryMapping;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductImage;
use App\Services\Ananas\AnanasEligibilityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AnanasEligibilityPolicyTest extends TestCase
{
    use RefreshDatabase;

    private AnanasEligibilityPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bnc.ananas_vat_rate' => 0]);

        $this->policy = app(AnanasEligibilityPolicy::class);
    }

    public function test_refurbished_product_is_not_eligible(): void
    {
        $product = Product::factory()->create([
            'is_refurbished' => true,
            'is_set' => false,
        ]);

        $result = $this->policy->evaluate($product);

        $this->assertFalse($result->eligible);
        $this->assertSame(AnanasEligibilityPolicy::REFURBISHED_OR_USED, $result->reasonCode);
    }

    public function test_set_product_is_not_eligible(): void
    {
        $product = Product::factory()->create([
            'is_refurbished' => false,
            'is_set' => true,
        ]);

        $result = $this->policy->evaluate($product);

        $this->assertFalse($result->eligible);
        $this->assertSame(AnanasEligibilityPolicy::SET_PRODUCT, $result->reasonCode);
    }

    public function test_refurbished_is_checked_before_set(): void
    {
        $product = Product::factory()->create([
            'is_refurbished' => true,
            'is_set' => true,
        ]);

        $this->assertSame(
            AnanasEligibilityPolicy::REFURBISHED_OR_USED,
            $this->policy->evaluate($product)->reasonCode,
        );
    }

    public function test_eline_new_passes_hard_exclusions(): void
    {
        $product = Product::factory()->create([
            'is_refurbished' => false,
            'is_set' => false,
            'import_source' => 'eline',
            'is_new' => true,
        ]);

        $this->assertTrue($this->policy->evaluateHardExclusions($product)->eligible);
    }

    public function test_fully_prepared_product_is_eligible_for_export(): void
    {
        $product = $this->createExportReadyProduct();

        $this->assertTrue($this->policy->evaluate($product)->eligible);
    }

    public function test_missing_ean_is_not_eligible(): void
    {
        $product = $this->createExportReadyProduct(['barcode' => null]);

        $this->assertSame(
            AnanasEligibilityPolicy::MISSING_EAN,
            $this->policy->evaluate($product)->reasonCode,
        );
    }

    public function test_invalid_ean_is_not_eligible(): void
    {
        $product = $this->createExportReadyProduct(['barcode' => 'ABC123']);

        $this->assertSame(
            AnanasEligibilityPolicy::INVALID_EAN,
            $this->policy->evaluate($product)->reasonCode,
        );
    }

    public function test_missing_weight_is_not_eligible(): void
    {
        $product = $this->createExportReadyProduct([], attachWeight: false);

        $this->assertSame(
            AnanasEligibilityPolicy::MISSING_WEIGHT,
            $this->policy->evaluate($product)->reasonCode,
        );
    }

    public function test_vat_unresolved_when_rate_is_invalid(): void
    {
        config(['bnc.ananas_vat_rate' => 99]);

        $product = $this->createExportReadyProduct();

        $this->assertSame(
            AnanasEligibilityPolicy::VAT_UNRESOLVED,
            $this->policy->evaluate($product)->reasonCode,
        );
    }

    public function test_assert_can_export_throws_for_refurbished_product(): void
    {
        $product = Product::factory()->create(['is_refurbished' => true]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(AnanasEligibilityPolicy::REFURBISHED_OR_USED);

        $this->policy->assertCanExport($product);
    }

    public function test_assert_can_export_allows_eligible_product(): void
    {
        $this->policy->assertCanExport($this->createExportReadyProduct());

        $this->assertTrue(true);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createExportReadyProduct(array $overrides = [], bool $attachWeight = true): Product
    {
        $category = Category::factory()->create();

        AnanasCategoryMapping::query()->create([
            'category_id' => $category->id,
            'ananas_product_type' => 'ITShop',
            'is_enabled' => true,
        ]);

        $product = Product::factory()->create(array_merge([
            'category_id' => $category->id,
            'barcode' => '1234567890123',
            'regular_price' => 120,
            'display_price' => 120,
            'available_stock' => 2,
            'is_refurbished' => false,
            'is_set' => false,
        ], $overrides));

        ProductImage::query()->create([
            'product_id' => $product->id,
            'image_url' => 'https://cdn.example.test/product.jpg',
            'public_url' => 'https://cdn.example.test/product.jpg',
            'status' => 'active',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        if ($attachWeight) {
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
                'raw_value' => '1 kg',
                'normalized_value' => '1',
                'normalized_type' => 'number',
            ]);
        }

        return $product->fresh(['images', 'attributeValues.attributeDefinition']);
    }
}
