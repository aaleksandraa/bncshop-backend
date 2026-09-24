<?php

namespace Tests\Unit\Ananas;

use App\Models\AnanasCategoryMapping;
use App\Models\AnanasProductMapping;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductImage;
use App\Services\Ananas\AnanasEligibilityPolicy;
use App\Services\Ananas\AnanasProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnanasProductImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'bnc.ananas_vat_rate' => 0,
            'bnc.ananas_import_batch_max_size' => 100,
            'bnc.ananas_import_scan_max' => 2000,
        ]);
    }

    public function test_dry_run_fills_limit_with_eligible_products_and_reports_skip_reasons(): void
    {
        $category = Category::factory()->create();

        AnanasCategoryMapping::query()->create([
            'category_id' => $category->id,
            'ananas_product_type' => 'ITShop',
            'ananas_category' => 'Gaming laptopi',
            'is_enabled' => true,
            'include_descendants' => true,
        ]);

        for ($i = 0; $i < 5; $i++) {
            Product::factory()->create([
                'category_id' => $category->id,
                'barcode' => null,
                'is_public' => true,
                'status' => 'active',
                'is_refurbished' => false,
                'is_set' => false,
            ]);
        }

        $eligibleIds = [];
        for ($i = 0; $i < 3; $i++) {
            $eligibleIds[] = $this->createEligibleProduct($category, (string) (4000000000000 + $i))->id;
        }

        $result = app(AnanasProductImportService::class)->importBatch(limit: 10, dryRun: true);

        $this->assertSame(3, $result['submitted']);
        $this->assertSame(5, $result['skipped']);
        $this->assertSame(8, $result['scanned']);
        $this->assertSame($eligibleIds, $result['product_ids']);
        $this->assertSame([AnanasEligibilityPolicy::MISSING_EAN => 5], $result['skip_reasons']);
        $this->assertSame(0, AnanasProductMapping::query()->count());
    }

    public function test_dry_run_stops_once_eligible_limit_is_reached(): void
    {
        $category = Category::factory()->create();

        AnanasCategoryMapping::query()->create([
            'category_id' => $category->id,
            'ananas_product_type' => 'ITShop',
            'ananas_category' => 'Gaming laptopi',
            'is_enabled' => true,
        ]);

        Product::factory()->create([
            'category_id' => $category->id,
            'barcode' => null,
            'is_public' => true,
            'status' => 'active',
        ]);

        $first = $this->createEligibleProduct($category, '4100000000001');
        $second = $this->createEligibleProduct($category, '4100000000002');
        $this->createEligibleProduct($category, '4100000000003');

        $result = app(AnanasProductImportService::class)->importBatch(limit: 2, dryRun: true);

        $this->assertSame(2, $result['submitted']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(3, $result['scanned']);
        $this->assertSame([$first->id, $second->id], $result['product_ids']);
    }

    public function test_dry_run_skips_already_submitted_products(): void
    {
        $category = Category::factory()->create();

        AnanasCategoryMapping::query()->create([
            'category_id' => $category->id,
            'ananas_product_type' => 'ITShop',
            'ananas_category' => 'Gaming laptopi',
            'is_enabled' => true,
        ]);

        $already = $this->createEligibleProduct($category, '4200000000001');
        $next = $this->createEligibleProduct($category, '4200000000002');

        AnanasProductMapping::query()->create([
            'product_id' => $already->id,
            'ean' => '4200000000001',
            'local_status' => AnanasProductMapping::LOCAL_SUBMITTED,
            'export_enabled' => true,
        ]);

        $result = app(AnanasProductImportService::class)->importBatch(limit: 10, dryRun: true);

        $this->assertSame(1, $result['submitted']);
        $this->assertSame([$next->id], $result['product_ids']);
        $this->assertSame(AnanasProductMapping::LOCAL_SUBMITTED, $already->fresh()->ananasProductMapping?->local_status);
    }

    public function test_explicit_product_option_can_reimport_submitted_sku(): void
    {
        $category = Category::factory()->create();

        AnanasCategoryMapping::query()->create([
            'category_id' => $category->id,
            'ananas_product_type' => 'ITShop',
            'ananas_category' => 'Gaming laptopi',
            'is_enabled' => true,
        ]);

        $already = $this->createEligibleProduct($category, '4300000000001');

        AnanasProductMapping::query()->create([
            'product_id' => $already->id,
            'ean' => '4300000000001',
            'local_status' => AnanasProductMapping::LOCAL_SUBMITTED,
            'export_enabled' => true,
        ]);

        $result = app(AnanasProductImportService::class)->importBatch(
            limit: 10,
            productId: (int) $already->id,
            dryRun: true,
        );

        $this->assertSame(1, $result['submitted']);
        $this->assertSame([$already->id], $result['product_ids']);
    }

    public function test_scoped_product_count_includes_descendants(): void
    {
        $parent = Category::factory()->create(['name' => 'Laptopi']);
        $child = Category::factory()->create(['name' => 'Gaming', 'parent_id' => $parent->id]);

        $mapping = AnanasCategoryMapping::query()->create([
            'category_id' => $parent->id,
            'ananas_product_type' => 'ITShop',
            'ananas_category' => 'Gaming laptopi',
            'is_enabled' => true,
            'include_descendants' => true,
        ]);

        Product::factory()->create(['category_id' => $parent->id]);
        Product::factory()->count(2)->create(['category_id' => $child->id]);

        $scope = app(\App\Services\Ananas\AnanasExportScope::class);

        $this->assertSame(3, $scope->scopedProductCountForMapping($mapping));
    }

    private function createEligibleProduct(Category $category, string $ean): Product
    {
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'barcode' => $ean,
            'regular_price' => 150,
            'display_price' => 150,
            'available_stock' => 2,
            'is_public' => true,
            'status' => 'active',
            'is_refurbished' => false,
            'is_set' => false,
        ]);

        ProductImage::query()->create([
            'product_id' => $product->id,
            'image_url' => 'https://cdn.example.test/'.$ean.'.jpg',
            'public_url' => 'https://cdn.example.test/'.$ean.'.jpg',
            'status' => 'active',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $definition = AttributeDefinition::query()->firstOrCreate(
            ['name' => 'Težina'],
            [
                'external_attribute_id' => (string) Str::uuid(),
                'display_name' => 'Težina',
                'internal_type' => 'text',
                'is_public' => true,
            ],
        );

        ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $definition->id,
            'attribute_name_snapshot' => 'Težina',
            'raw_value' => '1.2 kg',
            'normalized_value' => '1.2',
            'normalized_type' => 'number',
        ]);

        return $product->fresh(['images', 'attributeValues.attributeDefinition', 'manufacturer']);
    }
}
