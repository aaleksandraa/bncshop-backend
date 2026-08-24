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
