<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\MarketingContact;
use App\Models\Order;
use App\Models\User;
use App\Services\Marketing\MarketingContactSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MarketingContactSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_customer_creates_registered_contact(): void
    {
        $user = User::createAccount([
            'name' => 'Ana Anic',
            'email' => 'kupac@example.com',
            'password' => Hash::make('password'),
            'is_customer' => true,
            'phone' => '+38761111222',
        ]);

        $customer = Customer::query()->create([
            'user_id' => $user->id,
            'phone' => '+38761111222',
        ]);

        $contact = app(MarketingContactSyncService::class)->syncCustomer($customer);

        $this->assertNotNull($contact);
        $this->assertSame(MarketingContact::TYPE_REGISTERED, $contact->type);
        $this->assertSame('kupac@example.com', $contact->email);
        $this->assertSame($customer->id, $contact->customer_id);
    }

    public function test_sync_guest_email_from_orders(): void
    {
        Order::query()->create([
            'order_number' => 'BNC-TEST-001',
            'tracking_token' => 'token-1',
            'status' => 'pending',
            'email' => 'gost@example.com',
            'first_name' => 'Marko',
            'last_name' => 'Markovic',
            'phone' => '+38761111333',
            'address' => 'Ulica 1',
            'city' => 'Sarajevo',
            'postal_code' => '71000',
            'subtotal' => 150,
            'discount_total' => 0,
            'shipping_fee' => 0,
            'total' => 150,
            'shipping_method' => 'delivery',
            'payment_method' => 'pay_on_delivery',
            'items_count' => 1,
        ]);

        $contact = app(MarketingContactSyncService::class)->syncEmail('gost@example.com');

        $this->assertNotNull($contact);
        $this->assertSame(MarketingContact::TYPE_GUEST, $contact->type);
        $this->assertSame(1, $contact->orders_count);
        $this->assertSame('150.00', $contact->orders_total);
    }

    public function test_registered_contact_aggregates_guest_and_linked_orders(): void
    {
        Order::query()->create([
            'order_number' => 'BNC-TEST-002',
            'tracking_token' => 'token-2',
            'status' => 'pending',
            'email' => 'reg@example.com',
            'first_name' => 'Reg',
            'last_name' => 'User',
            'phone' => '+38761111444',
            'address' => 'Ulica 2',
            'city' => 'Sarajevo',
            'postal_code' => '71000',
            'subtotal' => 50,
            'discount_total' => 0,
            'shipping_fee' => 0,
            'total' => 50,
            'shipping_method' => 'delivery',
            'payment_method' => 'pay_on_delivery',
            'items_count' => 1,
        ]);

        $user = User::createAccount([
            'name' => 'Reg User',
            'email' => 'reg@example.com',
            'password' => Hash::make('password'),
            'is_customer' => true,
        ]);

        $customer = Customer::query()->create(['user_id' => $user->id]);

        Order::query()->create([
            'order_number' => 'BNC-TEST-003',
            'tracking_token' => 'token-3',
            'status' => 'pending',
            'email' => 'reg@example.com',
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'first_name' => 'Reg',
            'last_name' => 'User',
            'phone' => '+38761111444',
            'address' => 'Ulica 2',
            'city' => 'Sarajevo',
            'postal_code' => '71000',
            'subtotal' => 75,
            'discount_total' => 0,
            'shipping_fee' => 0,
            'total' => 75,
            'shipping_method' => 'delivery',
            'payment_method' => 'pay_on_delivery',
            'items_count' => 1,
        ]);

        $contact = app(MarketingContactSyncService::class)->syncCustomer($customer);

        $this->assertSame(MarketingContact::TYPE_REGISTERED, $contact->type);
        $this->assertSame(2, $contact->orders_count);
        $this->assertSame('125.00', $contact->orders_total);
    }
}
