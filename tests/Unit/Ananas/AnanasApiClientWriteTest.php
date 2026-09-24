<?php

namespace Tests\Unit\Ananas;

use App\Services\Ananas\AnanasApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AnanasApiClientWriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'bnc.ananas_env' => 'stage',
            'bnc.ananas_client_id' => 'test-client-id',
            'bnc.ananas_client_secret' => 'test-client-secret',
            'bnc.ananas_allow_catalog_writes' => false,
            'bnc.ananas_stage_token_url' => 'https://api.qa2.ananastest.com/iam/api/v1/auth/token',
            'bnc.ananas_stage_product_base_url' => 'https://api.qa2.ananastest.com',
        ]);

        Cache::flush();
    }

    public function test_import_products_blocked_when_catalog_writes_disabled(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('catalog writes are disabled');

        app(AnanasApiClient::class)->importProducts([
            ['name' => 'Test', 'ean' => '1234567890123'],
        ]);
    }

    public function test_import_products_returns_progress_uuid_when_enabled(): void
    {
        config(['bnc.ananas_allow_catalog_writes' => true]);

        Http::fake([
            'api.qa2.ananastest.com/iam/api/v1/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 900,
            ], 200),
            'api.qa2.ananastest.com/product/api/v1/merchant-integration/import' => Http::response([
                'id' => 'c52813ec-f69b-4202-bb03-0a2534c93781',
            ], 200),
        ]);

        $result = app(AnanasApiClient::class)->importProducts([
            ['name' => 'Test', 'ean' => '1234567890123'],
        ]);

        $this->assertSame('c52813ec-f69b-4202-bb03-0a2534c93781', $result['progress_id']);
    }

    public function test_check_eans_exist_normalizes_list_of_objects_response(): void
    {
        config(['bnc.ananas_allow_catalog_writes' => true]);

        Http::fake([
            'api.qa2.ananastest.com/iam/api/v1/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 900,
            ], 200),
            'api.qa2.ananastest.com/product/api/v1/merchant-integration/ean/exists' => Http::response([
                ['088280476892' => true],
                ['757670268125' => false],
            ], 200),
        ]);

        $result = app(AnanasApiClient::class)->checkEansExist(['088280476892', '757670268125']);

        $this->assertTrue($result['088280476892']);
        $this->assertFalse($result['757670268125']);
    }

    public function test_check_eans_exist_keeps_requested_keys_when_api_omits_leading_zero(): void
    {
        Http::fake([
            'api.qa2.ananastest.com/iam/api/v1/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 900,
            ], 200),
            'api.qa2.ananastest.com/product/api/v1/merchant-integration/ean/exists' => Http::response([
                '740617304350' => true,
                '1234567890123' => false,
            ], 200),
        ]);

        $result = app(AnanasApiClient::class)->checkEansExist(['0740617304350', '1234567890123']);

        $this->assertTrue($result['0740617304350']);
        $this->assertFalse($result['1234567890123']);
        $this->assertArrayHasKey('0740617304350', $result);
    }

    public function test_find_product_by_ean_returns_first_match(): void
    {
        Http::fake([
            'api.qa2.ananastest.com/iam/api/v1/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 900,
            ], 200),
            'api.qa2.ananastest.com/product/api/v1/merchant-integration/products*' => Http::response([
                [
                    'id' => 42,
                    'ean' => '1234567890123',
                    'categories' => ['Laptopi'],
                ],
            ], 200),
        ]);

        $product = app(AnanasApiClient::class)->findProductByEan('1234567890123');

        $this->assertSame(42, $product['id']);
        $this->assertSame(['Laptopi'], $product['categories']);
    }

    public function test_schedule_discounts_posts_rsd_payload_when_writes_enabled(): void
    {
        config(['bnc.ananas_allow_catalog_writes' => true]);

        Http::fake([
            'api.qa2.ananastest.com/iam/api/v1/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 900,
            ], 200),
            'api.qa2.ananastest.com/payment/api/v1/merchant-integration/discounts' => Http::response([
                'scheduleResult' => [
                    [
                        'success' => true,
                        'data' => [
                            'merchantInventoryId' => 2566378,
                            'discountId' => 'ee7c907d-b2cc-48f8-9226-1ba6e4db1055',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = app(AnanasApiClient::class)->scheduleDiscounts([
            [
                'merchantInventoryId' => 2566378,
                'discountPrice' => '900.00',
                'discountPriceCurrency' => 'RSD',
                'dateFrom' => '24/09/2026',
                'dateTo' => '30/09/2026',
                'discountType' => 'SALE',
            ],
        ]);

        $this->assertTrue($result['scheduleResult'][0]['success']);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/payment/api/v1/merchant-integration/discounts')) {
                return false;
            }

            $body = $request->data();

            return ($body['discounts'][0]['merchantInventoryId'] ?? null) === 2566378
                && ($body['discounts'][0]['discountPriceCurrency'] ?? null) === 'RSD';
        });
    }

    public function test_schedule_discounts_includes_response_body_on_http_error(): void
    {
        config(['bnc.ananas_allow_catalog_writes' => true]);

        Http::fake([
            'api.qa2.ananastest.com/iam/api/v1/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 900,
            ], 200),
            'api.qa2.ananastest.com/payment/api/v1/merchant-integration/discounts' => Http::response([
                'message' => 'Invalid discount payload',
            ], 400),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 400');
        $this->expectExceptionMessage('Invalid discount payload');

        app(AnanasApiClient::class)->scheduleDiscounts([
            [
                'merchantInventoryId' => 2566378,
                'discountPrice' => '900.00',
                'discountPriceCurrency' => 'RSD',
                'dateFrom' => '24/09/2026',
                'dateTo' => '30/09/2026',
                'discountType' => 'SALE',
            ],
        ]);
    }

    public function test_publish_products_sends_inventory_id_list(): void
    {
        config(['bnc.ananas_allow_catalog_writes' => true]);

        Http::fake([
            'api.qa2.ananastest.com/iam/api/v1/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 900,
            ], 200),
            'api.qa2.ananastest.com/product/api/v1/merchant-integration/product/publish' => Http::response([
                'id' => 'c52813ec-f69b-4202-bb03-0a2534c93781',
            ], 200),
        ]);

        $result = app(AnanasApiClient::class)->publishProducts([2566378, 2566379]);

        $this->assertSame('c52813ec-f69b-4202-bb03-0a2534c93781', $result['progress_id']);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/product/publish')
                && $request->data() === [2566378, 2566379];
        });
    }
}
