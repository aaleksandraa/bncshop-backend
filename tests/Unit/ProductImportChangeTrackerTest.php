<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\Sync\ProductImportChangeTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImportChangeTrackerTest extends TestCase
{
    use RefreshDatabase;

    public function test_diff_detects_price_and_stock_changes(): void
    {
        $product = Product::query()->create([
            'external_product_id' => '11111111-1111-1111-1111-111111111111',
            'name' => 'Test',
            'slug' => 'test',
            'is_public' => true,
            'status' => 'active',
            'api_price' => 100,
            'regular_price' => 100,
            'display_price' => 100,
            'api_stock' => 5,
            'available_stock' => 5,
            'stock_status' => 'in_stock',
        ]);

        $snapshot = ProductImportChangeTracker::snapshot($product);

        $product->update([
            'api_price' => 120,
            'regular_price' => 120,
            'display_price' => 120,
            'api_stock' => 2,
            'available_stock' => 2,
        ]);

        $changed = ProductImportChangeTracker::diff($snapshot, $product->fresh());

        $this->assertContains('api_price', $changed);
        $this->assertContains('regular_price', $changed);
        $this->assertContains('display_price', $changed);
        $this->assertContains('api_stock', $changed);
        $this->assertContains('available_stock', $changed);
    }
}
