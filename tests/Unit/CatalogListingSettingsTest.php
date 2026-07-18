<?php

namespace Tests\Unit;

use App\Models\SystemSetting;
use App\Services\Catalog\CatalogListingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogListingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_to_hiding_out_of_stock_refurbished_eline_products(): void
    {
        $settings = app(CatalogListingSettings::class);

        $this->assertTrue($settings->hideOutOfStockRefurbishedEline());
    }

    public function test_can_disable_hiding_out_of_stock_refurbished_eline_products(): void
    {
        $settings = app(CatalogListingSettings::class);

        $settings->save(['hide_out_of_stock_refurbished_eline' => false]);

        $this->assertFalse($settings->hideOutOfStockRefurbishedEline());

        $stored = SystemSetting::query()->where('key', 'catalog_listing')->first();

        $this->assertNotNull($stored);
        $this->assertSame('shop', $stored->group);
        $this->assertFalse($stored->value['hide_out_of_stock_refurbished_eline']);
    }
}
