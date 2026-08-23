<?php

namespace Tests\Unit;

use App\Jobs\OptimizeAndUploadImage;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Media\ImageOptimizer;
use App\Services\Media\MediaStorage;
use App\Services\Sync\ProductImageStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductImageStorageServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(ImageOptimizer::class);
        $this->mock(MediaStorage::class);
    }

    public function test_resolved_url_uses_local_path_without_checking_storage(): void
    {
        $image = $this->makeImage([
            'local_path' => 'products/demo/photo.webp',
            'storage_disk' => 'r2',
            'public_url' => 'https://media.a1team.ba/images/photo.webp',
            'image_url' => 'https://media.a1team.ba/images/photo.webp',
        ]);

        $url = app(ProductImageStorageService::class)->resolvedUrl($image);

        $this->assertSame('/storage/products/demo/photo.webp', $url);
    }

    public function test_resolved_url_falls_back_to_a1_when_local_path_is_missing(): void
    {
        $image = $this->makeImage([
            'local_path' => null,
            'public_url' => 'https://media.a1team.ba/images/photo.webp',
            'image_url' => 'https://media.a1team.ba/images/photo.webp',
        ]);

        $url = app(ProductImageStorageService::class)->resolvedUrl($image);

        $this->assertSame('https://media.a1team.ba/images/photo.webp', $url);
    }

    public function test_store_from_remote_downloads_new_images_and_queues_r2_upload(): void
    {
        Queue::fake();
        Storage::fake('local');
        Http::fake([
            'https://media.a1team.ba/images/new.webp' => Http::response('image-bytes', 200),
        ]);

        $image = $this->makeImage([
            'local_path' => null,
            'public_url' => 'https://media.a1team.ba/images/new.webp',
            'image_url' => 'https://media.a1team.ba/images/new.webp',
            'external_image_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        ]);

        $ok = app(ProductImageStorageService::class)->storeFromRemote($image, $image->product);

        $this->assertTrue($ok);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://media.a1team.ba/images/new.webp');
        Queue::assertPushed(OptimizeAndUploadImage::class, function (OptimizeAndUploadImage $job) use ($image): bool {
            return $job->modelType === 'product_image'
                && $job->modelId === $image->id
                && str_contains($job->targetKey, 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa.webp');
        });
    }

    public function test_store_from_remote_skips_download_when_r2_copy_already_exists(): void
    {
        Queue::fake();
        Http::fake();

        $image = $this->makeImage([
            'local_path' => 'products/demo/photo.webp',
            'storage_disk' => 'r2',
            'public_url' => 'https://media.a1team.ba/images/photo.webp',
            'image_url' => 'https://media.a1team.ba/images/photo.webp',
        ]);

        $ok = app(ProductImageStorageService::class)->storeFromRemote($image, $image->product);

        $this->assertTrue($ok);
        Http::assertNothingSent();
        Queue::assertNotPushed(OptimizeAndUploadImage::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeImage(array $overrides): ProductImage
    {
        $product = Product::query()->create([
            'external_product_id' => (string) Str::uuid(),
            'name' => 'Test product',
            'slug' => 'test-product-'.Str::random(8),
            'is_public' => true,
            'status' => 'active',
            'api_stock' => 1,
            'available_stock' => 1,
            'stock_status' => 'in_stock',
        ]);

        return ProductImage::query()->create(array_merge([
            'product_id' => $product->id,
            'external_image_id' => (string) Str::uuid(),
            'image_url' => 'https://media.a1team.ba/images/photo.webp',
            'public_url' => 'https://media.a1team.ba/images/photo.webp',
            'is_primary' => true,
            'sort_order' => 0,
            'status' => 'active',
        ], $overrides));
    }
}
