<?php

namespace Tests\Unit;

use App\Support\PublicStorageUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicStorageUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_absolute_from_resolved_rewrites_localhost_storage_urls(): void
    {
        config(['app.url' => 'https://api.bncshop.ba']);

        $url = PublicStorageUrl::absoluteFromResolved(
            'http://localhost:8000/storage/products/demo/image.webp',
        );

        $this->assertSame(
            'https://api.bncshop.ba/storage/products/demo/image.webp',
            $url,
        );
    }

    public function test_absolute_from_resolved_builds_from_relative_storage_path(): void
    {
        config(['app.url' => 'https://api.bncshop.ba']);

        $url = PublicStorageUrl::absoluteFromResolved('/storage/products/demo/image.webp');

        $this->assertSame(
            'https://api.bncshop.ba/storage/products/demo/image.webp',
            $url,
        );
    }

    public function test_absolute_from_resolved_uses_production_origin_when_app_url_is_localhost(): void
    {
        app()['env'] = 'production';
        config(['app.url' => 'http://localhost:8000']);

        $url = PublicStorageUrl::absoluteFromResolved('/storage/products/demo/image.webp');

        $this->assertSame(
            'https://api.bncshop.ba/storage/products/demo/image.webp',
            $url,
        );
    }

    public function test_absolute_from_resolved_rewrites_api_bnc_ba_storage_to_asset_url(): void
    {
        app()['env'] = 'production';
        config([
            'app.url' => 'https://api.bnc.ba',
            'app.asset_url' => 'https://api.bncshop.ba',
        ]);

        $url = PublicStorageUrl::absoluteFromResolved(
            'https://api.bnc.ba/storage/products/demo/seller-image.jpg',
        );

        $this->assertSame(
            'https://api.bncshop.ba/storage/products/demo/seller-image.jpg',
            $url,
        );
    }

    public function test_storage_origin_falls_back_to_bncshop_when_app_url_is_api_bnc_ba(): void
    {
        app()['env'] = 'production';
        config([
            'app.url' => 'https://api.bnc.ba',
            'app.asset_url' => null,
        ]);

        $this->assertSame('https://api.bncshop.ba', PublicStorageUrl::storageOrigin());
    }

    public function test_rewrite_storage_urls_in_value_rewrites_cached_product_payload(): void
    {
        app()['env'] = 'production';
        config(['app.url' => 'https://api.bnc.ba']);

        $payload = [
            'default_image' => [
                'url' => 'https://api.bnc.ba/storage/products/demo/seller-image.jpg',
            ],
            'manufacturer' => [
                'logo_url' => '/storage/manufacturers/logos/demo.webp',
            ],
        ];

        $rewritten = PublicStorageUrl::rewriteStorageUrlsInValue($payload);

        $this->assertSame(
            'https://api.bncshop.ba/storage/products/demo/seller-image.jpg',
            $rewritten['default_image']['url'],
        );
        $this->assertSame(
            'https://api.bncshop.ba/storage/manufacturers/logos/demo.webp',
            $rewritten['manufacturer']['logo_url'],
        );
    }
}
