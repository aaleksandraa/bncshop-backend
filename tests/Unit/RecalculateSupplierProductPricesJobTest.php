<?php

namespace Tests\Unit;

use App\Jobs\RecalculateSupplierProductPricesJob;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSupplierOffer;
use App\Models\Supplier;
use App\Models\SupplierCategoryMarginRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecalculateSupplierProductPricesJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_recalculates_supplier_product_prices(): void
    {
        $category = Category::factory()->create();
        $supplier = Supplier::query()->create([
            'external_supplier_id' => 'supplier-job',
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
            'external_product_id' => 'prod-job-1',
            'name' => 'Startech proizvod',
            'slug' => 'startech-proizvod-job',
            'status' => 'active',
            'is_public' => true,
            'category_id' => $category->id,
            'regular_price' => 726.96,
            'display_price' => 726.96,
        ]);

        ProductSupplierOffer::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'supplier_price' => 559.2,
            'supplier_stock' => 5,
            'is_selected_price_source' => true,
        ]);

        (new RecalculateSupplierProductPricesJob($supplier->id, $supplier->label()))->handle(
            app(\App\Services\Pricing\ProductPriceRecalculator::class),
        );

        $product->refresh();

        $this->assertSame(746.96, (float) $product->regular_price);
        $this->assertSame(746.96, (float) $product->display_price);
    }

    public function test_job_can_be_dispatched_to_queue(): void
    {
        Queue::fake();

        RecalculateSupplierProductPricesJob::dispatch(1, 'Startech');

        Queue::assertPushed(RecalculateSupplierProductPricesJob::class, function (RecalculateSupplierProductPricesJob $job): bool {
            return $job->supplierId === 1
                && $job->supplierLabel === 'Startech';
        });
    }
}
