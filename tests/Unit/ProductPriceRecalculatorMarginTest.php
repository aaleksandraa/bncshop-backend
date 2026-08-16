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
}
