<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Commerce\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LoyaltyClawbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\EmailTemplatesSeeder::class);

        SystemSetting::query()->create([
            'key' => 'loyalty',
            'value' => ['enabled' => true, 'points_per_km' => 1],
            'group' => 'loyalty',
        ]);
    }

    public function test_clawback_removes_points_after_return(): void
    {
        Mail::fake();

        $user = User::createAccount([
            'name' => 'Clawback User',
            'email' => 'clawback@test.test',
            'password' => Hash::make('password'),
            'is_customer' => true,
        ]);

        $customer = Customer::query()->create([
            'user_id' => $user->id,
            'loyalty_points_balance' => 0,
        ]);

        $order = Order::query()->create([
            'order_number' => 'BNC-CLAW-001',
            'tracking_token' => 'token-claw',
            'customer_id' => $customer->id,
            'status' => 'poslano',
            'first_name' => 'Claw',
            'last_name' => 'Back',
            'phone' => '061444444',
            'email' => 'clawback@test.test',
            'address' => 'Ulica 4',
            'city' => 'Sarajevo',
            'postal_code' => '71000',
            'subtotal' => 60,
            'discount_total' => 0,
            'shipping_fee' => 0,
            'total' => 60,
            'shipping_method' => 'delivery',
            'payment_method' => 'cod',
            'items_count' => 1,
        ]);

        $service = app(OrderService::class);
        $service->transition($order, 'isporučeno');
        $customer->refresh();
        $this->assertSame(60, $customer->loyalty_points_balance);

        $service->transition($order->fresh(), 'vraćeno');
        $customer->refresh();

        $this->assertSame(0, $customer->loyalty_points_balance);
        $this->assertTrue(
            LoyaltyTransaction::query()
                ->where('order_id', $order->id)
                ->where('type', 'clawback')
                ->exists()
        );
    }
}
