<?php

namespace Tests\Unit\Ananas;

use App\Models\Product;
use App\Services\Ananas\AnanasApiClient;
use App\Services\Ananas\AnanasEligibilityPolicy;
use App\Services\Ananas\AnanasRateLimiter;
use App\Services\Ananas\AnanasSyncSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class AnanasApiClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'bnc.ananas_env' => 'stage',
            'bnc.ananas_client_id' => 'test-client-id',
            'bnc.ananas_client_secret' => 'test-client-secret',
            'bnc.ananas_stage_iam_base_url' => 'https://api.qa2.ananastest.com',
            'bnc.ananas_stage_product_base_url' => 'https://api.qa2.ananastest.com',
            'bnc.ananas_stage_svc_base_url' => 'https://api.svc.qa2.ananastest.com',
            'bnc.ananas_token_cache_safety_seconds' => 120,
            'bnc.ananas_api_max_429_retries' => 2,
            'bnc.ananas_429_backoff_base_ms' => 1,
            'bnc.ananas_429_backoff_jitter_ms' => 0,
        ]);

        Cache::flush();
    }

    public function test_authenticate_caches_token_with_expires_in_safety_margin(): void
    {
        Http::fake([
            'api.qa2.ananastest.com/iam/api/v1/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 900,
                'token_type' => 'Bearer',
            ], 200),
        ]);

        $client = app(AnanasApiClient::class);

        $this->assertSame('token-abc', $client->authenticate());
        $this->assertSame('token-abc', $client->authenticate());
        $this->assertTrue(Cache::has($client->tokenCacheKey()));

        Http::assertSentCount(1);
    }

    public function test_authenticate_retries_once_after_401_then_fails_on_second_401(): void
    {
        Http::fake([
            'api.qa2.ananastest.com/iam/api/v1/auth/token' => Http::sequence()
                ->push(['access_token' => 'token-one', 'expires_in' => 900], 200)
                ->push(['access_token' => 'token-two', 'expires_in' => 900], 200),
            'api.qa2.ananastest.com/product/api/v1/merchant-integration/product-type' => Http::sequence()
                ->push(['message' => 'Unauthorized'], 401)
                ->push(['message' => 'Unauthorized'], 401),
        ]);

        $client = app(AnanasApiClient::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('failed after token refresh: HTTP 401');

        $client->getProductTypes();
    }

    public function test_get_product_types_recovers_after_single_401(): void
    {
        Http::fake([
            'api.qa2.ananastest.com/iam/api/v1/auth/token' => Http::sequence()
                ->push(['access_token' => 'token-one', 'expires_in' => 900], 200)
                ->push(['access_token' => 'token-two', 'expires_in' => 900], 200),
            'api.qa2.ananastest.com/product/api/v1/merchant-integration/product-type' => Http::sequence()
                ->push(['message' => 'Unauthorized'], 401)
                ->push(['Telefon', 'Laptop'], 200),
        ]);

        $types = app(AnanasApiClient::class)->getProductTypes();

        $this->assertSame(['Telefon', 'Laptop'], $types);
    }

    public function test_get_products_and_warehouses_parse_json(): void
    {
        Http::fake([
            'api.qa2.ananastest.com/iam/api/v1/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 900,
            ], 200),
            'api.qa2.ananastest.com/product/api/v1/merchant-integration/products*' => Http::response([
                [
                    'id' => 17,
                    'ean' => '2349589484368',
                    'status' => 'PUBLISHED',
                ],
            ], 200),
            'api.svc.qa2.ananastest.com/order/api/v1/merchant-integration/merchant-warehouses' => Http::response([
                'content' => [
                    ['id' => 1, 'warehouseName' => 'Main', 'defaultAddress' => true],
                ],
            ], 200),
        ]);

        $client = app(AnanasApiClient::class);

        $products = $client->getProducts(['page' => 0, 'size' => 1]);
        $warehouses = $client->getWarehouses();

        $this->assertSame(17, $products[0]['id']);
        $this->assertSame('Main', $warehouses['content'][0]['warehouseName']);
    }

    public function test_malformed_json_throws_runtime_exception(): void
    {
        Http::fake([
            'api.qa2.ananastest.com/iam/api/v1/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 900,
            ], 200),
            'api.qa2.ananastest.com/product/api/v1/merchant-integration/product-type' => Http::response('not-json', 200, [
                'Content-Type' => 'text/plain',
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('malformed JSON');

        app(AnanasApiClient::class)->getProductTypes();
    }

    public function test_server_error_is_retried_by_http_client(): void
    {
        Http::fake([
            'api.qa2.ananastest.com/iam/api/v1/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 900,
            ], 200),
            'api.qa2.ananastest.com/product/api/v1/merchant-integration/product-type' => Http::sequence()
                ->push(['error' => 'bad gateway'], 502)
                ->push(['Telefon'], 200),
        ]);

        $types = app(AnanasApiClient::class)->getProductTypes();

        $this->assertSame(['Telefon'], $types);
    }

    public function test_redact_sensitive_text_masks_tokens_and_secrets(): void
    {
        $raw = '{"access_token":"eyJhbGciOiJIUzI1NiJ9.abc.def","clientSecret":"super-secret"}';

        $redacted = AnanasApiClient::redactSensitiveText($raw);

        $this->assertStringNotContainsString('super-secret', $redacted);
        $this->assertStringNotContainsString('eyJhbGciOiJIUzI1NiJ9.abc.def', $redacted);
        $this->assertStringContainsString('[REDACTED]', $redacted);
    }

    public function test_api_error_logs_redacted_body(): void
    {
        Log::spy();

        Http::fake([
            'api.qa2.ananastest.com/iam/api/v1/auth/token' => Http::response([
                'access_token' => 'eyJhbGciOiJIUzI1NiJ9.abc.def',
                'expires_in' => 900,
            ], 200),
            'api.qa2.ananastest.com/product/api/v1/merchant-integration/product-type' => Http::response([
                'access_token' => 'leaked-token-value',
            ], 500),
        ]);

        try {
            app(AnanasApiClient::class)->getProductTypes();
        } catch (RuntimeException) {
            // expected
        }

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Ananas API error'
                    && str_contains((string) ($context['body'] ?? ''), '[REDACTED]')
                    && ! str_contains((string) ($context['body'] ?? ''), 'leaked-token-value');
            });
    }

    public function test_rate_limiter_is_invoked_for_product_requests(): void
    {
        $limiter = $this->createMock(AnanasRateLimiter::class);
        $limiter->expects($this->once())
            ->method('throttle')
            ->with(AnanasRateLimiter::CATEGORY_PRODUCTS);

        $this->app->instance(AnanasRateLimiter::class, $limiter);

        Http::fake([
            'api.qa2.ananastest.com/iam/api/v1/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 900,
            ], 200),
            'api.qa2.ananastest.com/product/api/v1/merchant-integration/product-type' => Http::response(['Telefon'], 200),
        ]);

        app(AnanasApiClient::class)->getProductTypes();
    }

    public function test_missing_credentials_throw_runtime_exception(): void
    {
        config([
            'bnc.ananas_client_id' => '',
            'bnc.ananas_client_secret' => '',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('credentials are not configured');

        app(AnanasApiClient::class)->authenticate(true);
    }

    public function test_settings_default_to_stage_hosts(): void
    {
        $settings = app(AnanasSyncSettings::class);

        $this->assertTrue($settings->isStage());
        $this->assertSame('https://api.qa2.ananastest.com', $settings->iamBaseUrl());
        $this->assertSame('https://api.svc.qa2.ananastest.com', $settings->svcBaseUrl());
        $this->assertFalse($settings->allowCatalogWrites());
    }
}
