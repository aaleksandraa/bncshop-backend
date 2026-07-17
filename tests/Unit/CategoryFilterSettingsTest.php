<?php

namespace Tests\Unit;

use App\Models\AttributeCategoryMapping;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Services\Search\FilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryFilterSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_config_respects_category_toggles(): void
    {
        $category = Category::factory()->create([
            'full_slug' => 'laptopi',
            'filter_price_enabled' => false,
            'filter_brand_enabled' => false,
            'filter_in_stock_enabled' => false,
        ]);

        $payload = app(FilterService::class)->getCategoryFilterPayload($category);

        $this->assertFalse($payload['config']['price']);
        $this->assertFalse($payload['config']['brand']);
        $this->assertFalse($payload['config']['in_stock']);
        $this->assertTrue($payload['config']['on_sale']);
        $this->assertSame([], $payload['brands']);
    }

    public function test_brand_filters_only_when_enabled(): void
    {
        $category = Category::factory()->create(['full_slug' => 'monitori']);
        $manufacturer = Manufacturer::factory()->create(['name' => 'Dell', 'slug' => 'dell']);

        Product::factory()->create([
            'category_id' => $category->id,
            'manufacturer_id' => $manufacturer->id,
            'is_public' => true,
            'status' => 'active',
        ]);

        $service = app(FilterService::class);

        $enabled = $service->getCategoryFilterPayload($category->fresh());
        $this->assertCount(1, $enabled['brands']);
        $this->assertSame('dell', $enabled['brands'][0]['slug']);

        $category->update(['filter_brand_enabled' => false]);
        $disabled = $service->getCategoryFilterPayload($category->fresh());
        $this->assertSame([], $disabled['brands']);
    }

    public function test_attribute_filters_respect_mapping_toggle(): void
    {
        $category = Category::factory()->create(['full_slug' => 'telefoni']);

        $definition = AttributeDefinition::query()->create([
            'external_attribute_id' => 'attr-color',
            'name' => 'Boja',
            'slug' => 'boja',
            'internal_type' => 'text',
            'is_filter' => true,
        ]);

        AttributeCategoryMapping::query()->create([
            'category_id' => $category->id,
            'attribute_definition_id' => $definition->id,
            'is_filter_enabled' => false,
            'sort_order' => 0,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_public' => true,
            'status' => 'active',
        ]);

        \App\Models\ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $definition->id,
            'attribute_name_snapshot' => 'Boja',
            'normalized_value' => 'Crna',
            'raw_value' => 'Crna',
        ]);

        $payload = app(FilterService::class)->getCategoryFilterPayload($category);

        $this->assertSame([], $payload['attributes']);
    }
}
