<?php

namespace Tests\Feature\B2b;

use App\Models\B2bOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\B2b\Concerns\CreatesB2bCustomers;
use Tests\TestCase;

class B2bOrderIsolationTest extends TestCase
{
    use CreatesB2bCustomers;
    use RefreshDatabase;

    public function test_customer_cannot_list_other_customers_orders(): void
    {
        [, $customerA] = $this->createB2bUser('iso-a@test.test');
        [, $customerB] = $this->createB2bUser('iso-b@test.test');

        B2bOrder::query()->create([
            'b2b_customer_id' => $customerA->id,
            'order_number' => 'B2B-ISO-A',
            'status' => 'nova',
            'payment_method' => 'invoice',
            'company_name' => $customerA->company_name,
            'company_address' => $customerA->company_address,
            'jib' => $customerA->jib,
            'contact_name' => 'A',
            'contact_email' => 'iso-a@test.test',
            'contact_phone' => '061111111',
            'shipping_address' => 'Adresa A',
            'subtotal' => 100,
            'discount_total' => 0,
            'total' => 100,
        ]);

        B2bOrder::query()->create([
            'b2b_customer_id' => $customerB->id,
            'order_number' => 'B2B-ISO-B',
            'status' => 'nova',
            'payment_method' => 'invoice',
            'company_name' => $customerB->company_name,
            'company_address' => $customerB->company_address,
            'jib' => $customerB->jib,
            'contact_name' => 'B',
            'contact_email' => 'iso-b@test.test',
            'contact_phone' => '061222222',
            'shipping_address' => 'Adresa B',
            'subtotal' => 200,
            'discount_total' => 0,
            'total' => 200,
        ]);

        $this->loginB2bUser($customerB->user);

        $this->getJsonStateful('/api/v1/b2b/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_number', 'B2B-ISO-B')
            ->assertJsonMissingPath('data.0.items')
            ->assertJsonMissingPath('data.0.shipping_address')
            ->assertJsonMissingPath('data.0.jib');
    }

    public function test_customer_cannot_download_other_customers_invoice(): void
    {
        [, $customerA] = $this->createB2bUser('inv-a@test.test');
        [, $customerB] = $this->createB2bUser('inv-b@test.test');

        $order = B2bOrder::query()->create([
            'b2b_customer_id' => $customerA->id,
            'order_number' => 'B2B-INV-A',
            'status' => 'nova',
            'payment_method' => 'invoice',
            'company_name' => $customerA->company_name,
            'company_address' => $customerA->company_address,
            'jib' => $customerA->jib,
            'contact_name' => 'A',
            'contact_email' => 'inv-a@test.test',
            'contact_phone' => '061111111',
            'shipping_address' => 'Adresa A',
            'subtotal' => 100,
            'discount_total' => 0,
            'total' => 100,
        ]);

        $this->loginB2bUser($customerB->user);

        $this->getJsonStateful("/api/v1/b2b/orders/{$order->id}/invoice")
            ->assertNotFound();
    }
}
