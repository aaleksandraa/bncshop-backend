<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\ElineCategory;
use App\Models\ElineCategoryMapping;
use App\Models\Product;
use App\Services\Eline\ElineSupport;
use App\Services\Eline\ElineVisibilityReapplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElineVisibilityReapplyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixes_refurbished_and_public_flags_without_touching_content_fields(): void
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

        $product = Product::query()->create([
            'external_product_id' => ElineSupport::externalProductId('501'),
            'import_source' => 'eline',
            'eline_sifra' => '501',
            'sku' => '501',
            'name' => 'Ručno uređen laptop',
            'slug' => 'rucno-uredjen-laptop',
            'category_id' => $category->id,
            'regular_price' => 999,
            'display_price' => 999,
            'available_stock' => 2,
            'is_refurbished' => false,
            'is_public' => false,
            'status' => 'active',
            'sync_status' => 'missing_from_api',
            'marked_missing_at' => now(),
        ]);

        $stats = app(ElineVisibilityReapplyService::class)->reapplyFromDatabase();

        $this->assertSame(1, $stats['updated']);

        $product->refresh();

        $this->assertTrue($product->is_refurbished);
        $this->assertTrue($product->is_public);
        $this->assertSame('synced', $product->sync_status);
        $this->assertNull($product->marked_missing_at);
        $this->assertSame('Ručno uređen laptop', $product->name);
        $this->assertSame(999.0, (float) $product->display_price);
    }
}
