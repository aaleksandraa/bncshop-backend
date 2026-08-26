<?php

namespace Tests\Unit;

use App\Models\Discount;
use App\Models\Product;
use App\Services\Eline\ElineProductImporter;
use App\Services\Eline\ElineSupport;
use App\Services\Pricing\DiscountEngine;
use App\Services\Pricing\PriceCalculator;
use App\Services\Pricing\ProductSalePriceService;
use App\Models\ApiSource;
use App\Models\Category;
use App\Models\ElineCategory;
use App\Models\ElineCategoryMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSalePriceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_upsert_creates_discount_with_target_sale_price(): void
    {
        $product = Product::factory()->create([
            'regular_price' => 500,
            'display_price' => 500,
            'api_price' => null,
        ]);

        app(ProductSalePriceService::class)->upsert(
            $product,
            450,
            ProductSalePriceService::VALIDITY_NO_END,
        );

        $discount = Discount::query()->where('product_id', $product->id)->first();

        $this->assertNotNull($discount);
        $this->assertTrue($discount->is_active);
        $this->assertSame(50.0, (float) $discount->value);
        $this->assertSame(450.0, (float) $discount->conditions_json['sale_price']);
    }

    public function test_sync_keeps_target_sale_price_when_regular_price_changes(): void
    {
        $product = Product::factory()->create([
            'import_source' => 'eline',
            'regular_price' => 500,
            'api_price' => 500,
            'display_price' => 500,
        ]);

        $service = app(ProductSalePriceService::class);
        $service->upsert($product, 450, ProductSalePriceService::VALIDITY_NO_END);

        $product->update(['regular_price' => 600, 'api_price' => 600]);
        $service->syncDiscountValue($product->fresh());

        $discount = $service->findProductSaleDiscount($product->fresh());
        $this->assertNotNull($discount);
        $this->assertSame(450.0, $service->resolveTargetSalePrice($product->fresh(), $discount));
        $this->assertSame(150.0, (float) $discount->value);
    }

    public function test_until_stock_discount_is_not_applied_when_out_of_stock(): void
    {
        $product = Product::factory()->create([
            'regular_price' => 500,
            'display_price' => 500,
            'available_stock' => 0,
        ]);

        app(ProductSalePriceService::class)->upsert(
            $product,
            450,
            ProductSalePriceService::VALIDITY_UNTIL_STOCK,
        );

        $best = app(DiscountEngine::class)->bestForProduct($product->fresh());

        $this->assertNull($best);
    }

    public function test_until_stock_discount_is_deactivated_when_stock_runs_out(): void
    {
        $product = Product::factory()->create([
            'regular_price' => 500,
            'display_price' => 450,
            'available_stock' => 2,
            'on_sale' => true,
            'api_price' => null,
        ]);

        $service = app(ProductSalePriceService::class);
        $service->upsert($product, 450, ProductSalePriceService::VALIDITY_UNTIL_STOCK);

        $product->update(['available_stock' => 0]);
        $service->deactivateUntilStockSalesIfOutOfStock($product->fresh());

        $discount = $service->findProductSaleDiscount($product->fresh());
        $this->assertFalse($discount?->is_active);
    }

    public function test_eline_import_preserves_target_sale_price(): void
    {
        $product = Product::factory()->create([
            'import_source' => 'eline',
            'eline_sifra' => 'EL-200',
            'external_product_id' => ElineSupport::externalProductId('EL-200'),
            'regular_price' => 500,
            'api_price' => 500,
            'display_price' => 450,
            'on_sale' => true,
        ]);

        app(ProductSalePriceService::class)->upsert(
            $product,
            450,
            ProductSalePriceService::VALIDITY_UNTIL_DATE,
            now()->addWeek(),
        );

        $source = ApiSource::query()->create([
            'name' => 'eLine ERP',
            'target_system_code' => 'eline',
            'base_url' => 'https://example.test',
            'is_active' => true,
        ]);

        $category = Category::factory()->create();
        $elineCategory = ElineCategory::query()->create([
            'name' => 'Laptopi',
            'product_count' => 1,
        ]);

        ElineCategoryMapping::query()->create([
            'eline_category_id' => $elineCategory->id,
            'category_id' => $category->id,
            'product_condition' => ElineCategoryMapping::CONDITION_REFURBISHED,
            'margin_percentage' => 0,
            'is_enabled' => true,
        ]);

        app(ElineProductImporter::class)->importMany(
            collect([[
                'sifra' => 'EL-200',
                'eline_category' => 'Laptopi',
                'naziv' => 'Laptop test',
                'opis' => 'Opis',
                'mpc' => 700,
                'stanje' => 3,
                'aktivan' => 1,
                'price_aktivan' => 1,
            ]]),
            ElineCategoryMapping::query()->with('elineCategory')->get()->keyBy(
                fn (ElineCategoryMapping $mapping): string => (string) $mapping->elineCategory?->name,
            ),
            $source,
        );

        $product->refresh();
        $discount = app(ProductSalePriceService::class)->findProductSaleDiscount($product);

        $this->assertSame(700.0, (float) $product->regular_price);
        $this->assertSame(450.0, app(ProductSalePriceService::class)->resolveTargetSalePrice($product, $discount));
        $this->assertSame(450.0, (float) $product->display_price);
        $this->assertTrue($product->on_sale);
    }

    public function test_expired_discount_is_not_applied(): void
    {
        $product = Product::factory()->create([
            'import_source' => 'eline',
            'regular_price' => 500,
            'api_price' => 500,
            'display_price' => 500,
        ]);

        app(ProductSalePriceService::class)->upsert(
            $product,
            450,
            ProductSalePriceService::VALIDITY_UNTIL_DATE,
            now()->subDay(),
        );

        app(PriceCalculator::class)->recalculateAndPersist($product->fresh());

        $product->refresh();
        $this->assertFalse($product->on_sale);
        $this->assertSame(500.0, (float) $product->display_price);
    }

    public function test_price_locked_product_still_applies_seller_sale_price(): void
    {
        $product = Product::factory()->create([
            'regular_price' => 500,
            'display_price' => 500,
            'price_locked' => true,
            'manual_price' => 500,
        ]);

        app(ProductSalePriceService::class)->upsert(
            $product,
            450,
            ProductSalePriceService::VALIDITY_NO_END,
        );

        app(PriceCalculator::class)->recalculateAndPersist($product->fresh());

        $product->refresh();
        $this->assertTrue($product->on_sale);
        $this->assertSame(500.0, (float) $product->regular_price);
        $this->assertSame(450.0, (float) $product->display_price);
    }

    public function test_upsert_uses_api_price_for_eline_when_regular_price_is_stale(): void
    {
        $product = Product::factory()->create([
            'import_source' => 'eline',
            'regular_price' => 0,
            'api_price' => 500,
            'display_price' => 500,
            'available_stock' => 3,
        ]);

        app(ProductSalePriceService::class)->upsert(
            $product,
            450,
            ProductSalePriceService::VALIDITY_NO_END,
        );

        $discount = app(ProductSalePriceService::class)->findProductSaleDiscount($product);

        $this->assertNotNull($discount);
        $this->assertTrue($discount->is_active);
        $this->assertSame(50.0, (float) $discount->value);

        app(PriceCalculator::class)->recalculateAndPersist($product->fresh());

        $product->refresh();
        $this->assertTrue($product->on_sale);
        $this->assertSame(450.0, (float) $product->display_price);
    }
}
