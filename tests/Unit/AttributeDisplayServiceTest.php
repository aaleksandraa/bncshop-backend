<?php

namespace Tests\Unit;

use App\Models\AttributeCategoryMapping;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Services\Catalog\AttributeDisplayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributeDisplayServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_boolean_values_render_as_da_ne(): void
    {
        $product = Product::query()->create([
            'external_product_id' => 'prod-1',
            'name' => 'Test',
            'slug' => 'test',
            'status' => 'active',
            'is_public' => true,
        ]);

        $definition = AttributeDefinition::query()->create([
            'external_attribute_id' => 'test-bool',
            'name' => 'BACKSIDE_SC',
            'internal_type' => 'boolean',
            'is_public' => true,
        ]);

        $value = ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $definition->id,
            'attribute_name_snapshot' => 'BACKSIDE_SC',
            'raw_value' => 'true',
            'normalized_value' => 'true',
            'normalized_type' => 'boolean',
        ]);

        $value->setRelation('attributeDefinition', $definition);

        $formatted = app(AttributeDisplayService::class)->formatForProduct($value);

        $this->assertSame('Da', $formatted['display_value']);
        $this->assertSame('BACKSIDE_SC', $formatted['display_name']);
    }

    public function test_custom_value_mapping_is_used(): void
    {
        $product = Product::query()->create([
            'external_product_id' => 'prod-2',
            'name' => 'Test 2',
            'slug' => 'test-2',
            'status' => 'active',
            'is_public' => true,
        ]);

        $definition = AttributeDefinition::query()->create([
            'external_attribute_id' => 'test-select',
            'name' => 'PAP_FORMAT',
            'display_name' => 'Format papira',
            'internal_type' => 'select',
            'is_public' => true,
            'value_mappings' => ['A4' => 'A4 format'],
        ]);

        $value = ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $definition->id,
            'attribute_name_snapshot' => 'PAP_FORMAT',
            'raw_value' => 'A4',
            'normalized_value' => 'A4',
            'normalized_type' => 'text',
        ]);

        $value->setRelation('attributeDefinition', $definition);

        $formatted = app(AttributeDisplayService::class)->formatForProduct($value);

        $this->assertSame('Format papira', $formatted['display_name']);
        $this->assertSame('A4 format', $formatted['display_value']);
    }

    public function test_inactive_attribute_is_hidden_on_frontend(): void
    {
        $product = Product::query()->create([
            'external_product_id' => 'prod-3',
            'name' => 'Test 3',
            'slug' => 'test-3',
            'status' => 'active',
            'is_public' => true,
        ]);

        $definition = AttributeDefinition::query()->create([
            'external_attribute_id' => 'hidden-attr',
            'name' => 'HIDDEN',
            'internal_type' => 'text',
            'is_public' => false,
        ]);

        $value = ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $definition->id,
            'attribute_name_snapshot' => 'HIDDEN',
            'raw_value' => 'value',
            'normalized_value' => 'value',
            'normalized_type' => 'text',
        ]);

        $value->setRelation('attributeDefinition', $definition);

        $service = app(AttributeDisplayService::class);

        $this->assertFalse($service->shouldShowOnFrontend($value));
        $this->assertSame([], $service->formatManyForProduct(collect([$value])));
    }

    public function test_category_mapping_sort_order_is_used(): void
    {
        $category = Category::factory()->create();

        $product = Product::query()->create([
            'external_product_id' => 'prod-4',
            'name' => 'Test 4',
            'slug' => 'test-4',
            'status' => 'active',
            'is_public' => true,
            'category_id' => $category->id,
        ]);

        $first = AttributeDefinition::query()->create([
            'external_attribute_id' => 'sort-a',
            'name' => 'A',
            'internal_type' => 'text',
            'is_public' => true,
            'detail_sort_order' => 10,
        ]);

        $second = AttributeDefinition::query()->create([
            'external_attribute_id' => 'sort-b',
            'name' => 'B',
            'internal_type' => 'text',
            'is_public' => true,
            'detail_sort_order' => 1,
        ]);

        AttributeCategoryMapping::query()->create([
            'attribute_definition_id' => $first->id,
            'category_id' => $category->id,
            'sort_order' => 1,
            'is_filter_enabled' => true,
            'is_public_enabled' => true,
        ]);

        AttributeCategoryMapping::query()->create([
            'attribute_definition_id' => $second->id,
            'category_id' => $category->id,
            'sort_order' => 2,
            'is_filter_enabled' => true,
            'is_public_enabled' => true,
        ]);

        $valueA = ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $first->id,
            'attribute_name_snapshot' => 'A',
            'raw_value' => '1',
            'normalized_value' => '1',
            'normalized_type' => 'text',
        ]);

        $valueB = ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $second->id,
            'attribute_name_snapshot' => 'B',
            'raw_value' => '2',
            'normalized_value' => '2',
            'normalized_type' => 'text',
        ]);

        $valueA->setRelation('attributeDefinition', $first->load('categoryMappings'));
        $valueB->setRelation('attributeDefinition', $second->load('categoryMappings'));

        $formatted = app(AttributeDisplayService::class)->formatManyForProduct(
            collect([$valueB, $valueA]),
            $category->id,
        );

        $this->assertSame('A', $formatted[0]['display_name']);
        $this->assertSame('B', $formatted[1]['display_name']);
    }

    public function test_value_mapping_true_false_strings_are_localized(): void
    {
        $product = Product::query()->create([
            'external_product_id' => 'prod-map-bool',
            'name' => 'Test map bool',
            'slug' => 'test-map-bool',
            'status' => 'active',
            'is_public' => true,
        ]);

        $definition = AttributeDefinition::query()->create([
            'external_attribute_id' => 'mapped-bool',
            'name' => 'IPS',
            'internal_type' => 'text',
            'is_public' => true,
            'value_mappings' => ['true' => 'true', 'false' => 'false'],
        ]);

        $value = ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $definition->id,
            'attribute_name_snapshot' => 'IPS',
            'raw_value' => 'true',
            'normalized_value' => 'true',
            'normalized_type' => 'boolean',
        ]);

        $value->setRelation('attributeDefinition', $definition);

        $formatted = app(AttributeDisplayService::class)->formatForProduct($value);

        $this->assertSame('Da', $formatted['display_value']);
    }
}
