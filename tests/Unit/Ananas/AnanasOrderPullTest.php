<?php

namespace Tests\Unit\Ananas;

use App\Models\AnanasOrder;
use App\Models\AnanasProductMapping;
use App\Models\Product;
use App\Services\Ananas\AnanasCommittedStockService;
use App\Services\Ananas\AnanasSyncSettings;
use App\Support\Ananas\AnanasOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AnanasOrderPullTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'bnc.ananas_env' => 'stage',
            'bnc.ananas_client_id' => 'test-client',
            'bnc.ananas_client_secret' => 'test-secret',
            'bnc.ananas_stage_token_url' => 'https://api.qa2.ananastest.com/iam/api/v1/auth/token',
            'bnc.ananas_stage_product_base_url' => 'https://api.qa2.ananastest.com',
            'bnc.seller_notification_email' => 'prodaja@test.local',
            'bnc.admin_notification_email' => 'prodaja@test.local',
        ]);
    }

    public function test_pull_creates_order_without_duplicate_notification(): void
    {
        Mail::fake();

        $settings = app(AnanasSyncSettings::class);
        $all = $settings->all();
        $all['enabled'] = true;
        $all['orders_pull_enabled'] = true;
        $all['orders_notify_since'] = now()->subHour()->toIso8601String();
        $settings->save($all);

        Http::fake([
            'api.qa2.ananastest.com/iam/api/v1/auth/token' => Http::response(['access_token' => 'tok', 'expires_in' => 900], 200),
            'api.qa2.ananastest.com/order/api/v1/merchant-integration/orders*' => Http::response([
                'content' => [[
                    'id' => 'TEST-ORDER-1',
                    'createdDate' => now()->toIso8601String(),
                    'totalPrice' => 100,
                    'currency' => 'BAM',
                    'paymentMethods' => ['PBC'],
                    'billingAddress' => ['firstName' => 'Ana', 'lastName' => 'Test', 'city' => 'Sarajevo'],
                    'items' => [[
                        'id' => 'line-1',
                        'quantity' => 1,
                        'productEan' => '1234567890123',
                        'productName' => 'Test product',
                        'basePrice' => 100,
                        'grandTotal' => 100,
                    ]],
                ]],
                'last' => true,
            ], 200),
            'api.qa2.ananastest.com/order/api/v1/merchant-integration/outbound-orders/shipments*' => Http::response([
                'content' => [],
                'last' => true,
            ], 200),
        ]);

        $pull = app(\App\Services\Ananas\AnanasOrderPullService::class);
        $first = $pull->pull();
        $second = $pull->pull();

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, AnanasOrder::query()->count());
        Mail::assertQueued(\App\Mail\AnanasOrderNotificationAdmin::class, 1);
    }

    public function test_committed_stock_reduces_effective_level(): void
    {
        $product = Product::factory()->create(['available_stock' => 10, 'api_stock' => 10]);
        $mapping = AnanasProductMapping::query()->create([
            'product_id' => $product->id,
            'ean' => '1234567890123',
            'merchant_inventory_id' => 999,
            'local_status' => AnanasProductMapping::LOCAL_LINKED,
            'remote_environment' => 'stage',
            'export_enabled' => true,
        ]);

        $order = AnanasOrder::query()->create([
            'ananas_order_id' => 'ORD-1',
            'remote_environment' => 'stage',
            'total_price' => 50,
            'currency' => 'BAM',
            'status_bucket' => AnanasOrderStatus::BUCKET_AWAITING,
        ]);

        $order->items()->create([
            'ananas_line_id' => 'l1',
            'product_id' => $product->id,
            'ananas_product_mapping_id' => $mapping->id,
            'merchant_inventory_id' => 999,
            'quantity_ordered' => 2,
            'committed_quantity' => 2,
        ]);

        $settings = app(AnanasSyncSettings::class);
        $all = $settings->all();
        $all['orders_pull_enabled'] = true;
        $settings->save($all);

        $service = app(AnanasCommittedStockService::class);
        $this->assertSame(2, $service->openQuantityForProduct($product->id));
        $this->assertSame(8, $service->effectiveStockLevelForAnanas($product->fresh()));
    }

    public function test_terminal_status_clears_commitment(): void
    {
        $product = Product::factory()->create(['available_stock' => 5, 'api_stock' => 5]);

        $order = AnanasOrder::query()->create([
            'ananas_order_id' => 'ORD-2',
            'remote_environment' => 'stage',
            'total_price' => 50,
            'currency' => 'BAM',
            'status_bucket' => AnanasOrderStatus::BUCKET_COMPLETED,
            'is_terminal' => true,
            'remote_status' => 'DELIVERED',
        ]);

        $item = $order->items()->create([
            'ananas_line_id' => 'l2',
            'product_id' => $product->id,
            'quantity_ordered' => 1,
            'committed_quantity' => 1,
        ]);

        app(AnanasCommittedStockService::class)->refreshItemCommitment($item->fresh(), true);

        $this->assertSame(0, $item->fresh()->committed_quantity);
    }
}
