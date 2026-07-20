<?php

namespace Tests\Unit;

use App\Models\B2bCustomer;
use App\Models\B2bOrder;
use App\Models\B2bOrderItem;
use App\Models\User;
use App\Support\B2bInvoiceVat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class B2bInvoiceVatTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_vat_breakdown_for_order(): void
    {
        $customer = B2bCustomer::query()->create([
            'user_id' => User::createAccount([
                'name' => 'B2B Customer',
                'email' => 'customer-vat@test.test',
                'password' => Hash::make('password123'),
                'is_b2b_customer' => true,
            ])->id,
            'company_name' => 'Test d.o.o.',
            'company_address' => 'Adresa 1',
            'jib' => '1234567890123',
            'phone' => '061111111',
            'is_active' => true,
            'discount_percent' => 0,
        ]);

        $order = B2bOrder::query()->create([
            'b2b_customer_id' => $customer->id,
            'order_number' => 'B2B-2026-00200',
            'status' => 'nova',
            'payment_method' => 'invoice',
            'company_name' => 'Test d.o.o.',
            'company_address' => 'Adresa 1',
            'jib' => $customer->jib,
            'contact_name' => 'Kontakt',
            'contact_email' => 'customer-vat@test.test',
            'contact_phone' => '061111111',
            'shipping_address' => 'Adresa 1',
            'subtotal' => 200,
            'discount_total' => 20,
            'shipping_fee' => 10,
            'total' => 190,
        ]);

        B2bOrderItem::query()->create([
            'b2b_order_id' => $order->id,
            'product_name' => 'Laptop',
            'product_sku' => 'LAP-1',
            'quantity' => 2,
            'unit_regular_price' => 100,
            'unit_final_price' => 90,
            'line_total' => 180,
            'customer_discount_percent' => 10,
        ]);

        $vat = B2bInvoiceVat::forOrder($order->fresh());

        $this->assertSame(17.0, $vat['rate_percent']);
        $this->assertSame(180.0, $vat['items_net']);
        $this->assertSame(10.0, $vat['shipping_net']);
        $this->assertSame(190.0, $vat['net_total']);
        $this->assertSame(32.3, $vat['vat_total']);
        $this->assertSame(222.3, $vat['gross_total']);
        $this->assertSame(30.6, $vat['lines'][0]['vat_amount']);
        $this->assertSame(210.6, $vat['lines'][0]['line_gross']);
    }
}
