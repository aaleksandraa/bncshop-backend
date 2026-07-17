<?php

namespace Tests\Unit;

use App\Models\AttributeCategoryMapping;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Services\Search\FilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilterServiceSubcategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_filters_include_products_from_subcategories(): void
    {
        $parent = Category::factory()->create(['full_slug' => 'telefoni']);
        $child = Category::factory()->create([
            'full_slug' => 'telefoni/smartphone',
            'parent_id' => $parent->id,
        ]);

        $definition = AttributeDefinition::query()->create([
            'external_attribute_id' => 'attr-brand',
            'name' => 'Brend',
            'slug' => 'brend',
            'internal_type' => 'text',
            'is_filter' => true,
        ]);

        AttributeCategoryMapping::query()->create([
            'category_id' => $parent->id,
            'attribute_definition_id' => $definition->id,
            'is_filter_enabled' => true,
            'sort_order' => 0,
        ]);

        $product = Product::factory()->create([
            'category_id' => $child->id,
            'is_public' => true,
            'status' => 'active',
        ]);

        ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $definition->id,
            'attribute_name_snapshot' => 'Brend',
            'normalized_value' => 'Samsung',
            'raw_value' => 'Samsung',
        ]);

        $filters = app(FilterService::class)->getAvailableFilters($parent);

        $this->assertCount(1, $filters);
        $this->assertSame('Samsung', $filters[0]['values'][0]['value']);
    }
}
