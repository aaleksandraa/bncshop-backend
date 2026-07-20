<?php

namespace Tests\Feature\B2b;

use App\Models\B2bCustomer;
use App\Models\B2bOrder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class B2bOrderInvoiceDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_b2b_admin_can_download_order_invoice_via_get_route(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::createAccount([
            'name' => 'B2B Admin',
            'email' => 'b2badmin@test.test',
            'password' => Hash::make('password123'),
        ]);
        $admin->assignRole(Role::findByName('B2B Admin'));

        $customer = B2bCustomer::query()->create([
            'user_id' => User::createAccount([
                'name' => 'B2B Customer',
                'email' => 'customer@test.test',
                'password' => Hash::make('password123'),
                'is_b2b_customer' => true,
            ])->id,
            'company_name' => 'Test d.o.o.',
            'company_address' => 'Adresa 1',
            'jib' => '1234567890123',
            'phone' => '061111111',
            'is_active' => true,
            'discount_percent' => 5,
        ]);

        $order = B2bOrder::query()->create([
            'b2b_customer_id' => $customer->id,
            'order_number' => 'B2B-2026-00099',
            'status' => 'nova',
            'payment_method' => 'invoice',
            'company_name' => 'Test d.o.o.',
            'company_address' => 'Adresa 1',
            'jib' => $customer->jib,
            'contact_name' => 'Kontakt',
            'contact_email' => 'customer@test.test',
            'contact_phone' => '061111111',
            'shipping_address' => 'Adresa 1',
            'subtotal' => 100,
            'discount_total' => 0,
            'shipping_fee' => 0,
            'total' => 100,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('filament.b2b-admin.b2b-orders.invoice', $order));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_b2b_admin_can_download_stored_invoice_file(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::createAccount([
            'name' => 'B2B Admin',
            'email' => 'b2badmin-stored@test.test',
            'password' => Hash::make('password123'),
        ]);
        $admin->assignRole(Role::findByName('B2B Admin'));

        $customer = B2bCustomer::query()->create([
            'user_id' => User::createAccount([
                'name' => 'B2B Customer',
                'email' => 'customer-stored@test.test',
                'password' => Hash::make('password123'),
                'is_b2b_customer' => true,
            ])->id,
            'company_name' => 'Stored d.o.o.',
            'company_address' => 'Adresa 1',
            'jib' => '1234567890124',
            'phone' => '061111111',
            'is_active' => true,
            'discount_percent' => 5,
        ]);

        $order = B2bOrder::query()->create([
            'b2b_customer_id' => $customer->id,
            'order_number' => 'B2B-2026-00100',
            'status' => 'nova',
            'payment_method' => 'invoice',
            'company_name' => 'Stored d.o.o.',
            'company_address' => 'Adresa 1',
            'jib' => $customer->jib,
            'contact_name' => 'Kontakt',
            'contact_email' => 'customer-stored@test.test',
            'contact_phone' => '061111111',
            'shipping_address' => 'Adresa 1',
            'subtotal' => 100,
            'discount_total' => 0,
            'shipping_fee' => 0,
            'total' => 100,
        ]);

        $invoicePath = app(\App\Services\B2b\B2bOrderInvoicePdf::class)->generateAndStore($order);

        $response = $this->actingAs($admin)
            ->get(route('filament.b2b-admin.b2b-orders.invoice', $order->fresh()));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertSame($invoicePath, $order->fresh()->invoice_path);
    }
}
