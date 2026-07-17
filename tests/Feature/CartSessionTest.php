<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_endpoint_issues_session_cookie_when_missing(): void
    {
        $response = $this->getJson('/api/v1/cart');

        $response->assertOk();

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'cart_session');

        $this->assertNotNull($cookie);
        $this->assertNotEmpty($cookie->getValue());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame($cookie->getValue(), $response->headers->get('X-Cart-Session'));
    }

    public function test_sanctum_csrf_cookie_endpoint_is_available_for_stateful_requests(): void
    {
        $this->withHeaders([
            'Origin' => 'http://localhost',
            'Referer' => 'http://localhost/',
        ])->get('/sanctum/csrf-cookie')
            ->assertNoContent();
    }
}
