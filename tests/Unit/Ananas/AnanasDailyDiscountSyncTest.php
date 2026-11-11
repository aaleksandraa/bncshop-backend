<?php

namespace Tests\Unit\Ananas;

use App\Models\AnanasProductMapping;
use App\Models\AnanasSyncRun;
use App\Models\Product;
use App\Services\Ananas\AnanasDailyDiscountSyncService;
use App\Services\Ananas\AnanasSyncRunService;
use App\Services\Ananas\AnanasSyncSettings;
use App\Services\Pricing\PriceCalculator;
use App\Services\Pricing\PriceResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnanasDailyDiscountSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'bnc.ananas_env' => 'stage',
            'bnc.ananas_client_id' => 'test-client-id',
            'bnc.ananas_client_secret' => 'test-client-secret',
            'bnc.ananas_allow_catalog_writes' => true,
            'bnc.ananas_stage_token_url' => 'https://api.qa2.ananastest.com/iam/api/v1/auth/token',
            'bnc.ananas_stage_product_base_url' => 'https://api.qa2.ananastest.com',
            'bnc.ananas_stage_svc_base_url' => 'https://api.svc.qa2.ananastest.com',
        ]);

        Cache::flush();
    }

    public function test_sale_is_scheduled_when_regular_price_did_not_change(): void
    {
        $sale = $this->linkedProduct(7453672);
        $regular = $this->linkedProduct(7453673);

        $calculator = $this->createMock(PriceCalculator::class);
        $calculator->method('calculate')->willReturnCallback(function (Product $product) use ($sale): PriceResult {
            if ($product->id === $sale->id) {
                return new PriceResult(displayPrice: 800, regularPrice: 1000, onSale: true);
            }

            return new PriceResult(displayPrice: 500, regularPrice: 500, onSale: false);
        });
        $this->app->instance(PriceCalculator::class, $calculator);

        Http::fake([
            'api.qa2.ananastest.com/iam/api/v1/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 900,
            ], 200),
            '*merchant-integration/prices*' => Http::response([
                ['merchantInventoryId' => 7453672, 'basePrice' => 1000, 'sellablePrice' => 1000],
                ['merchantInventoryId' => 7453673, 'basePrice' => 500, 'sellablePrice' => 500],
            ], 200),
            '*merchant-integration/products*' => Http::response([
                'content' => [],
                'totalElements' => 0,
            ], 200),
        ]);

        $run = AnanasSyncRun::query()->create([
            'job_key' => AnanasSyncRun::JOB_DISCOUNTS,
            'status' => AnanasSyncRun::STATUS_RUNNING,
            'triggered_by' => 'cli',
        ]);

        $result = app(AnanasDailyDiscountSyncService::class)->runDaily(
            $run,
            app(AnanasSyncRunService::class),
            [],
            false,
            true,
        );

        $this->assertSame(1, $result['scheduled']);
        $this->assertGreaterThanOrEqual(1, $result['skipped']);
        $this->assertNotSame($sale->id, $regular->id);

        Http::assertNotSent(function ($request): bool {
            return str_contains($request->url(), '/merchant-integration/discounts')
                && $request->method() === 'POST';
        });
    }

    private function linkedProduct(int $inventoryId): Product
    {
        $product = Product::factory()->create([
            'barcode' => (string) (5000000000000 + $inventoryId),
            'regular_price' => 1000,
            'display_price' => 1000,
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
            'remote_environment' => app(AnanasSyncSettings::class)->environment(),
            'export_enabled' => true,
        ]);

        return $product;
    }
}
