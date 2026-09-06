<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Integrations\TrackingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReplayMetaPurchaseEventsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_replays_orders_within_days_window(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['events_received' => 1], 200),
        ]);

        app(TrackingSettings::class)->save([
            'fb_dataset_id' => '786294308773690',
            'fb_access_token' => 'meta-token',
        ]);

        $recent = $this->createOrder('BNC-RECENT', now()->subDays(10));
        $this->createOrder('BNC-OLD', now()->subDays(45));

        $this->artisan('meta:replay-purchases', ['--days' => 30])
            ->expectsOutputToContain('BNC-RECENT')
            ->doesntExpectOutputToContain('BNC-OLD')
            ->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($recent): bool {
            $event = $request->data()['data'][0] ?? [];

            return ($event['event_name'] ?? null) === 'Purchase'
                && ($event['event_id'] ?? null) === $recent->order_number;
        });
    }

    private function createOrder(string $orderNumber, \DateTimeInterface $createdAt): Order
    {
        $product = Product::factory()->create();

        $order = Order::query()->create([
            'order_number' => $orderNumber,
            'tracking_token' => 'token-'.$orderNumber,
            'status' => 'nova',
            'first_name' => 'Test',
            'last_name' => 'Kupac',
            'phone' => '061000000',
            'email' => 'kupac@test.test',
            'address' => 'Ulica 1',
            'city' => 'Sarajevo',
            'postal_code' => '71000',
            'subtotal' => 129,
            'discount_total' => 0,
            'shipping_fee' => 0,
            'total' => 129,
            'shipping_method' => 'delivery',
            'payment_method' => 'pay_on_delivery',
            'items_count' => 1,
        ]);

        $order->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 129,
            'discount_amount' => 0,
            'final_price' => 129,
            'quantity' => 1,
            'line_total' => 129,
        ]);

        return $order->fresh(['items']);
    }
}
