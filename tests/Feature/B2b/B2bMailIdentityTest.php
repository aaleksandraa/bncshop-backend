<?php

namespace Tests\Feature\B2b;

use App\Mail\B2b\B2bOrderConfirmationCustomer;
use App\Models\B2bOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\B2b\Concerns\CreatesB2bCustomers;
use Tests\TestCase;

class B2bMailIdentityTest extends TestCase
{
    use CreatesB2bCustomers;
    use RefreshDatabase;

    public function test_b2b_mailable_uses_global_from_and_b2b_reply_to(): void
    {
        config([
            'mail.from.address' => 'info@bncshop.ba',
            'mail.from.name' => 'BNC Shop',
            'b2b.mail.from_address' => 'b2b@bncshop.ba',
            'b2b.mail.from_name' => 'BNC B2B',
            'b2b.mail.use_global_from' => true,
        ]);

        [, $customer] = $this->createB2bUser();

        $order = B2bOrder::query()->create([
            'order_number' => 'B2B-TEST-001',
            'b2b_customer_id' => $customer->id,
            'status' => 'nova',
            'payment_method' => 'invoice',
            'company_name' => 'Test d.o.o.',
            'company_address' => 'Adresa 1',
            'jib' => '1234567890123',
            'contact_name' => 'Kupac',
            'contact_email' => 'kupac@test.test',
            'contact_phone' => '061111111',
            'shipping_address' => 'Dostava 1',
            'subtotal' => 100,
            'discount_total' => 0,
            'shipping_fee' => 10,
            'total' => 110,
        ]);

        $envelope = (new B2bOrderConfirmationCustomer($order))->envelope();

        $this->assertSame('info@bncshop.ba', $envelope->from->address);
        $this->assertSame('BNC B2B', $envelope->from->name);
        $this->assertSame('b2b@bncshop.ba', $envelope->replyTo[0]->address);
        $this->assertSame('BNC B2B', $envelope->replyTo[0]->name);
    }

    public function test_admin_notification_email_uses_b2b_env_fallback(): void
    {
        config([
            'b2b.mail.admin_notification_email' => 'b2b@bncshop.ba',
            'bnc.admin_notification_email' => 'info@bncshop.ba',
        ]);

        $this->assertSame('b2b@bncshop.ba', \App\Models\B2bSetting::adminNotificationEmail());
    }
}
