<?php

namespace Tests\Unit\Ananas;

use App\Models\AnanasCategoryMapping;
use App\Models\AnanasProductMapping;
use App\Models\Category;
use App\Models\Product;
use App\Services\Ananas\AnanasDiscountService;
use App\Services\Ananas\AnanasLinkedProductSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnanasDiscountServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bnc.ananas_vat_rate' => 0]);
        Carbon::setTestNow(Carbon::create(2026, 9, 24, 12, 0, 0, 'Europe/Sarajevo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dry_run_builds_sale_payload_for_listed_inventory(): void
    {
        $product = $this->createLinkedProduct(2566378, 199.00);

        $result = app(AnanasDiscountService::class)->schedule(
            inventoryIds: [2566378],
            percentOff: 10,
            days: 7,
            useBncSale: false,
            dryRun: true,
        );

        $this->assertSame(1, $result['scheduled']);
        $this->assertSame([], $result['skipped']);
        $regular = (float) $result['results'][0]['regular_price'];
        $this->assertGreaterThan(0, $regular);
        $this->assertEqualsWithDelta(round($regular * 0.9, 2), (float) $result['payloads'][0]['discountPrice'], 0.011);
        $this->assertLessThanOrEqual(round($regular * 0.95, 2), (float) $result['payloads'][0]['discountPrice']);
        $this->assertSame('RSD', $result['payloads'][0]['discountPriceCurrency']);
        $this->assertSame('SALE', $result['payloads'][0]['discountType']);
        $this->assertSame(2566378, $result['payloads'][0]['merchantInventoryId']);
        $this->assertSame($product->id, $result['results'][0]['product_id']);
    }

    public function test_publish_dry_run_returns_ready_inventory_ids(): void
    {
        $this->createLinkedProduct(2566378, 100);
        $this->createLinkedProduct(2566379, 80);

        $result = app(AnanasLinkedProductSyncService::class)->publishReadyLinked(
            limit: 10,
            dryRun: true,
            inventoryIds: [2566378, 2566379],
        );

        $this->assertSame(2, $result['published']);
        $this->assertEqualsCanonicalizing([2566378, 2566379], $result['inventory_ids']);
        $this->assertNull($result['progress_id']);
    }

    private function createLinkedProduct(int $inventoryId, float $regularPrice): Product
    {
        $category = Category::factory()->create();

        AnanasCategoryMapping::query()->create([
            'category_id' => $category->id,
            'ananas_product_type' => 'ITShop',
            'ananas_category' => 'Gaming laptopi',
            'is_enabled' => true,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'barcode' => (string) (5000000000000 + $inventoryId),
            'regular_price' => $regularPrice,
            'display_price' => $regularPrice,
            'available_stock' => 3,
            'is_public' => true,
            'status' => 'active',
            'is_refurbished' => false,
            'is_set' => false,
        ]);

        AnanasProductMapping::query()->create([
            'product_id' => $product->id,
            'ean' => $product->barcode,
            'ananas_product_id' => (string) $inventoryId,
            'merchant_inventory_id' => (string) $inventoryId,
            'local_status' => AnanasProductMapping::LOCAL_LINKED,
            'remote_status' => 'READY_FOR_PUBLISH',
            'export_enabled' => true,
        ]);

        return $product;
    }
}
