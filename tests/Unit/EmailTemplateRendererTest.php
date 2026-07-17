<?php

namespace Tests\Unit;

use App\Models\EmailTemplate;
use App\Models\Order;
use App\Services\Mail\EmailTemplateRenderer;
use App\Services\Mail\OrderEmailVariables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplateRendererTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_order_confirmation_template_with_variables(): void
    {
        EmailTemplate::query()->create([
            'slug' => 'order_confirmation_customer',
            'subject' => 'Potvrda {{order_number}}',
            'body_html' => '<p>Poštovani {{first_name}}, ukupno {{total}} {{currency}}</p>',
            'variables' => ['order_number', 'first_name', 'total', 'currency'],
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'order_number' => 'BNC-20260628-ABC123',
            'tracking_token' => 'token-123',
            'status' => 'nova',
            'first_name' => 'Amir',
            'last_name' => 'Amirovic',
            'phone' => '061234567',
            'email' => 'amir@test.test',
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

        $rendered = app(EmailTemplateRenderer::class)->render(
            'order_confirmation_customer',
            OrderEmailVariables::from($order),
        );

        $this->assertNotNull($rendered);
        $this->assertSame('Potvrda BNC-20260628-ABC123', $rendered['subject']);
        $this->assertStringContainsString('Poštovani Amir', $rendered['body']);
        $this->assertStringContainsString('105,00 KM', $rendered['body']);
    }
}
