<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TurnstileVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'turnstile.enabled' => true,
            'turnstile.site_key' => 'test-site-key',
            'turnstile.secret_key' => 'test-secret',
        ]);
    }

    public function test_invalid_turnstile_token_is_rejected_on_register(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => false], 200),
        ]);

        $response = $this->postJson('/api/v1/customer/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'turnstile_token' => 'invalid-token',
        ]);

        $response->assertStatus(422);
    }

    public function test_missing_turnstile_token_is_rejected_when_enabled(): void
    {
        $response = $this->postJson('/api/v1/customer/register', [
            'name' => 'Test User',
            'email' => 'missing-token@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['turnstile_token']);
    }

    public function test_customer_login_requires_turnstile_when_enabled(): void
    {
        $response = $this->postJson('/api/v1/customer/login', [
            'email' => 'login@example.com',
            'password' => 'Password1!',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['turnstile_token']);
    }

    public function test_valid_turnstile_token_allows_register(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        $response = $this->postJson('/api/v1/customer/register', [
            'name' => 'Test User',
            'email' => 'valid@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'turnstile_token' => 'valid-token',
        ]);

        $response->assertCreated();
    }
}
