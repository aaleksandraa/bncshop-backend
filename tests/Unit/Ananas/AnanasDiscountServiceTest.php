<?php

namespace Tests\Unit\Ananas;

use App\Models\AnanasCategoryMapping;
use App\Models\AnanasDiscountAction;
use App\Models\AnanasProductMapping;
use App\Models\Category;
use App\Models\Product;
use App\Services\Ananas\AnanasDiscountService;
use App\Services\Ananas\AnanasLinkedProductSyncService;
use App\Services\Pricing\PriceCalculator;
use App\Services\Pricing\PriceResult;
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

    public function test_dry_run_skips_overlapping_scheduled_akcija(): void
    {
        $product = $this->createLinkedProduct(2566378, 199.00);
        $mapping = AnanasProductMapping::query()->where('product_id', $product->id)->firstOrFail();

        AnanasDiscountAction::query()->create([
            'ananas_product_mapping_id' => $mapping->id,
            'merchant_inventory_id' => 2566378,
            'ananas_discount_id' => 'ce98b230-cbc5-42a9-bb49-98460b10275e',
            'discount_type' => 'SALE',
            'discount_price' => 179.10,
            'currency' => 'BAM',
            'date_from' => '2026-09-25',
            'date_to' => '2026-10-01',
            'local_status' => AnanasDiscountAction::STATUS_SCHEDULED,
        ]);

        $result = app(AnanasDiscountService::class)->schedule(
            inventoryIds: [2566378],
            percentOff: 10,
            days: 7,
            useBncSale: false,
            dryRun: true,
        );

        $this->assertSame(0, $result['scheduled']);
        $this->assertSame([], $result['payloads']);
        $this->assertNotEmpty($result['skipped']);
        $this->assertStringContainsString('already has a scheduled akcija', $result['skipped'][0]);
    }

    public function test_discount_uses_ananas_base_price_when_bnc_ten_percent_is_not_lower(): void
    {
        config([
            'bnc.ananas_env' => 'stage',
            'bnc.ananas_client_id' => 'test-client-id',
            'bnc.ananas_client_secret' => 'test-client-secret',
            'bnc.ananas_stage_token_url' => 'https://api.qa2.ananastest.com/iam/api/v1/auth/token',
            'bnc.ananas_stage_product_base_url' => 'https://api.qa2.ananastest.com',
            'bnc.ananas_stage_svc_base_url' => 'https://api.svc.qa2.ananastest.com',
        ]);

        $this->createLinkedProduct(2567075, 2469.00);

        $calculator = $this->createMock(PriceCalculator::class);
        $calculator->method('calculate')->willReturn(new PriceResult(
            displayPrice: 2469.00,
            regularPrice: 2469.00,
            onSale: false,
        ));
        $this->app->instance(PriceCalculator::class, $calculator);

        Http::fake([
            'api.qa2.ananastest.com/iam/api/v1/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 900,
            ], 200),
            '*merchant-integration/prices*' => Http::response([
                [
                    'merchantInventoryId' => 2567075,
                    'basePrice' => 2000,
                    'sellablePrice' => 2000,
                ],
            ], 200),
            '*merchant-integration/products*' => Http::response([
                'content' => [],
                'totalElements' => 0,
            ], 200),
        ]);

        $result = app(AnanasDiscountService::class)->schedule(
            inventoryIds: [2567075],
            percentOff: 10,
            days: 7,
            useBncSale: false,
            dryRun: true,
        );

        $this->assertSame(1, $result['scheduled']);
        $this->assertEqualsWithDelta(2000.0, (float) $result['results'][0]['regular_price'], 0.001);
        $this->assertEqualsWithDelta(1800.0, (float) $result['payloads'][0]['discountPrice'], 0.001);
    }

    public function test_dry_run_skips_when_ananas_catalog_base_price_is_zero(): void
    {
        config([
            'bnc.ananas_env' => 'stage',
            'bnc.ananas_client_id' => 'test-client-id',
            'bnc.ananas_client_secret' => 'test-client-secret',
            'bnc.ananas_stage_token_url' => 'https://api.qa2.ananastest.com/iam/api/v1/auth/token',
            'bnc.ananas_stage_product_base_url' => 'https://api.qa2.ananastest.com',
            'bnc.ananas_stage_svc_base_url' => 'https://api.svc.qa2.ananastest.com',
        ]);

        $this->createLinkedProduct(2567071, 2519.00);

        Http::fake([
            'api.qa2.ananastest.com/iam/api/v1/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 900,
            ], 200),
            '*merchant-integration/prices*' => Http::response([], 200),
            '*merchant-integration/products*' => Http::response([
                'content' => [
                    [
                        'id' => 2567071,
                        'ean' => '4711387783597',
                        'basePrice' => 0,
                        'newBasePrice' => 0,
                    ],
                ],
                'totalElements' => 1,
            ], 200),
        ]);

        $result = app(AnanasDiscountService::class)->schedule(
            inventoryIds: [2567071],
            percentOff: 10,
            days: 7,
            useBncSale: false,
            dryRun: true,
        );

        $this->assertSame(0, $result['scheduled']);
        $this->assertSame([], $result['payloads']);
        $this->assertNotEmpty($result['skipped']);
        $this->assertStringContainsString('basePrice is 0', $result['skipped'][0]);
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
            '*merchant-integration/prices*' => Http::response([], 200),
            '*merchant-integration/products*' => Http::response(['content' => []], 200),
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
            '*merchant-integration/prices*' => Http::response([], 200),
            '*merchant-integration/products*' => Http::response(['content' => []], 200),
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
            '*merchant-integration/prices*' => Http::response([], 200),
            '*merchant-integration/products*' => Http::response(['content' => []], 200),
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
