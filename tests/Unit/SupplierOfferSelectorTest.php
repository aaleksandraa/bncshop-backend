<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductSupplierOffer;
use App\Models\Supplier;
use App\Services\Pricing\SupplierOfferSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierOfferSelectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_preferred_supplier_is_selected_first(): void
    {
        $preferred = Supplier::query()->create([
            'external_supplier_id' => 'supplier-pref',
            'name' => 'arbis',
            'display_name' => 'Arbis',
        ]);

        $other = Supplier::query()->create([
            'external_supplier_id' => 'supplier-other',
            'name' => 'comtrade',
            'display_name' => 'Comtrade',
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-select-1',
            'name' => 'Proizvod',
            'slug' => 'proizvod',
            'status' => 'active',
            'is_public' => true,
            'preferred_supplier_id' => $preferred->id,
        ]);

        ProductSupplierOffer::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $other->id,
            'supplier_price' => 100,
            'supplier_stock' => 5,
            'is_selected_price_source' => true,
        ]);

        ProductSupplierOffer::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $preferred->id,
            'supplier_price' => 200,
            'supplier_stock' => 0,
            'is_selected_price_source' => false,
        ]);

        $selected = app(SupplierOfferSelector::class)->select($product->fresh('supplierOffers'));

        $this->assertSame($preferred->id, $selected?->supplier_id);
    }

    public function test_selected_price_source_is_used_when_no_preference(): void
    {
        $selectedSupplier = Supplier::query()->create([
            'external_supplier_id' => 'supplier-selected',
            'name' => 'comtrade',
        ]);

        $otherSupplier = Supplier::query()->create([
            'external_supplier_id' => 'supplier-other-2',
            'name' => 'arbis',
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-select-2',
            'name' => 'Proizvod 2',
            'slug' => 'proizvod-2',
            'status' => 'active',
            'is_public' => true,
        ]);

        ProductSupplierOffer::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $otherSupplier->id,
            'supplier_price' => 50,
            'supplier_stock' => 10,
            'is_selected_price_source' => false,
        ]);

        ProductSupplierOffer::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $selectedSupplier->id,
            'supplier_price' => 500,
            'supplier_stock' => 0,
            'is_selected_price_source' => true,
        ]);

        $selected = app(SupplierOfferSelector::class)->select($product->fresh('supplierOffers'));

        $this->assertSame($selectedSupplier->id, $selected?->supplier_id);
    }
}
