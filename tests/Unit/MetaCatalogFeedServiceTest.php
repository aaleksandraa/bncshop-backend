<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Services\Integrations\MetaCatalogFeedPolicy;
use App\Services\Integrations\MetaCatalogFeedService;
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
            'api_default_image_url' => 'https://images.bnc.ba/products/laptop.webp',
        ]);

        Product::factory()->create([
            'category_id' => $excludedCategory->id,
            'name' => 'iPhone maska',
            'is_public' => true,
            'status' => 'active',
            'display_price' => 19.99,
            'api_default_image_url' => 'https://images.bnc.ba/products/maska.webp',
        ]);

        Product::factory()->create([
            'category_id' => $includedCategory->id,
            'name' => 'Zastitno staklo za laptop',
            'is_public' => true,
            'status' => 'active',
            'display_price' => 9.99,
            'api_default_image_url' => 'https://images.bnc.ba/products/glass.webp',
        ]);

        $policy = app(MetaCatalogFeedPolicy::class);

        $ids = Product::query()
            ->public()
            ->active()
            ->tap(fn ($query) => $policy->applyToQuery($query))
            ->pluck('id')
            ->all();

        $this->assertSame([(int) $included->id], $ids);
    }

    public function test_resolves_absolute_https_image_urls(): void
    {
        config([
            'bnc.media_origin' => 'https://images.bnc.ba',
            'app.url' => 'https://api.bnc.ba',
        ]);

        $product = Product::factory()->create([
            'api_default_image_url' => '/storage/products/demo/image.webp',
        ]);

        $url = app(MetaCatalogFeedService::class)->resolvePublicImageUrl($product);

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
