<?php

namespace Tests\Unit;

use App\Jobs\RecalculateAllProductPricesJob;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSupplierOffer;
use App\Models\Supplier;
use App\Services\Pricing\ProductPriceRecalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecalculateAllProductPricesJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_recalculates_catalog_prices_and_stores_calculated_price(): void
    {
        $category = Category::factory()->create([
            'margin_percentage' => 22,
        ]);
        $supplier = Supplier::query()->create([
            'external_supplier_id' => 'supplier-catalog-job',
            'name' => 'asbis',
            'display_name' => 'Asbis',
            'code' => 'asbis-catalog-job',
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-catalog-job',
            'name' => 'Katalog proizvod',
            'slug' => 'katalog-proizvod-job',
            'status' => 'active',
            'is_public' => true,
            'category_id' => $category->id,
            'api_price' => 789,
            'regular_price' => 672.46,
            'display_price' => 672.46,
        ]);

        ProductSupplierOffer::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'supplier_sku' => 'AS-CAT',
            'supplier_price' => 551.20,
            'supplier_stock' => 3,
            'is_selected_price_source' => true,
        ]);

        (new RecalculateAllProductPricesJob(0, true))->handle(
            app(ProductPriceRecalculator::class),
            app(\App\Services\Catalog\ProductReadCache::class),
        );

        $fresh = $product->fresh();

        $this->assertSame(787.0, (float) $fresh->regular_price);
        $this->assertSame(787.0, (float) $fresh->calculated_price);
        $this->assertSame(787.0, (float) $fresh->display_price);
    }

    public function test_start_dispatches_independent_chunk_jobs(): void
    {
        Queue::fake();

        $category = Category::factory()->create();

        for ($i = 1; $i <= RecalculateAllProductPricesJob::CHUNK_SIZE + 1; $i++) {
            Product::query()->create([
                'external_product_id' => "prod-catalog-chunk-{$i}",
                'name' => "Proizvod {$i}",
                'slug' => "proizvod-catalog-chunk-{$i}",
                'status' => 'active',
                'is_public' => true,
                'category_id' => $category->id,
                'regular_price' => 100,
                'display_price' => 100,
            ]);
        }

        $dispatched = RecalculateAllProductPricesJob::start();

        $this->assertSame(2, $dispatched);
        Queue::assertPushed(RecalculateAllProductPricesJob::class, 2);
    }

    public function test_start_dispatches_nothing_when_no_products(): void
    {
        Queue::fake();

        $dispatched = RecalculateAllProductPricesJob::start();

        $this->assertSame(0, $dispatched);
        Queue::assertNothingPushed();
    }

    public function test_for_product_ids_skips_locked_and_eline(): void
    {
        $category = Category::factory()->create([
            'margin_percentage' => 22,
        ]);
        $supplier = Supplier::query()->create([
            'external_supplier_id' => 'supplier-ids',
            'name' => 'asbis',
            'display_name' => 'Asbis',
            'code' => 'asbis-ids',
        ]);

        $unlocked = Product::query()->create([
            'external_product_id' => 'prod-ids-ok',
            'name' => 'Otključan',
            'slug' => 'otkljucan-ids',
            'status' => 'active',
            'is_public' => true,
            'category_id' => $category->id,
            'regular_price' => 100,
        ]);
        $locked = Product::query()->create([
            'external_product_id' => 'prod-ids-lock',
            'name' => 'Zaključan',
            'slug' => 'zakljucan-ids',
            'status' => 'active',
            'is_public' => true,
            'category_id' => $category->id,
            'regular_price' => 100,
            'price_locked' => true,
            'manual_price' => 100,
        ]);
        $eline = Product::query()->create([
            'external_product_id' => 'prod-ids-eline',
            'name' => 'eLine',
            'slug' => 'eline-ids',
            'status' => 'active',
            'is_public' => true,
            'import_source' => 'eline',
            'category_id' => $category->id,
            'api_price' => 450,
            'regular_price' => 450,
        ]);

        foreach ([$unlocked, $locked, $eline] as $product) {
            ProductSupplierOffer::query()->create([
                'product_id' => $product->id,
                'supplier_id' => $supplier->id,
                'supplier_sku' => 'AS-'.$product->id,
                'supplier_price' => 551.20,
                'supplier_stock' => 3,
                'is_selected_price_source' => true,
            ]);
        }

        $count = app(ProductPriceRecalculator::class)->forProductIds([
            $unlocked->id,
            $locked->id,
            $eline->id,
        ]);

        $this->assertSame(1, $count);
        $this->assertSame(787.0, (float) $unlocked->fresh()->regular_price);
        $this->assertSame(100.0, (float) $locked->fresh()->regular_price);
        $this->assertSame(450.0, (float) $eline->fresh()->regular_price);
    }

    public function test_recalculate_prices_command_queue_dispatches_jobs(): void
    {
        Queue::fake();

        Product::query()->create([
            'external_product_id' => 'prod-cmd-queue',
            'name' => 'Queue proizvod',
            'slug' => 'queue-proizvod',
            'status' => 'active',
            'is_public' => true,
            'regular_price' => 100,
        ]);

        $this->artisan('bnc:recalculate-prices', ['--queue' => true])
            ->assertSuccessful();

        Queue::assertPushed(RecalculateAllProductPricesJob::class);
    }
}
