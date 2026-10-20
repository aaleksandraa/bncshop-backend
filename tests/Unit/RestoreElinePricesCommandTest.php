<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\Pricing\ProductSalePriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestoreElinePricesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_refurbished_eline_prices_return_to_api_price(): void
    {
        $product = Product::factory()->create([
            'import_source' => 'eline',
            'is_refurbished' => true,
            'eline_sifra' => 'EL-179',
            'api_price' => 179,
            'api_final_price' => 179,
            'regular_price' => 169,
            'display_price' => 169,
            'calculated_price' => 169,
            'price_locked' => true,
            'manual_price' => 199,
        ]);

        $manual = Product::factory()->create([
            'import_source' => 'manual',
            'is_refurbished' => true,
            'regular_price' => 80,
            'display_price' => 80,
        ]);

        $newEline = Product::factory()->create([
            'import_source' => 'eline',
            'is_refurbished' => false,
            'api_price' => 300,
            'regular_price' => 289,
            'display_price' => 289,
        ]);

        $this->artisan('bnc:eline-restore-prices')
            ->assertSuccessful();

        $fresh = $product->fresh();
        $this->assertSame(179.0, (float) $fresh->regular_price);
        $this->assertSame(179.0, (float) $fresh->display_price);
        $this->assertFalse($fresh->on_sale);
        $this->assertFalse($fresh->price_locked);
        $this->assertNull($fresh->manual_price);
        $this->assertNull($fresh->calculated_price);

        $this->assertSame(80.0, (float) $manual->fresh()->display_price);
        $this->assertSame(289.0, (float) $newEline->fresh()->display_price);
    }

    public function test_seller_sale_stays_exact_below_restored_eline_price(): void
    {
        $product = Product::factory()->create([
            'import_source' => 'eline',
            'is_refurbished' => true,
            'api_price' => 250,
            'regular_price' => 169,
            'display_price' => 169,
        ]);

        app(ProductSalePriceService::class)->upsert($product, 179);

        $this->artisan('bnc:eline-restore-prices')->assertSuccessful();

        $fresh = $product->fresh();
        $this->assertSame(250.0, (float) $fresh->regular_price);
        $this->assertSame(179.0, (float) $fresh->display_price);
        $this->assertTrue($fresh->on_sale);
    }

    public function test_keep_locked_and_dry_run_do_not_overwrite(): void
    {
        $locked = Product::factory()->create([
            'import_source' => 'eline',
            'is_refurbished' => true,
            'api_price' => 179,
            'regular_price' => 199,
            'display_price' => 199,
            'price_locked' => true,
            'manual_price' => 199,
        ]);

        $this->artisan('bnc:eline-restore-prices', ['--keep-locked' => true])
            ->assertSuccessful();

        $this->assertSame(199.0, (float) $locked->fresh()->display_price);
        $this->assertTrue($locked->fresh()->price_locked);

        $charm = Product::factory()->create([
            'import_source' => 'eline',
            'is_refurbished' => true,
            'api_price' => 410,
            'regular_price' => 399,
            'display_price' => 399,
        ]);

        $this->artisan('bnc:eline-restore-prices', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(399.0, (float) $charm->fresh()->display_price);
    }
}
