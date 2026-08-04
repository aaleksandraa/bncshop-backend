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

    public function test_absolute_from_resolved_keeps_seller_uploads_on_app_url(): void
    {
        config([
            'app.url' => 'https://api.bnc.ba',
            'bnc.legacy_storage_url' => 'https://api.bncshop.ba',
        ]);

        $url = PublicStorageUrl::absoluteFromResolved(
            '/storage/products/demo/seller-8d01474c-603d-49c2-98a3-0fffcf84ff7b.jpg',
        );

        $this->assertSame(
            'https://api.bnc.ba/storage/products/demo/seller-8d01474c-603d-49c2-98a3-0fffcf84ff7b.jpg',
            $url,
        );
    }

    public function test_absolute_from_resolved_rewrites_legacy_assets_to_asset_url(): void
    {
        config([
            'app.url' => 'https://api.bnc.ba',
            'bnc.legacy_storage_url' => 'https://api.bncshop.ba',
        ]);

        $url = PublicStorageUrl::absoluteFromResolved(
            'https://api.bnc.ba/storage/products/demo/63780a32-1a4f-4356-9f49-24bbfbc594bf.webp',
        );

        $this->assertSame(
            'https://api.bncshop.ba/storage/products/demo/63780a32-1a4f-4356-9f49-24bbfbc594bf.webp',
            $url,
        );
    }

    public function test_absolute_from_resolved_uses_app_url_when_file_exists_locally(): void
    {
        config([
            'app.url' => 'https://api.bnc.ba',
            'bnc.legacy_storage_url' => 'https://api.bncshop.ba',
        ]);

        $relativePath = 'products/demo/63780a32-1a4f-4356-9f49-24bbfbc594bf.webp';
        \Illuminate\Support\Facades\Storage::disk('public')->put($relativePath, 'fake-image');

        $url = PublicStorageUrl::absoluteFromResolved(
            '/storage/'.$relativePath,
        );

        $this->assertSame(
            'https://api.bnc.ba/storage/'.$relativePath,
            $url,
        );
    }

    public function test_absolute_from_resolved_rewrites_wrong_host_for_seller_uploads(): void
    {
        config([
            'app.url' => 'https://api.bnc.ba',
            'bnc.legacy_storage_url' => 'https://api.bncshop.ba',
        ]);

        $url = PublicStorageUrl::absoluteFromResolved(
            'https://api.bncshop.ba/storage/products/demo/seller-8d01474c-603d-49c2-98a3-0fffcf84ff7b.jpg',
        );

        $this->assertSame(
            'https://api.bnc.ba/storage/products/demo/seller-8d01474c-603d-49c2-98a3-0fffcf84ff7b.jpg',
            $url,
        );
    }

    public function test_storage_origin_falls_back_to_bncshop_when_app_url_is_api_bnc_ba(): void
    {
        app()['env'] = 'production';
        config([
            'app.url' => 'https://api.bnc.ba',
            'bnc.legacy_storage_url' => null,
        ]);

        $this->assertSame('https://api.bncshop.ba', PublicStorageUrl::storageOrigin());
    }

    public function test_rewrite_storage_urls_in_value_routes_seller_and_legacy_assets(): void
    {
        config([
            'app.url' => 'https://api.bnc.ba',
            'bnc.legacy_storage_url' => 'https://api.bncshop.ba',
        ]);

        $payload = [
            'default_image' => [
                'url' => 'https://api.bncshop.ba/storage/products/demo/seller-8d01474c-603d-49c2-98a3-0fffcf84ff7b.jpg',
            ],
            'manufacturer' => [
                'logo_url' => '/storage/manufacturers/logos/demo.webp',
            ],
        ];

        $rewritten = PublicStorageUrl::rewriteStorageUrlsInValue($payload);

        $this->assertSame(
            'https://api.bnc.ba/storage/products/demo/seller-8d01474c-603d-49c2-98a3-0fffcf84ff7b.jpg',
            $rewritten['default_image']['url'],
        );
        $this->assertSame(
            'https://api.bncshop.ba/storage/manufacturers/logos/demo.webp',
            $rewritten['manufacturer']['logo_url'],
        );
    }
}
