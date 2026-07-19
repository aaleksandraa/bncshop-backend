<?php

namespace Tests\Unit;

use App\Models\SystemSetting;
use App\Services\Catalog\ProductPageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPageSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_to_showing_short_description(): void
    {
        $settings = app(ProductPageSettings::class);

        $this->assertTrue($settings->showShortDescription());
        $this->assertFalse($settings->showMessagingOrderButtons());
        $this->assertSame(
            [
                'show_short_description' => true,
                'show_messaging_order_buttons' => false,
            ],
            $settings->publicPayload(),
        );
    }

    public function test_can_disable_short_description(): void
    {
        $settings = app(ProductPageSettings::class);

        $settings->save(['show_short_description' => false]);

        $this->assertFalse($settings->showShortDescription());

        $stored = SystemSetting::query()->where('key', 'product_page')->first();

        $this->assertNotNull($stored);
        $this->assertSame('shop', $stored->group);
        $this->assertFalse($stored->value['show_short_description']);
    }

    public function test_can_enable_messaging_order_buttons(): void
    {
        $settings = app(ProductPageSettings::class);

        $settings->save(['show_messaging_order_buttons' => true]);

        $this->assertTrue($settings->showMessagingOrderButtons());
        $this->assertTrue($settings->publicPayload()['show_messaging_order_buttons']);
    }
}
