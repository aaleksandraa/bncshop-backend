<?php

namespace Tests\Unit;

use App\Models\AttributeCategoryMapping;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Services\Catalog\CategoryFilterLayoutService;
use App\Services\Search\FilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryFilterLayoutServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_layout_updates_standard_toggles_and_attribute_order(): void
    {
        $category = Category::factory()->create([
            'filter_price_enabled' => true,
            'filter_brand_enabled' => true,
        ]);

        $first = AttributeDefinition::query()->create([
            'external_attribute_id' => 'attr-a',
            'name' => 'RAM',
            'internal_type' => 'text',
            'is_filter' => true,
        ]);

        $second = AttributeDefinition::query()->create([
            'external_attribute_id' => 'attr-b',
            'name' => 'SSD',
            'internal_type' => 'text',
            'is_filter' => true,
        ]);

        AttributeCategoryMapping::query()->create([
            'category_id' => $category->id,
            'attribute_definition_id' => $first->id,
            'is_filter_enabled' => true,
            'sort_order' => 0,
        ]);

        AttributeCategoryMapping::query()->create([
            'category_id' => $category->id,
            'attribute_definition_id' => $second->id,
            'is_filter_enabled' => true,
            'sort_order' => 1,
        ]);

        $service = app(CategoryFilterLayoutService::class);

        $layout = [
            ['type' => 'standard', 'key' => 'in_stock', 'label' => 'Samo na stanju', 'enabled' => true],
            ['type' => 'attribute', 'attribute_definition_id' => $second->id, 'label' => 'SSD', 'enabled' => true],
            ['type' => 'attribute', 'attribute_definition_id' => $first->id, 'label' => 'RAM', 'enabled' => false],
            ['type' => 'standard', 'key' => 'price', 'label' => 'Cijena (min / max)', 'enabled' => false],
        ];

        $normalized = $service->applyLayoutToCategory($category, $layout);
        $category->save();
        $service->syncAttributeMappings($category, $normalized);

        $category->refresh();

        $this->assertFalse($category->filter_price_enabled);
        $this->assertTrue($category->filter_in_stock_enabled);
        $this->assertSame(0, AttributeCategoryMapping::query()->where('attribute_definition_id', $second->id)->value('sort_order'));
        $this->assertFalse(AttributeCategoryMapping::query()->where('attribute_definition_id', $first->id)->value('is_filter_enabled'));
    }

    public function test_filter_payload_uses_layout_order(): void
    {
        $category = Category::factory()->create(['full_slug' => 'laptopi-layout']);

        $first = AttributeDefinition::query()->create([
            'external_attribute_id' => 'layout-a',
            'name' => 'A',
            'internal_type' => 'text',
            'is_filter' => true,
        ]);

        $second = AttributeDefinition::query()->create([
            'external_attribute_id' => 'layout-b',
            'name' => 'B',
            'internal_type' => 'text',
            'is_filter' => true,
        ]);

        foreach ([$first, $second] as $index => $definition) {
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

        foreach ([[$first, '8GB'], [$second, '512GB']] as [$definition, $value]) {
            ProductAttributeValue::query()->create([
                'product_id' => $product->id,
                'attribute_definition_id' => $definition->id,
                'attribute_name_snapshot' => $definition->name,
                'normalized_value' => $value,
                'raw_value' => $value,
            ]);
        }

        $category->update([
            'filter_layout' => [
                ['type' => 'standard', 'key' => 'price', 'label' => 'Cijena (min / max)', 'enabled' => true],
                ['type' => 'attribute', 'attribute_definition_id' => $second->id, 'label' => 'B', 'enabled' => true],
                ['type' => 'attribute', 'attribute_definition_id' => $first->id, 'label' => 'A', 'enabled' => true],
            ],
        ]);

        app(CategoryFilterLayoutService::class)->syncAttributeMappings(
            $category->fresh(),
            $category->filter_layout,
        );

        $payload = app(FilterService::class)->getCategoryFilterPayload($category->fresh());

        $this->assertSame('B', $payload['attributes'][0]['name']);
        $this->assertSame('A', $payload['attributes'][1]['name']);
        $this->assertSame('price', $payload['layout'][0]['key']);
        $this->assertSame('attribute', $payload['layout'][1]['type']);
    }
}
