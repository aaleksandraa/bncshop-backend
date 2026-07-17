<?php

namespace Tests\Feature\B2b;

use App\Models\B2bCart;
use App\Models\B2bCartItem;
use App\Models\B2bCategory;
use App\Models\B2bCustomer;
use App\Models\B2bOrder;
use App\Models\B2bProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class B2bAuthTest extends TestCase
{
    use RefreshDatabase;

    private function createB2bUser(string $email = 'b2b@test.test'): array
    {
        $user = User::createAccount([
            'name' => 'B2B User',
            'email' => $email,
            'password' => Hash::make('password123'),
            'is_b2b_customer' => true,
        ]);

        $customer = B2bCustomer::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Test d.o.o.',
            'company_address' => 'Adresa 1',
            'jib' => substr(str_pad((string) random_int(1, 9999999999999), 13, '0', STR_PAD_LEFT), 0, 13),
            'phone' => '061111111',
            'is_active' => true,
            'discount_percent' => 5,
        ]);

        return [$user, $customer];
    }

    public function test_b2b_customer_can_login_and_access_me(): void
    {
        [$user] = $this->createB2bUser();

        $this->postJsonStateful('/api/v1/b2b/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonPath('data.user.company_name', 'Test d.o.o.');

        $this->getJsonStateful('/api/v1/b2b/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_retail_customer_cannot_access_b2b_routes(): void
    {
        $user = User::createAccount([
            'name' => 'Retail',
            'email' => 'retail@test.test',
            'password' => Hash::make('password123'),
            'is_customer' => true,
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/b2b/auth/me')
            ->assertForbidden();
    }

    public function test_b2b_order_isolation_between_customers(): void
    {
        [, $customerA] = $this->createB2bUser('a@test.test');
        [, $customerB] = $this->createB2bUser('b@test.test');

        $order = B2bOrder::query()->create([
            'b2b_customer_id' => $customerA->id,
            'order_number' => 'B2B-2026-00001',
            'status' => 'nova',
            'payment_method' => 'invoice',
            'company_name' => $customerA->company_name,
            'company_address' => $customerA->company_address,
            'jib' => $customerA->jib,
            'contact_name' => 'A',
            'contact_email' => 'a@test.test',
            'contact_phone' => '061111111',
            'shipping_address' => 'Adresa',
            'subtotal' => 100,
            'discount_total' => 0,
            'total' => 100,
        ]);

        $this->postJsonStateful('/api/v1/b2b/auth/login', [
            'email' => 'b@test.test',
            'password' => 'password123',
        ])->assertOk();

        $this->getJsonStateful("/api/v1/b2b/orders/{$order->id}")
            ->assertNotFound();
    }
}
