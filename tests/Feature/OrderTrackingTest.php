<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_tracking_returns_minimal_summary_only(): void
    {
        $order = $this->createOrder();

        $response = $this->getJson('/api/v1/orders/track/'.$order->tracking_token);

        $response
            ->assertOk()
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.status', $order->status)
            ->assertJsonPath('data.requires_verification', true)
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.items')
            ->assertJsonMissingPath('data.total')
            ->assertJsonMissingPath('data.shipping_fee')
            ->assertJsonMissingPath('data.shipping_method')
            ->assertJsonMissingPath('data.items_count')
            ->assertJsonMissingPath('data.pending_loyalty_points');
    }

    public function test_post_tracking_requires_matching_email(): void
    {
        $order = $this->createOrder();

        $this->postJson('/api/v1/orders/track', [
            'token' => $order->tracking_token,
            'email' => 'wrong@example.com',
        ])->assertNotFound();

        $response = $this->postJson('/api/v1/orders/track', [
            'token' => $order->tracking_token,
            'email' => 'buyer@example.com',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.email', 'buyer@example.com')
            ->assertJsonPath('data.requires_verification', false)
            ->assertJsonPath('data.total', '50.00')
            ->assertJsonStructure(['data' => ['items', 'status_history']])
            ->assertJsonMissingPath('data.pending_loyalty_points')
            ->assertJsonMissingPath('data.items.0.supplier_sku')
            ->assertJsonMissingPath('data.items.0.external_product_id')
            ->assertJsonMissingPath('data.items.0.discount_snapshot')
            ->assertJsonMissingPath('data.items.0.discount_id')
            ->assertJsonMissingPath('data.status_history.0.changed_by')
            ->assertJsonMissingPath('data.status_history.0.order_id');
    }

    private function createOrder(): Order
    {
        $order = Order::query()->create([
            'order_number' => 'BNC-TEST-001',
            'tracking_token' => (string) Str::uuid(),
            'status' => 'nova',
            'first_name' => 'Amir',
            'last_name' => 'Test',
            'phone' => '061123456',
            'email' => 'buyer@example.com',
            'address' => 'Ulica 1',
            'city' => 'Sarajevo',
            'postal_code' => '71000',
            'subtotal' => 50,
            'discount_total' => 0,
            'shipping_fee' => 0,
            'total' => 50,
            'shipping_method' => 'pickup',
            'payment_method' => 'pay_on_delivery',
            'items_count' => 1,
            'terms_accepted_at' => now(),
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => null,
            'external_product_id' => 'EXT-1',
            'product_name' => 'Test proizvod',
            'sku' => 'SKU-1',
            'unit_price' => 50,
            'discount_amount' => 0,
            'final_price' => 50,
            'quantity' => 1,
            'line_total' => 50,
            'supplier_sku' => 'SUP-1',
            'supplier_name' => 'Supplier',
            'discount_snapshot' => ['percent' => 0],
            'discount_id' => null,
        ]);

        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'old_status' => null,
            'new_status' => 'nova',
            'changed_by' => null,
            'note' => 'Kreirana',
            'created_at' => now(),
        ]);

        return $order->fresh(['items', 'statusHistory']);
    }
}
