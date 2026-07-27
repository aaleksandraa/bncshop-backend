<?php

namespace Tests\Unit;

use App\Models\ApiSource;
use App\Services\Sync\IntegrationApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationApiClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_uses_api_auth_path_and_snake_case_tokens(): void
    {
        config(['bnc.a1_api_verify_ssl' => false]);

        Http::fake([
            'https://a1team.ba/api/auth/login' => Http::response([
                'access_token' => 'test-access',
                'refresh_token' => 'test-refresh',
                'expires_in' => 3600,
            ], 200),
        ]);

        $source = ApiSource::query()->create([
            'name' => 'Test',
            'target_system_code' => 'bnc-shop',
            'base_url' => 'https://a1team.ba',
            'username' => 'bnc',
            'password' => 'secret',
            'is_active' => true,
        ]);

        IntegrationApiClient::forSource($source)->login();

        Http::assertSent(fn ($request) => $request->url() === 'https://a1team.ba/api/auth/login'
            && $request['username'] === 'bnc');

        $source->refresh();
        $this->assertSame('test-access', $source->access_token);
        $this->assertSame('connected', $source->connection_status);
    }

    public function test_login_falls_back_to_env_when_database_credentials_are_blank(): void
    {
        config([
            'bnc.a1_api_verify_ssl' => false,
            'bnc.a1_api_username' => 'bnc',
            'bnc.a1_api_password' => 'env-secret',
        ]);

        Http::fake([
            'https://a1team.ba/api/auth/login' => Http::response([
                'access_token' => 'env-access',
                'refresh_token' => 'env-refresh',
                'expires_in' => 3600,
            ], 200),
        ]);

        $source = ApiSource::query()->create([
            'name' => 'Test',
            'target_system_code' => 'bnc-shop',
            'base_url' => 'https://a1team.ba',
            'username' => null,
            'password' => null,
            'is_active' => true,
        ]);

        IntegrationApiClient::forSource($source)->login();

        Http::assertSent(fn ($request) => $request['username'] === 'bnc'
            && $request['password'] === 'env-secret');
    }

    public function test_products_pagination_uses_next_page(): void
    {
        config(['bnc.a1_api_verify_ssl' => false]);

        $source = ApiSource::query()->create([
            'name' => 'Test',
            'target_system_code' => 'bnc-shop',
            'base_url' => 'https://a1team.ba',
            'username' => 'bnc',
            'password' => 'secret',
            'access_token' => 'token',
            'page_size' => 2,
            'is_active' => true,
        ]);

        Http::fake([
            'https://a1team.ba/api/integrations/bnc-shop/products*' => Http::sequence()
                ->push([
                    'data' => [['productId' => '1'], ['productId' => '2']],
                    'pagination' => ['nextPage' => 2, 'currentPage' => 1],
                ])
                ->push([
                    'data' => [['productId' => '3']],
                    'pagination' => ['nextPage' => null, 'currentPage' => 2],
                ]),
        ]);

        $page1 = IntegrationApiClient::forSource($source)->getProducts(null, 1, 2);
        $this->assertCount(2, $page1['data']);
        $this->assertSame(2, $page1['meta']['nextPage']);
    }

    public function test_products_request_includes_modified_after(): void
    {
        config(['bnc.a1_api_verify_ssl' => false]);

        $source = ApiSource::query()->create([
            'name' => 'Test',
            'target_system_code' => 'bnc-shop',
            'base_url' => 'https://a1team.ba',
            'username' => 'bnc',
            'password' => 'secret',
            'access_token' => 'token',
            'page_size' => 500,
            'is_active' => true,
        ]);

        Http::fake([
            'https://a1team.ba/api/integrations/bnc-shop/products*' => Http::response([
                'data' => [],
                'pagination' => ['nextPage' => null],
            ], 200),
        ]);

        IntegrationApiClient::forSource($source)->getProducts('2026-09-03T14:30:00+00:00', 1);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/products')
                && ($request->data()['ModifiedAfter'] ?? null) === '2026-09-03T14:30:00+00:00';
        });
    }

    public function test_refresh_falls_back_to_login_when_refresh_token_is_invalid(): void
    {
        config(['bnc.a1_api_verify_ssl' => false]);

        $source = ApiSource::query()->create([
            'name' => 'Test',
            'target_system_code' => 'bnc-shop',
            'base_url' => 'https://a1team.ba',
            'username' => 'bnc',
            'password' => 'secret',
            'access_token' => 'old-access',
            'refresh_token' => 'expired-refresh',
            'token_expires_at' => now()->subHour(),
            'is_active' => true,
        ]);

        Http::fake([
            'https://a1team.ba/api/auth/refresh' => Http::response([
                'error' => 'invalid_refresh_token',
                'message' => 'Refresh token is invalid or expired.',
            ], 401),
            'https://a1team.ba/api/auth/login' => Http::response([
                'access_token' => 'new-access',
                'refresh_token' => 'new-refresh',
                'expires_in' => 3600,
            ], 200),
        ]);

        IntegrationApiClient::forSource($source)->ensureAuthenticated();

        $source->refresh();
        $this->assertSame('new-access', $source->access_token);
        $this->assertSame('new-refresh', $source->refresh_token);
    }
}
