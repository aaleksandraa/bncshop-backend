<?php

namespace Tests\Unit;

use App\Jobs\OptimizeAndUploadImage;
use App\Models\ApiSource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Media\ImageOptimizer;
use App\Services\Media\MediaStorage;
use App\Services\Sync\ProductImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductImporterImageDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(ImageOptimizer::class);
        $this->mock(MediaStorage::class);
    }

    public function test_new_a1_gallery_image_is_queued_for_r2_upload(): void
    {
        Queue::fake();
        Http::fake([
            'https://media.a1team.ba/images/fresh.webp' => Http::response('image-bytes', 200),
        ]);

        $source = ApiSource::query()->create([
            'name' => 'A1',
            'target_system_code' => 'bnc-shop',
            'base_url' => 'https://example.test',
            'is_active' => true,
        ]);

        $imageId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';

        app(ProductImporter::class)->upsertOne([
            'productId' => '11111111-1111-1111-1111-111111111111',
            'name' => 'A1 camera',
            'slug' => 'a1-camera',
            'isPublic' => true,
            'stock' => 3,
            'price' => 199,
            'gallery' => [[
                'imageId' => $imageId,
                'imageUrl' => 'https://media.a1team.ba/images/fresh.webp',
                'isPrimary' => true,
                'image' => [
                    'publicUrl' => 'https://media.a1team.ba/images/fresh.webp',
                    'sourceUrl' => 'https://media.a1team.ba/images/fresh.webp',
                ],
            ]],
        ], $source);

        $image = ProductImage::query()->where('external_image_id', $imageId)->first();

        $this->assertNotNull($image);
        $this->assertSame('https://media.a1team.ba/images/fresh.webp', $image->public_url);
        Queue::assertPushed(OptimizeAndUploadImage::class, fn (OptimizeAndUploadImage $job): bool => $job->modelId === $image->id);
    }

    public function test_existing_r2_image_is_not_re_downloaded_when_a1_cdn_host_changes(): void
    {
        Queue::fake();
        Http::fake();

        $product = Product::query()->create([
            'external_product_id' => '22222222-2222-2222-2222-222222222222',
            'name' => 'Existing camera',
            'slug' => 'existing-camera',
            'is_public' => true,
            'status' => 'active',
            'api_stock' => 1,
            'available_stock' => 1,
            'stock_status' => 'in_stock',
        ]);

        $imageId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';

        ProductImage::query()->create([
            'product_id' => $product->id,
            'external_image_id' => $imageId,
            'image_url' => '/storage/products/demo/old.webp',
            'public_url' => 'https://a1team.ba/storage/images/old.webp',
            'local_path' => 'products/demo/old.webp',
            'storage_disk' => 'r2',
            'is_primary' => true,
            'sort_order' => 0,
            'status' => 'active',
        ]);

        app(ProductImporter::class)->upsertOne([
            'productId' => $product->external_product_id,
            'name' => 'Existing camera',
            'slug' => 'existing-camera',
            'isPublic' => true,
            'stock' => 1,
            'price' => 199,
            'gallery' => [[
                'imageId' => $imageId,
                'imageUrl' => 'https://media.a1team.ba/images/old.webp',
                'isPrimary' => true,
                'image' => [
                    'publicUrl' => 'https://media.a1team.ba/images/old.webp',
                    'sourceUrl' => 'https://media.a1team.ba/images/old.webp',
                ],
            ]],
        ]);

        $image = $product->images()->first();

        $this->assertSame('products/demo/old.webp', $image?->local_path);
        $this->assertSame('/storage/products/demo/old.webp', $image?->image_url);
        $this->assertSame('https://media.a1team.ba/images/old.webp', $image?->public_url);
        Http::assertNothingSent();
        Queue::assertNotPushed(OptimizeAndUploadImage::class);
    }
}
