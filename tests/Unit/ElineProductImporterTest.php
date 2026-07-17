<?php

namespace Tests\Unit;

use App\Models\ApiSource;
use App\Models\Category;
use App\Models\ElineCategory;
use App\Models\ElineCategoryMapping;
use App\Models\ElineProductOverride;
use App\Models\Product;
use App\Services\Eline\ElineProductImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ElineProductImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_mapped_refurbished_product_with_mpc_price(): void
    {
        $source = ApiSource::query()->create([
            'name' => 'eLine ERP',
            'target_system_code' => 'eline',
            'base_url' => 'https://example.test',
            'is_active' => true,
        ]);

        $category = Category::factory()->create();
        $elineCategory = ElineCategory::query()->create([
            'name' => 'Refurbished racunari',
            'product_count' => 1,
        ]);

        ElineCategoryMapping::query()->create([
            'eline_category_id' => $elineCategory->id,
            'category_id' => $category->id,
            'is_enabled' => true,
            'product_condition' => ElineCategoryMapping::CONDITION_REFURBISHED,
        ]);

        $mappings = ElineCategoryMapping::query()
            ->with(['elineCategory', 'category'])
            ->where('is_enabled', true)
            ->get()
            ->keyBy(fn (ElineCategoryMapping $mapping): string => (string) $mapping->elineCategory?->name);

        $stats = app(ElineProductImporter::class)->importMany(
            Collection::make([[
                'sifra' => '99',
                'naziv' => 'HP EliteDesk',
                'opis' => 'Test opis',
                'eline_category' => 'Refurbished racunari',
                'aktivan' => 255,
                'mpc' => 499.99,
                'stanje' => 2,
                'price_aktivan' => 255,
            ]]),
            $mappings,
            $source,
        );

        $this->assertSame(1, $stats['created']);

        $product = Product::query()->where('eline_sifra', '99')->first();

        $this->assertNotNull($product);
        $this->assertSame('eline', $product->import_source);
        $this->assertTrue($product->is_refurbished);
        $this->assertFalse($product->is_new);
        $this->assertSame('499.99', $product->regular_price);
        $this->assertSame($category->id, $product->category_id);
        $this->assertTrue($product->is_public);
        $this->assertSame('store_available', $product->stock_status);
    }

    public function test_skips_disabled_category_mapping(): void
    {
        $source = ApiSource::query()->create([
            'name' => 'eLine ERP',
            'target_system_code' => 'eline',
            'base_url' => 'https://example.test',
            'is_active' => true,
        ]);

        $elineCategory = ElineCategory::query()->create([
            'name' => 'Refurbished racunari',
            'product_count' => 1,
        ]);

        ElineCategoryMapping::query()->create([
            'eline_category_id' => $elineCategory->id,
            'category_id' => Category::factory()->create()->id,
            'is_enabled' => false,
            'product_condition' => ElineCategoryMapping::CONDITION_REFURBISHED,
        ]);

        $mappings = ElineCategoryMapping::query()
            ->with(['elineCategory', 'category'])
            ->where('is_enabled', true)
            ->get()
            ->keyBy(fn (ElineCategoryMapping $mapping): string => (string) $mapping->elineCategory?->name);

        $stats = app(ElineProductImporter::class)->importMany(
            Collection::make([[
                'sifra' => '100',
                'naziv' => 'Skipped item',
                'opis' => '',
                'eline_category' => 'Refurbished racunari',
                'aktivan' => 255,
                'mpc' => 100,
                'stanje' => 1,
                'price_aktivan' => 255,
            ]]),
            $mappings,
            $source,
        );

        $this->assertSame(1, $stats['skipped']);
        $this->assertNull(Product::query()->where('eline_sifra', '100')->first());
    }

    public function test_respects_product_override_disable(): void
    {
        $source = ApiSource::query()->create([
            'name' => 'eLine ERP',
            'target_system_code' => 'eline',
            'base_url' => 'https://example.test',
            'is_active' => true,
        ]);

        $category = Category::factory()->create();
        $elineCategory = ElineCategory::query()->create([
            'name' => 'Novi racunari',
            'product_count' => 1,
        ]);

        ElineCategoryMapping::query()->create([
            'eline_category_id' => $elineCategory->id,
            'category_id' => $category->id,
            'is_enabled' => true,
            'product_condition' => ElineCategoryMapping::CONDITION_NEW,
        ]);

        ElineProductOverride::query()->create([
            'eline_sifra' => '101',
            'is_enabled' => false,
        ]);

        $mappings = ElineCategoryMapping::query()
            ->with(['elineCategory', 'category'])
            ->where('is_enabled', true)
            ->get()
            ->keyBy(fn (ElineCategoryMapping $mapping): string => (string) $mapping->elineCategory?->name);

        $stats = app(ElineProductImporter::class)->importMany(
            Collection::make([[
                'sifra' => '101',
                'naziv' => 'Disabled override',
                'opis' => '',
                'eline_category' => 'Novi racunari',
                'aktivan' => 255,
                'mpc' => 250,
                'stanje' => 1,
                'price_aktivan' => 255,
            ]]),
            $mappings,
            $source,
        );

        $this->assertSame(1, $stats['skipped']);
        $this->assertNull(Product::query()->where('eline_sifra', '101')->first());
    }
}
