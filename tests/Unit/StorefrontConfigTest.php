<?php

namespace Tests\Unit;

use App\Support\StorefrontConfig;
use Tests\TestCase;

class StorefrontConfigTest extends TestCase
{
    public function test_session_cookie_domain_defaults_from_api_app_url(): void
    {
        config(['app.url' => 'https://api.bncshop.ba']);

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
