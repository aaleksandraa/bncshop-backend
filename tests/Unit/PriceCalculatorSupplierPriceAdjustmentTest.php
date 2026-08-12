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

class PriceCalculatorSupplierPriceAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_price_adjustment_is_added_on_top_of_api_price(): void
    {
        $category = Category::factory()->create();
        $supplier = Supplier::query()->create([
            'external_supplier_id' => 'supplier-startech',
            'name' => 'startech',
            'display_name' => 'Startech',
            'code' => 'startech',
            'price_adjustment_amount' => 20,
        ]);

        SupplierCategoryMarginRule::query()->create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'margin_percentage' => 30,
            'subcategory_scope' => 'all_descendants',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-adjust-1',
            'name' => 'Startech proizvod',
            'slug' => 'startech-proizvod',
            'status' => 'active',
            'is_public' => true,
            'category_id' => $category->id,
            'api_price' => 100,
            'api_final_price' => 100,
            'regular_price' => 100,
            'display_price' => 100,
        ]);

        ProductSupplierOffer::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'supplier_sku' => 'ST-1',
            'supplier_price' => 559.2,
            'supplier_stock' => 5,
            'is_selected_price_source' => true,
        ]);

        $result = app(PriceCalculator::class)->calculate($product->fresh(['supplierOffers.supplier', 'category']));

        $this->assertSame(120.0, $result->regularPrice);
        $this->assertSame(120.0, $result->displayPrice);
        $this->assertFalse($result->onSale);
        $this->assertSame(20.0, $result->appliedPriceAdjustment);
        $this->assertSame('Startech', $result->supplierName);
    }

    public function test_supplier_price_adjustment_applies_to_api_final_price_when_on_sale(): void
    {
        $category = Category::factory()->create();
        $supplier = Supplier::query()->create([
            'external_supplier_id' => 'supplier-startech-sale',
            'name' => 'startech',
            'display_name' => 'Startech',
            'code' => 'startech',
            'price_adjustment_amount' => 20,
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-adjust-sale',
            'name' => 'Startech akcija',
            'slug' => 'startech-akcija',
            'status' => 'active',
            'is_public' => true,
            'category_id' => $category->id,
            'api_price' => 120,
            'api_final_price' => 100,
            'api_rebate' => 20,
            'api_rebate_valid_until' => now()->addWeek(),
            'regular_price' => 120,
            'display_price' => 100,
        ]);

        ProductSupplierOffer::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'supplier_price' => 50,
            'supplier_stock' => 5,
            'is_selected_price_source' => true,
        ]);

        $result = app(PriceCalculator::class)->calculate($product->fresh(['supplierOffers.supplier', 'category']));

        $this->assertSame(140.0, $result->regularPrice);
        $this->assertSame(120.0, $result->displayPrice);
        $this->assertTrue($result->onSale);
        $this->assertSame('api', $result->discountSource);
        $this->assertSame(20.0, $result->appliedPriceAdjustment);
    }

    public function test_zero_price_adjustment_uses_margin_for_regular_price(): void
    {
        $category = Category::factory()->create();
        $supplier = Supplier::query()->create([
            'external_supplier_id' => 'supplier-zero',
            'name' => 'comtrade',
            'display_name' => 'Comtrade',
            'code' => 'comtrade',
            'price_adjustment_amount' => 0,
        ]);

        SupplierCategoryMarginRule::query()->create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'margin_percentage' => 30,
            'subcategory_scope' => 'all_descendants',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-adjust-2',
            'name' => 'Telefon',
            'slug' => 'telefon-adjust',
            'status' => 'active',
            'is_public' => true,
            'category_id' => $category->id,
            'api_price' => 809,
            'regular_price' => 809,
        ]);

        ProductSupplierOffer::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'supplier_price' => 559.2,
            'supplier_stock' => 0,
            'is_selected_price_source' => true,
        ]);

        $result = app(PriceCalculator::class)->calculate($product->fresh(['supplierOffers.supplier', 'category']));

        $this->assertSame(726.96, $result->regularPrice);
        $this->assertNull($result->appliedPriceAdjustment);
    }

    public function test_price_locked_product_ignores_supplier_price_adjustment(): void
    {
        $supplier = Supplier::query()->create([
            'external_supplier_id' => 'supplier-locked',
            'name' => 'startech',
            'display_name' => 'Startech',
            'code' => 'startech',
            'price_adjustment_amount' => 20,
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-adjust-3',
            'name' => 'Zaključan proizvod',
            'slug' => 'zakljucan-proizvod',
            'status' => 'active',
            'is_public' => true,
            'price_locked' => true,
            'manual_price' => 500,
            'regular_price' => 500,
            'display_price' => 500,
        ]);

        ProductSupplierOffer::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'supplier_price' => 559.2,
            'supplier_stock' => 5,
            'is_selected_price_source' => true,
        ]);

        $result = app(PriceCalculator::class)->calculate($product->fresh(['supplierOffers.supplier']));

        $this->assertSame(500.0, $result->regularPrice);
        $this->assertNull($result->appliedPriceAdjustment);
    }

    public function test_price_adjustment_applies_only_for_selected_supplier(): void
    {
        $category = Category::factory()->create();

        $startech = Supplier::query()->create([
            'external_supplier_id' => 'supplier-startech-2',
            'name' => 'startech',
            'display_name' => 'Startech',
            'code' => 'startech',
            'price_adjustment_amount' => 20,
        ]);

        $comtrade = Supplier::query()->create([
            'external_supplier_id' => 'supplier-comtrade-2',
            'name' => 'comtrade',
            'display_name' => 'Comtrade',
            'code' => 'comtrade',
            'price_adjustment_amount' => 0,
        ]);

        SupplierCategoryMarginRule::query()->create([
            'supplier_id' => $comtrade->id,
            'category_id' => $category->id,
            'margin_percentage' => 30,
            'subcategory_scope' => 'all_descendants',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-adjust-4',
            'name' => 'Multi supplier proizvod',
            'slug' => 'multi-supplier-proizvod',
            'status' => 'active',
            'is_public' => true,
            'category_id' => $category->id,
            'preferred_supplier_id' => $comtrade->id,
            'api_price' => 809,
            'regular_price' => 809,
        ]);

        ProductSupplierOffer::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $startech->id,
            'supplier_price' => 100,
            'supplier_stock' => 10,
            'is_selected_price_source' => true,
        ]);

        ProductSupplierOffer::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $comtrade->id,
            'supplier_price' => 559.2,
            'supplier_stock' => 5,
            'is_selected_price_source' => false,
        ]);

        $result = app(PriceCalculator::class)->calculate($product->fresh(['supplierOffers.supplier', 'category']));

        $this->assertSame(726.96, $result->regularPrice);
        $this->assertNull($result->appliedPriceAdjustment);
        $this->assertSame('Comtrade', $result->supplierName);
    }

    public function test_display_price_matches_api_final_plus_adjustment_without_fake_sale(): void
    {
        $category = Category::factory()->create();
        $supplier = Supplier::query()->create([
            'external_supplier_id' => 'supplier-display',
            'name' => 'startech',
            'display_name' => 'Startech',
            'code' => 'startech',
            'price_adjustment_amount' => 20,
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-display-1',
            'name' => 'Filter proizvod',
            'slug' => 'filter-proizvod',
            'status' => 'active',
            'is_public' => true,
            'category_id' => $category->id,
            'api_price' => 109.00,
            'api_final_price' => 109.00,
            'regular_price' => 109.00,
            'display_price' => 109.00,
        ]);

        ProductSupplierOffer::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'supplier_price' => 69.00,
            'supplier_stock' => 5,
            'is_selected_price_source' => true,
        ]);

        $result = app(PriceCalculator::class)->calculate($product->fresh(['supplierOffers.supplier', 'category']));

        $this->assertSame(129.0, $result->regularPrice);
        $this->assertSame(129.0, $result->displayPrice);
        $this->assertFalse($result->onSale);
        $this->assertSame(20.0, $result->appliedPriceAdjustment);
    }
}
