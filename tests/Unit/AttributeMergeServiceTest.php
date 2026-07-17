<?php

namespace Tests\Unit;

use App\Models\AttributeCategoryMapping;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Services\Catalog\AttributeDisplayService;
use App\Services\Catalog\AttributeMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributeMergeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_merge_moves_values_and_marks_source_as_alias(): void
    {
        $product = Product::query()->create([
            'external_product_id' => 'prod-merge-1',
            'name' => 'Laptop',
            'slug' => 'laptop',
            'status' => 'active',
            'is_public' => true,
        ]);

        $canonical = AttributeDefinition::query()->create([
            'external_attribute_id' => 'ram-main',
            'name' => 'RAM',
            'display_name' => 'RAM memorija',
            'internal_type' => 'text',
            'is_public' => true,
            'is_filter' => true,
        ]);

        $source = AttributeDefinition::query()->create([
            'external_attribute_id' => 'ram-alt',
            'name' => 'Memorija RAM',
            'internal_type' => 'text',
            'is_public' => true,
            'is_filter' => true,
        ]);

        ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $source->id,
            'attribute_name_snapshot' => 'Memorija RAM',
            'raw_value' => '16 GB',
            'normalized_value' => '16 GB',
            'normalized_type' => 'text',
        ]);

        $result = app(AttributeMergeService::class)->merge($canonical, $source);

        $source->refresh();
        $this->assertSame($canonical->id, $source->canonical_attribute_definition_id);
        $this->assertFalse($source->is_public);
        $this->assertSame(1, $result['products']);

        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $product->id,
            'attribute_definition_id' => $canonical->id,
            'normalized_value' => '16 GB',
        ]);

        $this->assertDatabaseMissing('product_attribute_values', [
            'product_id' => $product->id,
            'attribute_definition_id' => $source->id,
        ]);
    }

    public function test_display_service_deduplicates_merged_attributes_on_product(): void
    {
        $product = Product::query()->create([
            'external_product_id' => 'prod-merge-2',
            'name' => 'Monitor',
            'slug' => 'monitor',
            'status' => 'active',
            'is_public' => true,
        ]);

        $canonical = AttributeDefinition::query()->create([
            'external_attribute_id' => 'size-main',
            'name' => 'Veličina ekrana',
            'display_name' => 'Dijagonala',
            'internal_type' => 'text',
            'is_public' => true,
        ]);

        $alias = AttributeDefinition::query()->create([
            'external_attribute_id' => 'size-alt',
            'name' => 'Screen size',
            'internal_type' => 'text',
            'is_public' => false,
            'canonical_attribute_definition_id' => $canonical->id,
        ]);

        $canonicalValue = ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $canonical->id,
            'attribute_name_snapshot' => 'Veličina ekrana',
            'raw_value' => '27"',
            'normalized_value' => '27"',
            'normalized_type' => 'text',
        ]);
        $canonicalValue->setRelation('attributeDefinition', $canonical);

        $aliasValue = ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $alias->id,
            'attribute_name_snapshot' => 'Screen size',
            'raw_value' => '27 in',
            'normalized_value' => '27 in',
            'normalized_type' => 'text',
        ]);
        $aliasValue->setRelation('attributeDefinition', $alias);

        $formatted = app(AttributeDisplayService::class)->formatManyForProduct(collect([
            $canonicalValue,
            $aliasValue,
        ]));

        $this->assertCount(1, $formatted);
        $this->assertSame($canonical->id, $formatted[0]['attribute_definition_id']);
        $this->assertSame('Dijagonala', $formatted[0]['display_name']);
    }

    public function test_merge_consolidates_category_mappings(): void
    {
        $category = Category::query()->create([
            'external_category_id' => 'cat-merge',
            'name' => 'Laptopi',
            'full_slug' => 'laptopi',
            'status' => 'active',
        ]);

        $canonical = AttributeDefinition::query()->create([
            'external_attribute_id' => 'cpu-main',
            'name' => 'Procesor',
            'internal_type' => 'text',
            'is_public' => true,
            'is_filter' => true,
        ]);

        $source = AttributeDefinition::query()->create([
            'external_attribute_id' => 'cpu-alt',
            'name' => 'CPU',
            'internal_type' => 'text',
            'is_public' => true,
            'is_filter' => true,
        ]);

        AttributeCategoryMapping::query()->create([
            'attribute_definition_id' => $source->id,
            'category_id' => $category->id,
            'external_category_id' => 'cat-merge',
            'category_name' => 'Laptopi',
            'is_filter_enabled' => true,
            'is_public_enabled' => true,
            'sort_order' => 3,
        ]);

        app(AttributeMergeService::class)->merge($canonical, $source);

        $this->assertDatabaseHas('attribute_category_mappings', [
            'attribute_definition_id' => $canonical->id,
            'category_id' => $category->id,
            'is_filter_enabled' => true,
        ]);

        $this->assertDatabaseMissing('attribute_category_mappings', [
            'attribute_definition_id' => $source->id,
            'category_id' => $category->id,
        ]);
    }
}
