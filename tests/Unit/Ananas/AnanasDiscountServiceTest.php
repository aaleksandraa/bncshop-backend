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
use Illuminate\Support\Facades\Http;
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
        $this->assertSame('BAM', $result['payloads'][0]['discountPriceCurrency']);
        $this->assertSame('SALE', $result['payloads'][0]['discountType']);
        $this->assertSame(2566378, $result['payloads'][0]['merchantInventoryId']);
        $this->assertSame($product->id, $result['results'][0]['product_id']);
    }

    public function test_live_schedule_surfaces_ananas_error_instead_of_preview(): void
    {
        config([
            'bnc.ananas_allow_catalog_writes' => true,
            'bnc.ananas_env' => 'stage',
            'bnc.ananas_client_id' => 'test-client-id',
            'bnc.ananas_client_secret' => 'test-client-secret',
            'bnc.ananas_stage_token_url' => 'https://api.qa2.ananastest.com/iam/api/v1/auth/token',
            'bnc.ananas_stage_product_base_url' => 'https://api.qa2.ananastest.com',
        ]);

        $this->createLinkedProduct(2566378, 199.00);

        Http::fake([
            'api.qa2.ananastest.com/iam/api/v1/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 900,
            ], 200),
            'api.qa2.ananastest.com/payment/api/v1/merchant-integration/discounts' => Http::response([
                'scheduleResult' => [
                    [
                        'success' => false,
                        'error' => [
                            'merchantInventoryId' => 2566378,
                            'errorMessage' => 'Product is not published',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = app(AnanasDiscountService::class)->schedule(
            inventoryIds: [2566378],
            percentOff: 10,
            days: 7,
            useBncSale: false,
            dryRun: false,
        );

        $this->assertSame(0, $result['scheduled']);
        $this->assertSame(1, $result['failed']);
        $this->assertFalse($result['results'][0]['success']);
        $this->assertSame('Product is not published', $result['results'][0]['error']);
        $this->assertArrayHasKey('scheduleResult', $result['raw']);
    }

    public function test_live_schedule_empty_body_includes_raw_in_error(): void
    {
        config([
            'bnc.ananas_allow_catalog_writes' => true,
            'bnc.ananas_env' => 'stage',
            'bnc.ananas_client_id' => 'test-client-id',
            'bnc.ananas_client_secret' => 'test-client-secret',
            'bnc.ananas_stage_token_url' => 'https://api.qa2.ananastest.com/iam/api/v1/auth/token',
            'bnc.ananas_stage_product_base_url' => 'https://api.qa2.ananastest.com',
        ]);

        $this->createLinkedProduct(2566378, 199.00);

        Http::fake([
            'api.qa2.ananastest.com/iam/api/v1/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 900,
            ], 200),
            'api.qa2.ananastest.com/payment/api/v1/merchant-integration/discounts' => Http::response([], 200),
        ]);

        $result = app(AnanasDiscountService::class)->schedule(
            inventoryIds: [2566378],
            percentOff: 10,
            days: 7,
            useBncSale: false,
            dryRun: false,
        );

        $this->assertSame(1, $result['failed']);
        $this->assertStringContainsString('Unexpected discount response', (string) $result['results'][0]['error']);
    }

    public function test_schedule_command_prints_api_error_not_preview(): void
    {
        config([
            'bnc.ananas_allow_catalog_writes' => true,
            'bnc.ananas_env' => 'stage',
            'bnc.ananas_client_id' => 'test-client-id',
            'bnc.ananas_client_secret' => 'test-client-secret',
            'bnc.ananas_stage_token_url' => 'https://api.qa2.ananastest.com/iam/api/v1/auth/token',
            'bnc.ananas_stage_product_base_url' => 'https://api.qa2.ananastest.com',
        ]);

        $this->createLinkedProduct(2566378, 199.00);

        Http::fake([
            'api.qa2.ananastest.com/iam/api/v1/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 900,
            ], 200),
            'api.qa2.ananastest.com/payment/api/v1/merchant-integration/discounts' => Http::response([
                'scheduleResult' => [
                    [
                        'success' => false,
                        'error' => [
                            'merchantInventoryId' => 2566378,
                            'errorMessage' => 'Product is not published',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('bnc:ananas-schedule-discount', [
            '--inventory' => '2566378',
            '--percent' => '10',
            '--days' => '7',
            '--type' => 'SALE',
            '--confirm' => true,
        ])
            ->expectsOutputToContain('Product is not published')
            ->expectsOutputToContain('Raw POST')
            ->assertFailed();
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
