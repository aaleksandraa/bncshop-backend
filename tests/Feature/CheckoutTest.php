<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingRule;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Commerce\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_create_order(): void
    {
        $this->seedCheckoutSettings();
        $sessionId = $this->seedCartWithProduct();

        $response = $this->postJson('/api/v1/checkout', $this->checkoutPayload(), [
            'X-Cart-Session' => $sessionId,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.tracking_token', fn ($value) => is_string($value) && $value !== '');

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_authenticated_customer_gets_user_id_on_order(): void
    {
        $this->seedCheckoutSettings();
        $sessionId = $this->seedCartWithProduct();

        $user = User::factory()->customer()->create();

        Customer::query()->create([
            'user_id' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->withStatefulOrigin()->postJson('/api/v1/checkout', $this->checkoutPayload(), [
            'X-Cart-Session' => $sessionId,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
        ]);
    }

    public function test_guest_checkout_can_be_disabled(): void
    {
        $this->seedCheckoutSettings(['guest_checkout_enabled' => false]);
        $sessionId = $this->seedCartWithProduct();

        $response = $this->postJson('/api/v1/checkout', $this->checkoutPayload(), [
            'X-Cart-Session' => $sessionId,
        ]);

        $response->assertStatus(422);
    }

    public function test_invalid_payment_method_is_rejected(): void
    {
        $this->seedCheckoutSettings([
            'payment_methods' => ['pay_on_delivery'],
        ]);
        $sessionId = $this->seedCartWithProduct();

        $payload = $this->checkoutPayload();
        $payload['payment_method'] = 'bank_transfer';

        $response = $this->postJson('/api/v1/checkout', $payload, [
            'X-Cart-Session' => $sessionId,
        ]);

        $response->assertStatus(422);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedCheckoutSettings(array $overrides = []): void
    {
        SystemSetting::query()->create([
            'key' => 'checkout',
            'group' => 'checkout',
            'value' => array_merge([
                'payment_methods' => ['pay_on_delivery', 'bank_transfer'],
                'shipping_methods' => ['delivery', 'pickup'],
                'guest_checkout_enabled' => true,
            ], $overrides),
        ]);

        ShippingRule::factory()->create([
            'type' => 'global',
            'fixed_fee' => 5,
            'free_threshold' => null,
            'is_active' => true,
            'priority' => 0,
        ]);
    }

    private function seedCartWithProduct(): string
    {
        $product = Product::factory()->create([
            'price_locked' => true,
            'manual_price' => 50,
            'display_price' => 50,
            'regular_price' => 50,
            'available_stock' => 5,
            'is_public' => true,
            'status' => 'active',
        ]);

        $sessionId = (string) Str::uuid();
        $cart = app(CartService::class)->getOrCreate($sessionId);
        app(CartService::class)->addItem($cart, $product, 1);

        return $sessionId;
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutPayload(): array
    {
        return [
            'first_name' => 'Amir',
            'last_name' => 'Test',
            'phone' => '061123456',
            'email' => 'amir@example.com',
            'address' => 'Ulica 1',
            'city' => 'Sarajevo',
            'postal_code' => '71000',
            'shipping_method' => 'pickup',
            'payment_method' => 'pay_on_delivery',
            'accepted_terms' => true,
        ];
    }
}
