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

    public function test_regular_price_is_calculated_from_supplier_price_and_margin_rule(): void
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

        $this->assertSame(726.96, $result->regularPrice);
        $this->assertSame(559.2, $result->wholesalePrice);
        $this->assertSame(30.0, $result->appliedMargin);
        $this->assertSame('rule', $result->marginSource);
        $this->assertSame('Comtrade', $result->supplierName);
    }
}
