<?php

namespace Tests\Unit;

use App\Services\Security\AdminLoginProtection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminLoginProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('admin-login:127.0.0.1');
    }

    public function test_honeypot_rejects_non_empty_value(): void
    {
        $service = app(AdminLoginProtection::class);

        $this->expectException(ValidationException::class);

        $service->validateBotProtection([
            'website' => 'spam-bot',
            'email' => 'admin@example.com',
        ]);
    }

    public function test_security_code_is_required_when_configured(): void
    {
        Config::set('admin.login_secret', 'secret-code');

        $service = app(AdminLoginProtection::class);

        $this->expectException(ValidationException::class);

        $service->validateBotProtection([
            'website' => '',
            'company' => '',
            'security_code' => 'wrong',
            'email' => 'admin@example.com',
        ]);
    }

    public function test_ip_rate_limit_blocks_after_max_attempts(): void
    {
        Config::set('admin.login_ip_max_attempts', 2);
        Config::set('admin.login_ip_decay_minutes', 15);

        $service = app(AdminLoginProtection::class);

        $service->recordFailedAttempt('admin@example.com');
        $service->recordFailedAttempt('admin@example.com');

        $this->expectException(ValidationException::class);

        $service->ensureIpNotBlocked();
    }
}
