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
}
