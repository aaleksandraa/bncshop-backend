<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\ElineCategory;
use App\Models\ElineCategoryMapping;
use App\Models\Product;
use App\Services\Eline\ElineSupport;
use App\Services\Eline\ElineVisibilityReapplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class ElineVisibilityReapplyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_prefers_refurbished_mapping_when_bnc_category_is_shared(): void
    {
        $category = Category::factory()->create();
        $newElineCategory = ElineCategory::query()->create([
            'name' => 'Novi laptopi',
            'product_count' => 1,
        ]);
        $refurbishedElineCategory = ElineCategory::query()->create([
            'name' => 'Refurbished laptopi',
            'product_count' => 1,
        ]);

        ElineCategoryMapping::query()->create([
            'eline_category_id' => $newElineCategory->id,
            'category_id' => $category->id,
            'is_enabled' => true,
            'product_condition' => ElineCategoryMapping::CONDITION_NEW,
        ]);

        ElineCategoryMapping::query()->create([
            'eline_category_id' => $refurbishedElineCategory->id,
            'category_id' => $category->id,
            'is_enabled' => true,
            'product_condition' => ElineCategoryMapping::CONDITION_REFURBISHED,
        ]);

        $product = Product::query()->create([
            'external_product_id' => ElineSupport::externalProductId('501'),
            'import_source' => 'eline',
            'eline_sifra' => '501',
            'sku' => '501',
            'name' => 'Refurbished laptop',
            'slug' => 'refurbished-laptop',
            'category_id' => $category->id,
            'regular_price' => 500,
            'display_price' => 500,
            'available_stock' => 2,
            'is_refurbished' => false,
            'is_public' => true,
            'status' => 'active',
        ]);

        $stats = app(ElineVisibilityReapplyService::class)->reapplyFromDatabase();

        $this->assertSame(1, $stats['updated']);

        $product->refresh();

        $this->assertTrue($product->is_refurbished);
        $this->assertFalse($product->is_new);
    }

    public function test_build_visibility_updates_sets_refurbished_flag_from_import_state(): void
    {
        $product = Product::query()->create([
            'external_product_id' => ElineSupport::externalProductId('777'),
            'import_source' => 'eline',
            'eline_sifra' => '777',
            'sku' => '777',
            'name' => 'Feed laptop',
            'slug' => 'feed-laptop',
            'regular_price' => 400,
            'display_price' => 400,
            'available_stock' => 1,
            'is_refurbished' => false,
            'is_new' => true,
            'is_public' => false,
            'status' => 'draft',
        ]);

        $service = app(ElineVisibilityReapplyService::class);
        $method = new ReflectionMethod($service, 'buildVisibilityUpdates');
        $method->setAccessible(true);

        /** @var array<string, mixed> $updates */
        $updates = $method->invoke($service, $product, [
            'is_refurbished' => true,
            'is_new' => false,
            'is_public' => true,
            'status' => 'active',
        ]);

        $this->assertSame([
            'is_refurbished' => true,
            'is_new' => false,
            'is_public' => true,
            'status' => 'active',
        ], $updates);
    }
}
