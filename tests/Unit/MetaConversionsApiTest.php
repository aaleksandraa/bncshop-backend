<?php

namespace Tests\Unit;

use App\Models\InstallmentInquiry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Integrations\MetaConversionsApi;
use App\Services\Integrations\TrackingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaConversionsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_skips_when_access_token_is_missing(): void
    {
        Http::fake();

        app(TrackingSettings::class)->save([
            'fb_dataset_id' => '786294308773690',
        ]);

        app(MetaConversionsApi::class)->sendPurchase($this->createOrder());

        Http::assertNothingSent();
    }

    public function test_posts_purchase_with_product_data(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['events_received' => 1], 200),
        ]);

        app(TrackingSettings::class)->save([
            'fb_dataset_id' => '786294308773690',
            'fb_access_token' => 'meta-token',
        ]);

        $order = $this->createOrder();
        app(MetaConversionsApi::class)->sendPurchase($order);

        Http::assertSent(function ($request) use ($order): bool {
            $body = $request->data();
            $event = $body['data'][0] ?? [];

            return str_contains($request->url(), '786294308773690/events')
                && str_contains($request->url(), 'access_token=meta-token')
                && ($event['event_name'] ?? null) === 'Purchase'
                && ($event['event_id'] ?? null) === $order->order_number
                && ($event['action_source'] ?? null) === 'website'
                && ($event['custom_data']['currency'] ?? null) === 'BAM'
                && (float) ($event['custom_data']['value'] ?? 0) === 129.0
                && ($event['custom_data']['content_type'] ?? null) === 'product'
                && is_array($event['custom_data']['content_ids'] ?? null)
                && is_array($event['user_data']['em'] ?? null);
        });
    }

    public function test_posts_crm_lead_event_for_installment_inquiry(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['events_received' => 1], 200),
        ]);

        app(TrackingSettings::class)->save([
            'fb_dataset_id' => '786294308773690',
            'fb_access_token' => 'meta-token',
            'fb_crm_name' => 'BNC Shop',
        ]);

        $product = Product::factory()->create(['sku' => 'SKU-LEAD-1']);
        $inquiry = InstallmentInquiry::query()->create([
            'first_name' => 'Ana',
            'last_name' => 'Kupac',
            'phone' => '061111222',
            'email' => 'ana@example.test',
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'quantity' => 1,
            'base_price' => 499.99,
            'installment_type' => 'mikrofin',
            'months' => 12,
            'monthly_amount' => 45.00,
            'total_amount' => 540.00,
            'interest_rate' => 0,
            'provision_rate' => 0,
            'status' => 'nova',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        app(MetaConversionsApi::class)->sendLead($inquiry);

        Http::assertSent(function ($request) use ($inquiry, $product): bool {
            $body = $request->data();
            $event = $body['data'][0] ?? [];

            return ($event['event_name'] ?? null) === 'Lead'
                && ($event['action_source'] ?? null) === 'system_generated'
                && ($event['custom_data']['event_source'] ?? null) === 'crm'
                && ($event['custom_data']['lead_event_source'] ?? null) === 'BNC Shop'
                && ($event['event_id'] ?? null) === 'lead-'.$inquiry->id
                && ($event['custom_data']['content_ids'][0] ?? null) === (string) $product->id;
        });
    }

    public function test_posts_page_view_and_view_content_for_product(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['events_received' => 2], 200),
        ]);

        app(TrackingSettings::class)->save([
            'fb_dataset_id' => '786294308773690',
            'fb_access_token' => 'meta-token',
        ]);

        $product = Product::factory()->create(['display_price' => 129.00]);
        app(MetaConversionsApi::class)->sendProductView(
            $product,
            'https://bnc.ba/proizvod/test',
            '127.0.0.1',
            'PHPUnit',
        );

        Http::assertSent(function ($request) use ($product): bool {
            $events = $request->data()['data'] ?? [];
            $names = array_column($events, 'event_name');

            return in_array('PageView', $names, true)
                && in_array('ViewContent', $names, true)
                && ($events[1]['custom_data']['content_ids'][0] ?? null) === (string) $product->id;
        });
    }

    public function test_skips_product_view_without_user_agent(): void
    {
        Http::fake();

        app(TrackingSettings::class)->save([
            'fb_dataset_id' => '786294308773690',
            'fb_access_token' => 'meta-token',
        ]);

        $product = Product::factory()->create();
        app(MetaConversionsApi::class)->sendProductView(
            $product,
            'https://bnc.ba/proizvod/test',
            '127.0.0.1',
            null,
        );

        Http::assertNothingSent();
    }

    public function test_meta_secrets_are_not_exposed_in_public_config(): void
    {
        app(TrackingSettings::class)->save([
            'fb_pixel_id' => '786294308773690',
            'fb_dataset_id' => '786294308773690',
            'fb_access_token' => 'keep-private',
            'fb_test_event_code' => 'TEST123',
        ]);

        $public = app(TrackingSettings::class)->publicConfig();

        $this->assertSame('786294308773690', $public['fb_pixel_id']);
        $this->assertArrayNotHasKey('fb_access_token', $public);
        $this->assertArrayNotHasKey('fb_dataset_id', $public);
        $this->assertArrayNotHasKey('fb_test_event_code', $public);
    }

    public function test_blank_access_token_on_save_keeps_existing_token(): void
    {
        $settings = app(TrackingSettings::class);
        $settings->save([
            'fb_access_token' => 'keep-private',
        ]);

        $settings->save([
            'fb_access_token' => '',
        ]);

        $this->assertSame('keep-private', $settings->all()['fb_access_token']);
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
