<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ShippingRule;
use App\Services\Commerce\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShippingQuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipping_quote_returns_configured_delivery_fee(): void
    {
        ShippingRule::factory()->create([
            'type' => 'global',
            'fixed_fee' => 5,
            'free_threshold' => null,
            'is_active' => true,
            'priority' => 0,
        ]);

        $product = Product::factory()->create([
            'price_locked' => true,
            'manual_price' => 68.9,
            'display_price' => 68.9,
            'regular_price' => 68.9,
            'available_stock' => 5,
            'is_public' => true,
            'status' => 'active',
        ]);

        $sessionId = (string) Str::uuid();
        $cart = app(CartService::class)->getOrCreate($sessionId);
        app(CartService::class)->addItem($cart, $product, 1);

        $response = $this->postJson('/api/v1/checkout/shipping-quote', [
            'shipping_method' => 'delivery',
        ], [
            'X-Cart-Session' => $sessionId,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.shipping.fee', 5)
            ->assertJsonPath('data.total', 73.9);
    }

    public function test_shipping_quote_returns_zero_for_pickup(): void
    {
        ShippingRule::factory()->create([
            'type' => 'global',
            'fixed_fee' => 5,
            'free_threshold' => null,
            'is_active' => true,
        ]);

        $sessionId = (string) Str::uuid();

        $response = $this->postJson('/api/v1/checkout/shipping-quote', [
            'shipping_method' => 'pickup',
        ], [
            'X-Cart-Session' => $sessionId,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.shipping.fee', 0)
            ->assertJsonPath('data.shipping.is_free', true);
    }

    public function test_shipping_quote_charges_fee_below_free_threshold(): void
    {
        ShippingRule::factory()->create([
            'type' => 'global',
            'fixed_fee' => 7,
            'free_threshold' => 500,
            'is_active' => true,
            'priority' => 0,
        ]);

        $product = Product::factory()->create([
            'price_locked' => true,
            'manual_price' => 150,
            'display_price' => 150,
            'regular_price' => 150,
            'available_stock' => 5,
            'is_public' => true,
            'status' => 'active',
        ]);

        $sessionId = (string) Str::uuid();
        $cart = app(CartService::class)->getOrCreate($sessionId);
        app(CartService::class)->addItem($cart, $product, 2);

        $response = $this->postJson('/api/v1/checkout/shipping-quote', [
            'shipping_method' => 'delivery',
        ], [
            'X-Cart-Session' => $sessionId,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.shipping.fee', 7)
            ->assertJsonPath('data.shipping.is_free', false)
            ->assertJsonPath('data.total', 307);
    }

    public function test_shipping_quote_is_free_at_or_above_threshold(): void
    {
        ShippingRule::factory()->create([
            'type' => 'global',
            'fixed_fee' => 7,
            'free_threshold' => 500,
            'is_active' => true,
            'priority' => 0,
        ]);

        $product = Product::factory()->create([
            'price_locked' => true,
            'manual_price' => 250,
            'display_price' => 250,
            'regular_price' => 250,
            'available_stock' => 5,
            'is_public' => true,
            'status' => 'active',
        ]);

        $sessionId = (string) Str::uuid();
        $cart = app(CartService::class)->getOrCreate($sessionId);
        app(CartService::class)->addItem($cart, $product, 2);

        $response = $this->postJson('/api/v1/checkout/shipping-quote', [
            'shipping_method' => 'delivery',
        ], [
            'X-Cart-Session' => $sessionId,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.shipping.fee', 0)
            ->assertJsonPath('data.shipping.is_free', true)
            ->assertJsonPath('data.total', 500);
    }
}
