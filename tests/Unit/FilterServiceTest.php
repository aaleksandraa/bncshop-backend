<?php

namespace Tests\Unit;

use App\Models\AttributeCategoryMapping;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Services\Search\FilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FilterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_filters_use_single_aggregation_query(): void
    {
        $category = Category::factory()->create(['full_slug' => 'laptopi']);

        $definitionA = AttributeDefinition::query()->create([
            'external_attribute_id' => 'attr-ram',
            'name' => 'RAM',
            'slug' => 'ram',
            'internal_type' => 'text',
            'is_filter' => true,
        ]);

        $definitionB = AttributeDefinition::query()->create([
            'external_attribute_id' => 'attr-ssd',
            'name' => 'SSD',
            'slug' => 'ssd',
            'internal_type' => 'text',
            'is_filter' => true,
        ]);

        foreach ([$definitionA, $definitionB] as $index => $definition) {
            AttributeCategoryMapping::query()->create([
                'category_id' => $category->id,
                'attribute_definition_id' => $definition->id,
                'is_filter_enabled' => true,
                'sort_order' => $index,
            ]);
        }

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_public' => true,
            'status' => 'active',
        ]);

        ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $definitionA->id,
            'attribute_name_snapshot' => 'RAM',
            'normalized_value' => '16GB',
            'raw_value' => '16GB',
        ]);

        ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $definitionB->id,
            'attribute_name_snapshot' => 'SSD',
            'normalized_value' => '512GB',
            'raw_value' => '512GB',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $filters = app(FilterService::class)->getAvailableFilters($category);

        $queries = collect(DB::getQueryLog())
            ->filter(fn (array $entry): bool => str_contains($entry['query'], 'product_attribute_values'))
            ->count();

        $this->assertCount(2, $filters);
        $this->assertSame(1, $queries, 'Expected one batch aggregation query for filter values');
    }

    public function test_boolean_attribute_filter_is_exposed_as_checkbox_type(): void
    {
        $category = Category::factory()->create(['full_slug' => 'monitori']);

        $definition = AttributeDefinition::query()->create([
            'external_attribute_id' => 'attr-hdmi',
            'name' => 'HDMI',
            'internal_type' => 'boolean',
            'is_filter' => true,
        ]);

        AttributeCategoryMapping::query()->create([
            'category_id' => $category->id,
            'attribute_definition_id' => $definition->id,
            'is_filter_enabled' => true,
            'sort_order' => 0,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_public' => true,
            'status' => 'active',
        ]);

        ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $definition->id,
            'attribute_name_snapshot' => 'HDMI',
            'normalized_value' => 'true',
            'raw_value' => 'true',
            'normalized_type' => 'boolean',
        ]);

        $filters = app(FilterService::class)->getAvailableFilters($category);

        $this->assertCount(1, $filters);
        $this->assertSame('boolean', $filters[0]['type']);
        $this->assertSame([], $filters[0]['values']);
        $this->assertSame(1, $filters[0]['true_count']);
    }

    public function test_select_filter_values_use_da_ne_labels_for_boolean_tokens(): void
    {
        $category = Category::factory()->create(['full_slug' => 'periferija']);

        $definition = AttributeDefinition::query()->create([
            'external_attribute_id' => 'attr-legacy',
            'name' => 'Legacy bool',
            'internal_type' => 'text',
            'is_filter' => true,
        ]);

        AttributeCategoryMapping::query()->create([
            'category_id' => $category->id,
            'attribute_definition_id' => $definition->id,
            'is_filter_enabled' => true,
            'sort_order' => 0,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_public' => true,
            'status' => 'active',
        ]);

        ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $definition->id,
            'attribute_name_snapshot' => 'Legacy bool',
            'normalized_value' => 'true',
            'raw_value' => 'true',
            'normalized_type' => 'boolean',
        ]);

        $filters = app(FilterService::class)->getAvailableFilters($category);

        $this->assertCount(1, $filters);
        $this->assertSame('boolean', $filters[0]['type']);
    }
}
