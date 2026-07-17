<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\Catalog\ProductReadCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ScoutIndexDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_searchable_array_contains_filter_and_facet_fields(): void
    {
        $product = Product::query()->create([
            'external_product_id' => 'search-1',
            'name' => 'Laptop',
            'slug' => 'laptop',
            'status' => 'active',
            'is_public' => true,
            'display_price' => 999,
            'regular_price' => 1099,
            'on_sale' => true,
            'available_stock' => 3,
            'is_gaming' => true,
            'is_new' => true,
        ]);

        $document = $product->toSearchableArray();

        $this->assertArrayHasKey('available_stock', $document);
        $this->assertArrayHasKey('on_sale', $document);
        $this->assertArrayHasKey('is_gaming', $document);
        $this->assertArrayHasKey('is_new', $document);
        $this->assertArrayHasKey('filter_attributes', $document);
        $this->assertTrue($document['on_sale']);
    }
}
