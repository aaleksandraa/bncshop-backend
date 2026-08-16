<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSupplierOffer;
use App\Models\Supplier;
use App\Models\SupplierCategoryMarginRule;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceCalculatorSupplierMarginTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_price_uses_api_price_and_keeps_margin_metadata(): void
    {
        $category = Category::factory()->create();
        $supplier = Supplier::query()->create([
            'external_supplier_id' => 'supplier-3',
            'name' => 'comtrade',
            'display_name' => 'Comtrade',
            'code' => 'comtrade',
        ]);

        SupplierCategoryMarginRule::query()->create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'margin_percentage' => 30,
            'subcategory_scope' => 'all_descendants',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-price-1',
            'name' => 'Telefon',
            'slug' => 'telefon',
            'status' => 'active',
            'is_public' => true,
            'category_id' => $category->id,
            'api_price' => 809,
            'regular_price' => 809,
        ]);

        ProductSupplierOffer::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'supplier_sku' => 'CT-1',
            'supplier_price' => 559.2,
            'supplier_stock' => 0,
            'is_selected_price_source' => true,
        ]);

        $result = app(PriceCalculator::class)->calculate($product->fresh(['supplierOffers.supplier', 'category']));

        $this->assertSame(809.0, $result->regularPrice);
        $this->assertSame(559.2, $result->wholesalePrice);
        $this->assertSame(30.0, $result->appliedMargin);
        $this->assertSame('rule', $result->marginSource);
        $this->assertSame('Comtrade', $result->supplierName);
        $this->assertSame(809.0, $result->displayPrice);
    }

    public function test_fallback_price_includes_vat_when_api_price_missing(): void
    {
        $category = Category::factory()->create([
            'margin_percentage' => 30,
        ]);
        $supplier = Supplier::query()->create([
            'external_supplier_id' => 'supplier-no-api',
            'name' => 'uniexpert',
            'display_name' => 'Uniexpert',
            'code' => 'uniexpert',
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-no-api-1',
            'name' => 'Proizvod bez API cijene',
            'slug' => 'proizvod-bez-api-cijene',
            'status' => 'active',
            'is_public' => true,
            'category_id' => $category->id,
            'regular_price' => 0,
        ]);

        ProductSupplierOffer::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'supplier_sku' => 'UX-2',
            'supplier_price' => 92,
            'supplier_stock' => 5,
            'is_selected_price_source' => true,
        ]);

        $result = app(PriceCalculator::class)->calculate($product->fresh(['supplierOffers.supplier', 'category']));

        $this->assertSame(140.0, $result->regularPrice);
        $this->assertSame(140.0, $result->displayPrice);
    }

    public function test_display_price_uses_api_price_when_no_supplier_adjustment(): void
    {
        $category = Category::factory()->create([
            'margin_percentage' => 30,
        ]);
        $supplier = Supplier::query()->create([
            'external_supplier_id' => 'supplier-uniexpert',
            'name' => 'uniexpert',
            'display_name' => 'Uniexpert',
            'code' => 'uniexpert',
            'price_adjustment_amount' => 0,
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-display-api-1',
            'name' => 'Uniexpert proizvod',
            'slug' => 'uniexpert-proizvod',
            'status' => 'active',
            'is_public' => true,
            'category_id' => $category->id,
            'api_price' => 139,
            'api_final_price' => 139,
            'regular_price' => 139,
            'display_price' => 139,
        ]);

        ProductSupplierOffer::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'supplier_sku' => 'UX-1',
            'supplier_price' => 92,
            'supplier_stock' => 5,
            'is_selected_price_source' => true,
        ]);

        $result = app(PriceCalculator::class)->calculate($product->fresh(['supplierOffers.supplier', 'category']));

        $this->assertSame(139.0, $result->regularPrice);
        $this->assertSame(139.0, $result->displayPrice);
        $this->assertFalse($result->onSale);
    }

    public function test_locked_product_margin_calculates_wholesale_plus_margin_plus_vat(): void
    {
        $category = Category::factory()->create([
            'margin_percentage' => 30,
        ]);
        $supplier = Supplier::query()->create([
            'external_supplier_id' => 'supplier-manual-margin',
            'name' => 'uniexpert',
            'display_name' => 'Uniexpert',
            'code' => 'uniexpert',
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-manual-margin',
            'name' => 'Ručna marža',
            'slug' => 'rucna-marza',
            'status' => 'active',
            'is_public' => true,
            'category_id' => $category->id,
            'api_price' => 139,
            'api_final_price' => 139,
            'regular_price' => 139,
            'display_price' => 139,
            'margin_percentage' => 40,
        ]);

        ProductSupplierOffer::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'supplier_sku' => 'UX-3',
            'supplier_price' => 92,
            'supplier_stock' => 5,
            'is_selected_price_source' => true,
        ]);

        app(\App\Services\Sync\FieldLockService::class)->lockField($product, 'margin_percentage');

        $result = app(PriceCalculator::class)->calculate($product->fresh(['supplierOffers.supplier', 'category']));

        $this->assertSame(151.0, $result->regularPrice);
        $this->assertSame(151.0, $result->displayPrice);
        $this->assertSame(40.0, $result->appliedMargin);
        $this->assertSame('product', $result->marginSource);
    }

    public function test_locked_category_margin_calculates_wholesale_plus_margin_plus_vat(): void
    {
        $category = Category::factory()->create([
            'margin_percentage' => 25,
            'margin_locked' => true,
        ]);
        $supplier = Supplier::query()->create([
            'external_supplier_id' => 'supplier-cat-lock',
            'name' => 'asbis',
            'display_name' => 'Asbis',
            'code' => 'asbis',
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-cat-lock',
            'name' => 'Kategorija marža',
            'slug' => 'kategorija-marza',
            'status' => 'active',
            'is_public' => true,
            'category_id' => $category->id,
            'api_price' => 3429,
            'api_final_price' => 3429,
            'regular_price' => 3429,
            'display_price' => 3429,
        ]);

        ProductSupplierOffer::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'supplier_sku' => 'AS-1',
            'supplier_price' => 2399,
            'supplier_stock' => 2,
            'is_selected_price_source' => true,
        ]);

        $result = app(PriceCalculator::class)->calculate($product->fresh(['supplierOffers.supplier', 'category']));

        $this->assertSame(3509.0, $result->regularPrice);
        $this->assertSame(3509.0, $result->displayPrice);
        $this->assertSame(25.0, $result->appliedMargin);
        $this->assertSame('category', $result->marginSource);
    }

    public function test_locked_category_margin_rounds_sell_price_up_to_whole_km(): void
    {
        $category = Category::factory()->create([
            'margin_percentage' => 22,
            'margin_locked' => true,
        ]);
        $supplier = Supplier::query()->create([
            'external_supplier_id' => 'supplier-round-up',
            'name' => 'asbis',
            'display_name' => 'Asbis',
            'code' => 'asbis',
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-round-up',
            'name' => 'Zaokruživanje',
            'slug' => 'zaokruzivanje',
            'status' => 'active',
            'is_public' => true,
            'category_id' => $category->id,
            'api_price' => 1289,
            'api_final_price' => 1289,
            'regular_price' => 1096.78,
            'display_price' => 1096.78,
        ]);

        ProductSupplierOffer::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'supplier_sku' => 'AS-ROUND',
            'supplier_price' => 899,
            'supplier_stock' => 3,
            'is_selected_price_source' => true,
        ]);

        $calculator = app(PriceCalculator::class);
        $result = $calculator->calculate($product->fresh(['supplierOffers.supplier', 'category']));

        $this->assertSame(1284.0, $calculator->roundSellPrice(899 * 1.22 * 1.17));
        $this->assertSame(1284.0, $result->regularPrice);
        $this->assertSame(1284.0, $result->displayPrice);
    }
}
