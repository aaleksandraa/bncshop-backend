<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoyaltyPendingEarning;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Commerce\OrderService;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LoyaltyAwardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\EmailTemplatesSeeder::class);

        SystemSetting::query()->create([
            'key' => 'loyalty',
            'value' => [
                'enabled' => true,
                'points_per_km' => 1,
                'guest_registration_prompt' => true,
            ],
            'group' => 'loyalty',
        ]);
    }

    public function test_awards_points_when_order_is_delivered_for_customer(): void
    {
        Mail::fake();

        $user = User::createAccount([
            'name' => 'Kupac Test',
            'email' => 'loyalty@test.test',
            'password' => Hash::make('password'),
            'is_customer' => true,
        ]);

        $customer = Customer::query()->create(['user_id' => $user->id]);

        $order = Order::query()->create([
            'order_number' => 'BNC-LOY-001',
            'tracking_token' => 'token-1',
            'customer_id' => $customer->id,
            'status' => 'poslano',
            'first_name' => 'Kupac',
            'last_name' => 'Test',
            'phone' => '061111111',
            'email' => 'loyalty@test.test',
            'address' => 'Ulica 1',
            'city' => 'Sarajevo',
            'postal_code' => '71000',
            'subtotal' => 100,
            'discount_total' => 10,
            'shipping_fee' => 5,
            'total' => 95,
            'shipping_method' => 'delivery',
            'payment_method' => 'cod',
            'items_count' => 1,
        ]);

        app(OrderService::class)->transition($order, 'isporučeno');

        $customer->refresh();
        $order->refresh();

        $this->assertSame(90, $customer->loyalty_points_balance);
        $this->assertSame(90, $order->points_earned);
        $this->assertTrue(
            LoyaltyTransaction::query()
                ->where('order_id', $order->id)
                ->where('type', 'earn')
                ->exists()
        );
    }

    public function test_guest_delivery_creates_pending_earning(): void
    {
        Mail::fake();

        $order = Order::query()->create([
            'order_number' => 'BNC-LOY-002',
            'tracking_token' => 'token-2',
            'status' => 'poslano',
            'first_name' => 'Gost',
            'last_name' => 'Test',
            'phone' => '061222222',
            'email' => 'guest@test.test',
            'address' => 'Ulica 2',
            'city' => 'Sarajevo',
            'postal_code' => '71000',
            'subtotal' => 50,
            'discount_total' => 0,
            'shipping_fee' => 5,
            'total' => 55,
            'shipping_method' => 'delivery',
            'payment_method' => 'cod',
            'items_count' => 1,
        ]);

        app(OrderService::class)->transition($order, 'isporučeno');

        $this->assertDatabaseHas('loyalty_pending_earnings', [
            'order_id' => $order->id,
            'email' => 'guest@test.test',
            'points' => 50,
            'status' => 'pending',
        ]);
    }

    public function test_registration_claims_pending_points(): void
    {
        Mail::fake();

        $order = Order::query()->create([
            'order_number' => 'BNC-LOY-003',
            'tracking_token' => 'token-3',
            'status' => 'isporučeno',
            'first_name' => 'Gost',
            'last_name' => 'Claim',
            'phone' => '061333333',
            'email' => 'claim@test.test',
            'address' => 'Ulica 3',
            'city' => 'Sarajevo',
            'postal_code' => '71000',
            'subtotal' => 80,
            'discount_total' => 0,
            'shipping_fee' => 0,
            'total' => 80,
            'shipping_method' => 'delivery',
            'payment_method' => 'cod',
            'items_count' => 1,
            'points_earned' => 80,
        ]);

        LoyaltyPendingEarning::query()->create([
            'email' => 'claim@test.test',
            'order_id' => $order->id,
            'points' => 80,
            'status' => 'pending',
        ]);

        $user = User::createAccount([
            'name' => 'Claim User',
            'email' => 'claim@test.test',
            'password' => Hash::make('password'),
            'is_customer' => true,
        ]);

        $customer = Customer::query()->create(['user_id' => $user->id]);

        $result = app(LoyaltyService::class)->claimPendingForCustomer($customer);

        $this->assertSame(80, $result['claimed_points']);
        $this->assertSame(80, $customer->fresh()->loyalty_points_balance);
    }
}
