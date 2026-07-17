<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SellerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\EmailTemplatesSeeder::class);
    }

    public function test_seller_can_login_and_list_orders(): void
    {
        $role = Role::findOrCreate('Prodavac');

        $seller = User::createAccount([
            'name' => 'Test Prodavac',
            'email' => 'seller@test.test',
            'password' => Hash::make('password123'),
            'is_customer' => false,
        ]);
        $seller->assignRole($role);

        Order::query()->create([
            'order_number' => 'BNC-TEST-001',
            'tracking_token' => 'track-token-001',
            'status' => 'nova',
            'first_name' => 'Ana',
            'last_name' => 'Anic',
            'phone' => '061111111',
            'email' => 'ana@test.test',
            'address' => 'Ulica 1',
            'city' => 'Sarajevo',
            'postal_code' => '71000',
            'subtotal' => 100,
            'discount_total' => 0,
            'shipping_fee' => 5,
            'total' => 105,
            'shipping_method' => 'standard',
            'payment_method' => 'cod',
            'items_count' => 1,
        ]);

        $login = $this->postJsonStateful('/api/v1/seller/login', [
            'email' => 'seller@test.test',
            'password' => 'password123',
        ]);

        $login->assertOk()
            ->assertJsonPath('data.user.email', 'seller@test.test');

        $this->getJsonStateful('/api/v1/seller/orders')
            ->assertOk()
            ->assertJsonPath('data.0.order_number', 'BNC-TEST-001');
    }

    public function test_customer_cannot_use_seller_login(): void
    {
        User::createAccount([
            'name' => 'Kupac',
            'email' => 'buyer@test.test',
            'password' => Hash::make('password123'),
            'is_customer' => true,
        ]);

        $this->postJson('/api/v1/seller/login', [
            'email' => 'buyer@test.test',
            'password' => 'password123',
        ])->assertStatus(422);
    }

    public function test_seller_can_mark_order_as_shipped_and_send_email(): void
    {
        Mail::fake();

        $role = Role::findOrCreate('Prodavac');

        $seller = User::createAccount([
            'name' => 'Test Prodavac',
            'email' => 'seller2@test.test',
            'password' => Hash::make('password123'),
            'is_customer' => false,
        ]);
        $seller->assignRole($role);

        $order = Order::query()->create([
            'order_number' => 'BNC-TEST-002',
            'tracking_token' => 'track-token-002',
            'status' => 'nova',
            'first_name' => 'Marko',
            'last_name' => 'Markic',
            'phone' => '062222222',
            'email' => 'marko@test.test',
            'address' => 'Ulica 2',
            'city' => 'Mostar',
            'postal_code' => '88000',
            'subtotal' => 200,
            'discount_total' => 0,
            'shipping_fee' => 0,
            'total' => 200,
            'shipping_method' => 'standard',
            'payment_method' => 'cod',
            'items_count' => 1,
        ]);

        $this->postJsonStateful('/api/v1/seller/login', [
            'email' => 'seller2@test.test',
            'password' => 'password123',
        ])->assertOk();

        $this->patchJsonStateful("/api/v1/seller/orders/{$order->id}/status", [
                'status' => 'poslano',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'poslano');

        Mail::assertQueued(\App\Mail\OrderStatusChanged::class, function ($mail) use ($order) {
            return $mail->hasTo('marko@test.test')
                && $mail->order->is($order->fresh());
        });
    }
}
