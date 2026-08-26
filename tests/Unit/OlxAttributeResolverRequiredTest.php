<?php

namespace Tests\Unit;

use App\Models\OlxAttributeMapping;
use App\Models\OlxCategory;
use App\Models\OlxCategoryAttribute;
use App\Models\Product;
use App\Services\Media\ImageOptimizer;
use App\Services\Media\MediaStorage;
use App\Services\Olx\OlxAttributeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OlxAttributeResolverRequiredTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(ImageOptimizer::class);
        $this->mock(MediaStorage::class);
    }

    public function test_unmapped_olx_required_attribute_does_not_block_publish(): void
    {
        OlxCategory::query()->create([
            'id' => 100,
            'name' => 'Ostalo',
            'slug' => 'ostalo',
        ]);

        OlxCategoryAttribute::query()->create([
            'olx_category_id' => 100,
            'olx_attribute_id' => 9999,
            'name' => 'mystery',
            'display_name' => 'Neki OLX attr',
            'required' => true,
        ]);

        $missing = app(OlxAttributeResolver::class)->missingRequiredForPublish($this->makeProduct(), 100);

        $this->assertSame([], $missing);
    }

    public function test_mapped_required_attribute_without_value_blocks_publish(): void
    {
        OlxCategory::query()->create([
            'id' => 100,
            'name' => 'Laptopi',
            'slug' => 'laptopi',
        ]);

        OlxCategoryAttribute::query()->create([
            'olx_category_id' => 100,
            'olx_attribute_id' => 246,
            'name' => 'ram',
            'display_name' => 'RAM',
            'required' => true,
        ]);

        OlxAttributeMapping::query()->create([
            'olx_category_id' => 100,
            'olx_attribute_id' => 246,
            'bnc_attribute_aliases' => ['RAM'],
            'is_required_for_publish' => true,
        ]);

        $missing = app(OlxAttributeResolver::class)->missingRequiredForPublish($this->makeProduct(), 100);

        $this->assertSame([246 => 'RAM'], $missing);
    }

    public function test_warranty_attribute_stays_required_even_without_mapping(): void
    {
        OlxCategory::query()->create([
            'id' => 163,
            'name' => 'Laptopi',
            'slug' => 'laptopi',
        ]);

        OlxCategoryAttribute::query()->create([
            'olx_category_id' => 163,
            'olx_attribute_id' => 5160,
            'name' => 'garancija',
            'display_name' => 'Garancija',
            'required' => true,
        ]);

        $missing = app(OlxAttributeResolver::class)->missingRequiredForPublish($this->makeProduct(), 163);

        $this->assertSame([5160 => 'Garancija'], $missing);
    }

    public function test_computer_os_defaults_to_nema_when_missing(): void
    {
        OlxCategory::query()->create([
            'id' => 39,
            'name' => 'Laptopi',
            'slug' => 'laptopi',
        ]);

        OlxCategoryAttribute::query()->create([
            'olx_category_id' => 39,
            'olx_attribute_id' => 261,
            'name' => 'operativni-sistem',
            'display_name' => 'Operativni sistem',
            'input_type' => 'select',
            'required' => true,
            'options_json' => ['Nema', 'Win 11', 'Linux'],
        ]);

        OlxAttributeMapping::query()->create([
            'olx_category_id' => 39,
            'olx_attribute_id' => 261,
            'bnc_attribute_aliases' => ['Operativni sistem', 'OS'],
            'is_required_for_publish' => true,
        ]);

        $missing = app(OlxAttributeResolver::class)->missingRequiredForPublish($this->makeProduct(), 39);

        $this->assertSame([], $missing);
    }

    public function test_apple_processor_snaps_to_ostalo_when_olx_has_no_apple_option(): void
    {
        OlxCategory::query()->create([
            'id' => 39,
            'name' => 'Laptopi',
            'slug' => 'laptopi',
        ]);

        OlxCategoryAttribute::query()->create([
            'olx_category_id' => 39,
            'olx_attribute_id' => 262,
            'name' => 'procesor',
            'display_name' => 'Procesor',
            'input_type' => 'select',
            'required' => true,
            'options_json' => ['AMD', 'Intel', 'Ostalo'],
        ]);

        OlxAttributeMapping::query()->create([
            'olx_category_id' => 39,
            'olx_attribute_id' => 262,
            'bnc_attribute_aliases' => ['Procesor', 'CPU'],
            'is_required_for_publish' => true,
        ]);

        $product = $this->makeProduct();
        $product->update(['name' => 'Apple Macbook Air 13 2023 M4 16GB 512GB']);

        $missing = app(OlxAttributeResolver::class)->missingRequiredForPublish($product->fresh(), 39);

        $this->assertSame([], $missing);

        $resolved = app(OlxAttributeResolver::class)->resolveForProduct($product->fresh(), 39);
        $processor = collect($resolved)->firstWhere('id', 262);

        $this->assertSame('Ostalo', $processor['value'] ?? null);
    }

    public function test_processor_alias_model_procesora_fills_required_attribute(): void
    {
        OlxCategory::query()->create([
            'id' => 38,
            'name' => 'Desktop',
            'slug' => 'desktop',
        ]);

        OlxCategoryAttribute::query()->create([
            'olx_category_id' => 38,
            'olx_attribute_id' => 245,
            'name' => 'procesor',
            'display_name' => 'Procesor',
            'input_type' => 'select',
            'required' => true,
            'options_json' => ['AMD', 'Intel', 'Ostalo'],
        ]);

        OlxAttributeMapping::query()->create([
            'olx_category_id' => 38,
            'olx_attribute_id' => 245,
            'bnc_attribute_aliases' => ['Procesor', 'CPU'],
            'is_required_for_publish' => true,
        ]);

        $product = $this->makeProduct();
        $definition = \App\Models\AttributeDefinition::query()->create([
            'external_attribute_id' => (string) Str::uuid(),
            'name' => 'Model procesora',
            'internal_type' => 'text',
        ]);
        \App\Models\ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $definition->id,
            'attribute_name_snapshot' => 'Model procesora',
            'raw_value' => 'Intel Core Ultra 7',
            'normalized_value' => 'Intel Core Ultra 7',
        ]);

        $missing = app(OlxAttributeResolver::class)->missingRequiredForPublish($product->fresh(['attributeValues.attributeDefinition']), 38);

        $this->assertSame([], $missing);
    }

    public function test_required_select_falls_back_to_ostalo_when_unresolved(): void
    {
        OlxCategory::query()->create([
            'id' => 163,
            'name' => 'Monitori',
            'slug' => 'monitori',
        ]);

        OlxCategoryAttribute::query()->create([
            'olx_category_id' => 163,
            'olx_attribute_id' => 1143,
            'name' => 'dijagonala-inch',
            'display_name' => 'Dijagonala (inch)',
            'input_type' => 'select',
            'required' => true,
            'options_json' => ['24', '27', '32', 'Ostalo'],
        ]);

        OlxAttributeMapping::query()->create([
            'olx_category_id' => 163,
            'olx_attribute_id' => 1143,
            'bnc_attribute_aliases' => ['Dijagonala'],
            'is_required_for_publish' => true,
        ]);

        $product = $this->makeProduct();
        $missing = app(OlxAttributeResolver::class)->missingRequiredForPublish($product, 163);

        $this->assertSame([], $missing);

        $resolved = app(OlxAttributeResolver::class)->resolveForProduct($product, 163);
        $diagonal = collect($resolved)->firstWhere('id', 1143);

        $this->assertSame('Ostalo', $diagonal['value'] ?? null);
    }

    private function makeProduct(): Product
    {
        return Product::query()->create([
            'external_product_id' => (string) Str::uuid(),
            'name' => 'Resolver product '.Str::random(6),
            'slug' => 'resolver-product-'.Str::random(8),
            'is_public' => true,
            'status' => 'active',
            'api_stock' => 2,
            'available_stock' => 2,
            'stock_status' => 'in_stock',
        ]);
    }
}
