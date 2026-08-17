<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSupplierOffer;
use App\Models\Supplier;
use App\Services\Pricing\ProductPriceRecalculator;
use App\Services\Sync\FieldLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPriceRecalculatorMarginTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_recalc_updates_unlocked_product_margins_and_keeps_locked(): void
    {
        $category = Category::factory()->create([
            'margin_percentage' => 22,
        ]);
        $supplier = Supplier::query()->create([
            'external_supplier_id' => 'supplier-cat-recalc',
            'name' => 'asbis',
            'display_name' => 'Asbis',
            'code' => 'asbis-cat-recalc',
        ]);

        $unlocked = Product::query()->create([
            'external_product_id' => 'prod-cat-unlocked',
            'name' => 'Naslijeđena marža',
            'slug' => 'naslijedjena-marza',
            'status' => 'active',
            'is_public' => true,
            'category_id' => $category->id,
            'api_price' => 1289,
            'regular_price' => 1096.78,
        ]);
        $locked = Product::query()->create([
            'external_product_id' => 'prod-cat-locked',
            'name' => 'Ručna marža',
            'slug' => 'rucna-marza-cat',
            'status' => 'active',
            'is_public' => true,
            'category_id' => $category->id,
            'api_price' => 1289,
            'regular_price' => 1096.78,
            'margin_percentage' => 40,
        ]);

        foreach ([$unlocked, $locked] as $product) {
            ProductSupplierOffer::query()->create([
                'product_id' => $product->id,
                'supplier_id' => $supplier->id,
                'supplier_sku' => 'AS-'.$product->id,
                'supplier_price' => 899,
                'supplier_stock' => 3,
                'is_selected_price_source' => true,
            ]);
        }

        app(FieldLockService::class)->lockField($locked, 'margin_percentage');

        $category->update(['margin_percentage' => 30]);

        $count = app(ProductPriceRecalculator::class)->forAll(null, $category->id);

        $this->assertSame(2, $count);
        $this->assertSame(30.0, (float) $unlocked->fresh()->margin_percentage);
        $this->assertSame(40.0, (float) $locked->fresh()->margin_percentage);
        $this->assertSame(1368.0, (float) $unlocked->fresh()->regular_price);
        $this->assertSame(1473.0, (float) $locked->fresh()->regular_price);
    }

    public function test_recalculate_prices_command_fixes_a_single_product(): void
    {
        $category = Category::factory()->create([
            'margin_percentage' => 22,
        ]);
        $supplier = Supplier::query()->create([
            'external_supplier_id' => 'supplier-cmd-one',
            'name' => 'asbis',
            'display_name' => 'Asbis',
            'code' => 'asbis-cmd-one',
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-cmd-one',
            'name' => 'Jedan proizvod',
            'slug' => 'jedan-proizvod',
            'status' => 'active',
            'is_public' => true,
            'category_id' => $category->id,
            'api_price' => 789,
            'regular_price' => 672.46,
        ]);

        ProductSupplierOffer::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'supplier_sku' => 'AS-ONE',
            'supplier_price' => 551.20,
            'supplier_stock' => 3,
            'is_selected_price_source' => true,
        ]);

        $this->artisan('bnc:recalculate-prices', ['--product' => (string) $product->id])
            ->assertSuccessful();

        $fresh = $product->fresh();

        $this->assertSame(787.0, (float) $fresh->regular_price);
        $this->assertSame(22.0, (float) $fresh->margin_percentage);
    }

    public function test_recalculate_skips_eline_products(): void
    {
        $category = Category::factory()->create([
            'margin_percentage' => 22,
        ]);
        $supplier = Supplier::query()->create([
            'external_supplier_id' => 'supplier-eline-skip',
            'name' => 'asbis',
            'display_name' => 'Asbis',
            'code' => 'asbis-eline-skip',
        ]);

        $eline = Product::query()->create([
            'external_product_id' => 'prod-eline-skip',
            'name' => 'eLine proizvod',
            'slug' => 'eline-proizvod',
            'status' => 'active',
            'is_public' => true,
            'import_source' => 'eline',
            'category_id' => $category->id,
            'api_price' => 450,
            'regular_price' => 450,
            'display_price' => 450,
        ]);
        $a1 = Product::query()->create([
            'external_product_id' => 'prod-a1-keep',
            'name' => 'A1 proizvod',
            'slug' => 'a1-proizvod',
            'status' => 'active',
            'is_public' => true,
            'import_source' => 'a1',
            'category_id' => $category->id,
            'api_price' => 789,
            'regular_price' => 672.46,
        ]);

        foreach ([$eline, $a1] as $product) {
            ProductSupplierOffer::query()->create([
                'product_id' => $product->id,
                'supplier_id' => $supplier->id,
                'supplier_sku' => 'AS-'.$product->id,
                'supplier_price' => 551.20,
                'supplier_stock' => 3,
                'is_selected_price_source' => true,
            ]);
        }

        $count = app(ProductPriceRecalculator::class)->forAll();

        $this->assertSame(1, $count);
        $this->assertSame(450.0, (float) $eline->fresh()->regular_price);
        $this->assertSame(787.0, (float) $a1->fresh()->regular_price);
    }

    public function test_recalculate_prices_command_skips_eline_product(): void
    {
        $product = Product::query()->create([
            'external_product_id' => 'prod-eline-cmd',
            'name' => 'eLine komanda',
            'slug' => 'eline-komanda',
            'status' => 'active',
            'is_public' => true,
            'import_source' => 'eline',
            'api_price' => 450,
            'regular_price' => 450,
        ]);

        $this->artisan('bnc:recalculate-prices', ['--product' => (string) $product->id])
            ->assertSuccessful();

        $this->assertSame(450.0, (float) $product->fresh()->regular_price);
    }

    public function test_recalculate_prices_dry_run_does_not_write(): void
    {
        $category = Category::factory()->create([
            'margin_percentage' => 22,
        ]);
        $supplier = Supplier::query()->create([
            'external_supplier_id' => 'supplier-cmd-dry',
            'name' => 'asbis',
            'display_name' => 'Asbis',
            'code' => 'asbis-cmd-dry',
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-cmd-dry',
            'name' => 'Dry run proizvod',
            'slug' => 'dry-run-proizvod',
            'status' => 'active',
            'is_public' => true,
            'category_id' => $category->id,
            'api_price' => 789,
            'regular_price' => 672.46,
        ]);

        ProductSupplierOffer::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'supplier_sku' => 'AS-DRY',
            'supplier_price' => 551.20,
            'supplier_stock' => 3,
            'is_selected_price_source' => true,
        ]);

        $this->artisan('bnc:recalculate-prices', [
            '--dry-run' => true,
            '--product' => (string) $product->id,
        ])->assertSuccessful();

        $fresh = $product->fresh();

        $this->assertSame(672.46, (float) $fresh->regular_price);
        $this->assertNull($fresh->margin_percentage);
    }
}
