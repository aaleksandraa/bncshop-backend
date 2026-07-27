<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\ProductBulkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ProductBulkServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reassign_category_updates_products(): void
    {
        $oldCategory = Category::query()->create([
            'external_category_id' => 'cat-bulk-old',
            'name' => 'Stara',
            'full_slug' => 'stara',
            'status' => 'active',
        ]);

        $newCategory = Category::query()->create([
            'external_category_id' => 'cat-bulk-new',
            'name' => 'Nova',
            'full_slug' => 'nova',
            'status' => 'active',
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-bulk-1',
            'name' => 'Test proizvod',
            'slug' => 'test-proizvod',
            'category_id' => $oldCategory->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        $updated = app(ProductBulkService::class)->reassignCategory(
            Collection::make([$product]),
            $newCategory->id,
        );

        $this->assertSame(1, $updated);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'category_id' => $newCategory->id,
        ]);
    }

    public function test_update_status_and_visibility(): void
    {
        $product = Product::query()->create([
            'external_product_id' => 'prod-bulk-2',
            'name' => 'Drugi proizvod',
            'slug' => 'drugi-proizvod',
            'status' => 'active',
            'is_public' => true,
        ]);

        $service = app(ProductBulkService::class);

        $this->assertSame(1, $service->updateStatus(Collection::make([$product]), 'archived'));
        $this->assertSame(1, $service->updateVisibility(Collection::make([$product->fresh()]), false));

        $product->refresh();
        $this->assertSame('archived', $product->status);
        $this->assertFalse($product->is_public);
    }
}
