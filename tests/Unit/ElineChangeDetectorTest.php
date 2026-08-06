<?php

namespace Tests\Unit;

use App\Models\ApiSource;
use App\Models\Category;
use App\Models\ElineCategory;
use App\Models\ElineCategoryMapping;
use App\Models\Product;
use App\Services\Eline\ElineChangeDetector;
use App\Services\Eline\ElineSupport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ElineChangeDetectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_new_and_modified_items_only(): void
    {
        $category = Category::factory()->create();
        $elineCategory = ElineCategory::query()->create([
            'name' => 'Refurbished racunari',
            'product_count' => 2,
        ]);

        ElineCategoryMapping::query()->create([
            'eline_category_id' => $elineCategory->id,
            'category_id' => $category->id,
            'is_enabled' => true,
            'product_condition' => ElineCategoryMapping::CONDITION_REFURBISHED,
        ]);

        $existingItem = [
            'sifra' => '10',
            'naziv' => 'Stari naziv',
            'opis' => 'Opis',
            'eline_category' => 'Refurbished racunari',
            'aktivan' => 255,
            'mpc' => 100.0,
            'stanje' => 1,
            'price_aktivan' => 255,
        ];

        Product::query()->create([
            'external_product_id' => ElineSupport::externalProductId('10'),
            'import_source' => 'eline',
            'eline_sifra' => '10',
            'eline_feed_hash' => ElineSupport::feedHash($existingItem),
            'sku' => '10',
            'name' => 'Stari naziv',
            'slug' => 'stari-naziv',
            'category_id' => $category->id,
            'regular_price' => 100,
            'display_price' => 100,
            'is_public' => true,
            'status' => 'active',
        ]);

        $items = Collection::make([
            [
                'sifra' => '10',
                'naziv' => 'Novi naziv',
                'opis' => 'Opis',
                'eline_category' => 'Refurbished racunari',
                'aktivan' => 255,
                'mpc' => 100.0,
                'stanje' => 1,
                'price_aktivan' => 255,
            ],
            [
                'sifra' => '11',
                'naziv' => 'Potpuno novi',
                'opis' => '',
                'eline_category' => 'Refurbished racunari',
                'aktivan' => 255,
                'mpc' => 50.0,
                'stanje' => 2,
                'price_aktivan' => 255,
            ],
        ]);

        $mappings = ElineCategoryMapping::query()
            ->with(['elineCategory', 'category'])
            ->where('is_enabled', true)
            ->get()
            ->keyBy(fn (ElineCategoryMapping $mapping): string => (string) $mapping->elineCategory?->name);

        $result = app(ElineChangeDetector::class)->detect($items, $mappings);

        $this->assertSame(2, $result['scanned']);
        $this->assertSame(0, $result['unchanged']);
        $this->assertSame(1, $result['new_items']);
        $this->assertSame(1, $result['modified_items']);
        $this->assertCount(2, $result['changed']);
        $this->assertSame(['10', '11'], collect($result['changed'])->pluck('sifra')->sort()->values()->all());
    }

    public function test_skips_unchanged_items(): void
    {
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

        $item = [
            'sifra' => '20',
            'naziv' => 'Bez promjene',
            'opis' => '',
            'eline_category' => 'Novi racunari',
            'aktivan' => 255,
            'mpc' => 200.0,
            'stanje' => 3,
            'price_aktivan' => 255,
        ];

        Product::query()->create([
            'external_product_id' => ElineSupport::externalProductId('20'),
            'import_source' => 'eline',
            'eline_sifra' => '20',
            'eline_feed_hash' => ElineSupport::feedHash($item),
            'sku' => '20',
            'name' => 'Bez promjene',
            'slug' => 'bez-promjene',
            'category_id' => $category->id,
            'regular_price' => 200,
            'display_price' => 200,
            'is_refurbished' => false,
            'is_new' => true,
            'is_public' => true,
            'status' => 'active',
        ]);

        $mappings = ElineCategoryMapping::query()
            ->with(['elineCategory', 'category'])
            ->where('is_enabled', true)
            ->get()
            ->keyBy(fn (ElineCategoryMapping $mapping): string => (string) $mapping->elineCategory?->name);

        $result = app(ElineChangeDetector::class)->detect(Collection::make([$item]), $mappings);

        $this->assertSame(1, $result['unchanged']);
        $this->assertCount(0, $result['changed']);
    }

    public function test_detects_mapping_mismatch_even_when_feed_hash_unchanged(): void
    {
        $category = Category::factory()->create();
        $elineCategory = ElineCategory::query()->create([
            'name' => 'Refurbished laptopi',
            'product_count' => 1,
        ]);

        ElineCategoryMapping::query()->create([
            'eline_category_id' => $elineCategory->id,
            'category_id' => $category->id,
            'is_enabled' => true,
            'product_condition' => ElineCategoryMapping::CONDITION_REFURBISHED,
        ]);

        $item = [
            'sifra' => '30',
            'naziv' => 'Laptop',
            'opis' => '',
            'eline_category' => 'Refurbished laptopi',
            'aktivan' => 255,
            'mpc' => 500.0,
            'stanje' => 2,
            'price_aktivan' => 255,
        ];

        Product::query()->create([
            'external_product_id' => ElineSupport::externalProductId('30'),
            'import_source' => 'eline',
            'eline_sifra' => '30',
            'eline_feed_hash' => ElineSupport::feedHash($item),
            'sku' => '30',
            'name' => 'Laptop',
            'slug' => 'laptop',
            'category_id' => $category->id,
            'regular_price' => 500,
            'display_price' => 500,
            'is_refurbished' => false,
            'is_public' => false,
            'status' => 'active',
            'available_stock' => 2,
        ]);

        $mappings = ElineCategoryMapping::query()
            ->with(['elineCategory', 'category'])
            ->where('is_enabled', true)
            ->get()
            ->keyBy(fn (ElineCategoryMapping $mapping): string => (string) $mapping->elineCategory?->name);

        $result = app(ElineChangeDetector::class)->detect(Collection::make([$item]), $mappings);

        $this->assertSame(0, $result['unchanged']);
        $this->assertSame(1, $result['modified_items']);
        $this->assertCount(1, $result['changed']);
    }
}
