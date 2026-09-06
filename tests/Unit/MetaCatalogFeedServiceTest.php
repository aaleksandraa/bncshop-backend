<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Integrations\MetaCatalogFeedPolicy;
use App\Services\Integrations\MetaCatalogFeedService;
use App\Services\Integrations\MetaCatalogImageUrlResolver;
use App\Services\Integrations\TrackingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetaCatalogFeedServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_feed_token_and_authorizes_request(): void
    {
        $service = app(MetaCatalogFeedService::class);
        $token = $service->feedToken();

        $this->assertNotSame('', $token);
        $this->assertTrue($service->isAuthorized($token));
        $this->assertFalse($service->isAuthorized('wrong-token'));
    }

    public function test_feed_url_points_to_csv_endpoint(): void
    {
        config(['bnc.frontend_url' => 'https://bnc.ba']);

        $service = app(MetaCatalogFeedService::class);
        $url = $service->feedUrl();

        $this->assertStringContainsString('/feeds/meta-catalog.csv?token=', $url);
    }

    public function test_feed_policy_filters_by_category_and_name_keywords(): void
    {
        config([
            'bnc.meta_catalog.default_include_category_slugs' => 'it-oprema/laptopi',
            'bnc.meta_catalog.default_exclude_name_keywords' => 'zastitno staklo',
        ]);

        $includedCategory = Category::factory()->create([
            'full_slug' => 'it-oprema/laptopi/gaming',
        ]);
        $excludedCategory = Category::factory()->create([
            'full_slug' => 'telefonija/maske',
        ]);

        $included = Product::factory()->create([
            'category_id' => $includedCategory->id,
            'name' => 'Lenovo Legion 5',
            'is_public' => true,
            'status' => 'active',
            'display_price' => 1999,
            'available_stock' => 3,
            'api_default_image_url' => 'https://images.bnc.ba/products/laptop.webp',
        ]);

        Product::factory()->create([
            'category_id' => $excludedCategory->id,
            'name' => 'iPhone maska',
            'is_public' => true,
            'status' => 'active',
            'display_price' => 19.99,
            'available_stock' => 5,
            'api_default_image_url' => 'https://images.bnc.ba/products/maska.webp',
        ]);

        Product::factory()->create([
            'category_id' => $includedCategory->id,
            'name' => 'Zastitno staklo za laptop',
            'is_public' => true,
            'status' => 'active',
            'display_price' => 9.99,
            'available_stock' => 5,
            'api_default_image_url' => 'https://images.bnc.ba/products/glass.webp',
        ]);

        $ids = app(MetaCatalogFeedService::class)
            ->feedProductQuery()
            ->pluck('id')
            ->all();

        $this->assertSame([(int) $included->id], $ids);
    }

    public function test_out_of_stock_products_are_excluded_from_feed_query(): void
    {
        config(['bnc.meta_catalog.default_include_category_slugs' => '']);

        Product::factory()->create([
            'is_public' => true,
            'status' => 'active',
            'display_price' => 100,
            'available_stock' => 0,
            'api_default_image_url' => 'https://images.bnc.ba/products/out.webp',
        ]);

        $inStock = Product::factory()->create([
            'is_public' => true,
            'status' => 'active',
            'display_price' => 100,
            'available_stock' => 2,
            'api_default_image_url' => 'https://images.bnc.ba/products/in.webp',
        ]);

        $ids = app(MetaCatalogFeedService::class)->feedProductQuery()->pluck('id')->all();

        $this->assertSame([(int) $inStock->id], $ids);
    }

    public function test_image_resolver_uses_local_path_on_catalog_cdn(): void
    {
        config(['bnc.meta_catalog.image_origin' => 'https://images.bnc.ba']);

        $product = Product::factory()->create();
        $image = ProductImage::query()->create([
            'product_id' => $product->id,
            'image_url' => 'https://supplier.example/image.webp',
            'local_path' => 'products/demo/laptop.webp',
            'is_primary' => true,
            'sort_order' => 0,
            'status' => 'active',
        ]);
        $product->setRelation('defaultImage', $image);

        $url = app(MetaCatalogImageUrlResolver::class)->resolve($product);

        $this->assertSame('https://images.bnc.ba/products/demo/laptop.webp', $url);
    }

    public function test_image_resolver_rewrites_api_bncshop_storage_to_catalog_cdn(): void
    {
        config([
            'bnc.meta_catalog.image_origin' => 'https://images.bnc.ba',
            'app.url' => 'https://api.bnc.ba',
            'bnc.legacy_storage_url' => 'https://api.bncshop.ba',
        ]);

        $product = Product::factory()->make([
            'api_default_image_url' => 'https://api.bncshop.ba/storage/products/demo/image.webp',
        ]);

        $url = app(MetaCatalogImageUrlResolver::class)->resolve($product);

        $this->assertSame('https://images.bnc.ba/products/demo/image.webp', $url);
    }

    public function test_image_resolver_rewrites_relative_storage_path(): void
    {
        config(['bnc.meta_catalog.image_origin' => 'https://images.bnc.ba']);

        $product = Product::factory()->make([
            'api_default_image_url' => '/storage/products/demo/image.webp',
        ]);

        $url = app(MetaCatalogImageUrlResolver::class)->resolve($product);

        $this->assertSame('https://images.bnc.ba/products/demo/image.webp', $url);
    }

    public function test_refurbished_products_use_refurbished_condition(): void
    {
        $product = Product::factory()->make([
            'is_refurbished' => true,
        ]);

        $this->assertSame('refurbished', app(MetaCatalogFeedPolicy::class)->resolveCondition($product));
    }
}
