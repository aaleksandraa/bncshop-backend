<?php

namespace Tests\Unit;

use App\Support\StorefrontConfig;
use Tests\TestCase;

class StorefrontConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('CORS_ALLOWED_ORIGINS');
        putenv('FRONTEND_URL');
        putenv('SANCTUM_STATEFUL_DOMAINS');
        putenv('SESSION_DOMAIN');

        parent::tearDown();
    }

    public function test_session_cookie_domain_defaults_from_api_app_url(): void
    {
        config(['app.url' => 'https://api.bncshop.ba']);

        $this->assertSame('.bncshop.ba', StorefrontConfig::sessionCookieDomain());
    }

    public function test_session_cookie_domain_ignores_null_string_and_uses_frontend_url(): void
    {
        config(['app.url' => 'https://bncshop.ba']);
        putenv('SESSION_DOMAIN=null');
        putenv('FRONTEND_URL=https://bncshop.ba');

        $this->assertSame('.bncshop.ba', StorefrontConfig::sessionCookieDomain());
    }

    public function test_cors_allowed_origins_fallback_to_frontend_url_when_localhost_is_configured(): void
    {
        config(['app.url' => 'https://api.bncshop.ba']);
        putenv('CORS_ALLOWED_ORIGINS=http://localhost:3000');
        putenv('FRONTEND_URL=https://bncshop.ba');

        $this->assertSame(
            ['https://bncshop.ba', 'https://www.bncshop.ba'],
            StorefrontConfig::corsAllowedOrigins(),
        );
    }

    public function test_sanctum_stateful_domains_sanitize_urls_without_protocol(): void
    {
        putenv('SANCTUM_STATEFUL_DOMAINS=bncshop.ba,https://bncshop.ba,127.0.0.1,127.0.0.1:8000');

        $this->assertSame(
            ['bncshop.ba', 'www.bncshop.ba'],
            StorefrontConfig::sanctumStatefulDomains(),
        );
    }

    public function test_sanctum_stateful_domains_fallback_to_frontend_host(): void
    {
        putenv('SANCTUM_STATEFUL_DOMAINS=');
        putenv('FRONTEND_URL=https://bncshop.ba');

        $this->assertSame(
            ['bncshop.ba', 'www.bncshop.ba'],
            StorefrontConfig::sanctumStatefulDomains(),
        );
    }
}
