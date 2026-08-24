<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Catalog\ProductReadCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class LookupNegativeCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_slugs_are_rejected_without_caching(): void
    {
        foreach (['null', 'undefined', 'NaN'] as $slug) {
            $this->getJson("/api/v1/campaigns/{$slug}")->assertNotFound();
            $this->getJson("/api/v1/pages/{$slug}")->assertNotFound();
            $this->getJson("/api/v1/products/{$slug}")->assertNotFound();
        }
    }

    public function test_missing_campaign_is_negatively_cached(): void
    {
        $this->getJson('/api/v1/campaigns/shop')->assertNotFound();
        $this->getJson('/api/v1/campaigns/shop')->assertNotFound();

        $cache = app(ProductReadCache::class);
        $cached = $cache->rememberCampaign('shop', 60, function (): array {
            throw new RuntimeException('missing campaign should be cached');
        });

        $this->assertNull($cached);
    }

    public function test_missing_page_is_negatively_cached(): void
    {
        $this->getJson('/api/v1/pages/shop')->assertNotFound();

        $cache = app(ProductReadCache::class);
        $cached = $cache->rememberPage('shop', 600, function (): array {
            throw new RuntimeException('missing page should be cached');
        });

        $this->assertNull($cached);
    }

    public function test_missing_product_is_negatively_cached(): void
    {
        $this->getJson('/api/v1/products/nonexistent-product')->assertNotFound();

        $cache = app(ProductReadCache::class);
        $cached = $cache->rememberProduct('nonexistent-product', 900, function (): array {
            throw new RuntimeException('missing product should be cached');
        });

        $this->assertNull($cached);
    }

    public function test_existing_product_is_not_marked_missing(): void
    {
        $product = Product::factory()->create([
            'slug' => 'real-product',
            'is_public' => true,
            'status' => 'active',
        ]);

        $this->getJson('/api/v1/products/real-product')
            ->assertOk()
            ->assertJsonPath('data.slug', $product->slug);

        $cache = app(ProductReadCache::class);
        $cached = $cache->rememberProduct('real-product', 900, function (): array {
            throw new RuntimeException('existing product should be cached');
        });

        $this->assertIsArray($cached);
        $this->assertArrayNotHasKey('__missing', $cached);
        $this->assertSame('real-product', $cached['slug']);
    }
}
