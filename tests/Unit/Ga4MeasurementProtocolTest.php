<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Integrations\Ga4MeasurementProtocol;
use App\Services\Integrations\TrackingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Ga4MeasurementProtocolTest extends TestCase
{
    use RefreshDatabase;

    public function test_skips_when_api_secret_is_missing(): void
    {
        Http::fake();

        app(TrackingSettings::class)->save([
            'ga_measurement_id' => 'G-TEST123',
        ]);

        $order = $this->createOrder();
        app(Ga4MeasurementProtocol::class)->sendPurchase($order);

        Http::assertNothingSent();
    }

    public function test_posts_purchase_even_without_browser_consent(): void
    {
        Http::fake([
            'www.google-analytics.com/*' => Http::response(['ok' => true], 204),
        ]);

        app(TrackingSettings::class)->save([
            'ga_measurement_id' => 'G-TEST123',
            'ga_api_secret' => 'secret-token',
        ]);

        $order = $this->createOrder();
        app(Ga4MeasurementProtocol::class)->sendPurchase($order);

        Http::assertSent(function ($request) use ($order): bool {
            $body = $request->data();

            return str_contains($request->url(), 'measurement_id=G-TEST123')
                && str_contains($request->url(), 'api_secret=secret-token')
                && ($body['events'][0]['name'] ?? null) === 'purchase'
                && ($body['events'][0]['params']['transaction_id'] ?? null) === $order->order_number
                && (float) ($body['events'][0]['params']['value'] ?? 0) === 129.0
                && ($body['events'][0]['params']['currency'] ?? null) === 'BAM';
        });
    }

    public function test_api_secret_is_not_exposed_in_public_config(): void
    {
        app(TrackingSettings::class)->save([
            'ga_measurement_id' => 'G-TEST123',
            'ga_api_secret' => 'keep-private',
        ]);

        $public = app(TrackingSettings::class)->publicConfig();

        $this->assertSame('G-TEST123', $public['ga_measurement_id']);
        $this->assertArrayNotHasKey('ga_api_secret', $public);
    }

    public function test_blank_secret_on_save_keeps_existing_secret(): void
    {
        $settings = app(TrackingSettings::class);
        $settings->save([
            'ga_measurement_id' => 'G-TEST123',
            'ga_api_secret' => 'keep-private',
        ]);

        $settings->save([
            'ga_measurement_id' => 'G-TEST123',
            'ga_api_secret' => '',
        ]);

        $this->assertSame('keep-private', $settings->all()['ga_api_secret']);
    }

    private function createOrder(): Order
    {
        $product = Product::factory()->create();

        $order = Order::query()->create([
            'order_number' => 'BNC-TEST-1',
            'tracking_token' => 'token-1',
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
