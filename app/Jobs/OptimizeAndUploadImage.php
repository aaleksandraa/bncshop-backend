<?php

namespace App\Jobs;

use App\Models\BlogPost;
use App\Models\B2bProductImage;
use App\Models\Manufacturer;
use App\Models\ProductImage;
use App\Services\Media\MediaStorage;
use App\Services\Sync\ProductImageStorageService;
use App\Support\PublicStorageUrl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class OptimizeAndUploadImage implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 3;

    public function __construct(
        public string $modelType,
        public int $modelId,
        public string $tempPath,
        public string $targetKey,
        public ?string $previousKey = null,
        public ?string $previousDisk = null,
    ) {
        $this->onQueue('images');
    }

    public function handle(MediaStorage $mediaStorage, ProductImageStorageService $productImageStorage): void
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($this->tempPath)) {
            return;
        }

        $contents = (string) $disk->get($this->tempPath);

        if ($contents === '') {
            $disk->delete($this->tempPath);

            return;
        }

        $stored = $mediaStorage->storeOptimized(
            $this->targetKey,
            $contents,
            $this->previousKey,
        );

        $this->updateModel($stored->key, $stored->disk, $stored->width, $stored->height, $stored->bytes);

        if ($this->previousKey !== null && $this->previousDisk !== null) {
            $mediaStorage->deleteFromAnyDisk($this->previousKey, $this->previousDisk);
        }

        $disk->delete($this->tempPath);

        if ($this->modelType === 'product_image') {
            $image = ProductImage::query()->find($this->modelId);

            if ($image !== null) {
                $productImageStorage->forgetResolvedUrlCache($image);
            }
        }
    }

    public function failed(): void
    {
        Storage::disk('local')->delete($this->tempPath);
    }

    private function updateModel(string $key, string $disk, int $width, int $height, int $bytes): void
    {
        match ($this->modelType) {
            'product_image' => ProductImage::query()
                ->whereKey($this->modelId)
                ->update([
                    'local_path' => $key,
                    'storage_disk' => $disk,
                    'optimized_at' => now(),
                    'image_url' => PublicStorageUrl::url($key),
                    'file_extension' => pathinfo($key, PATHINFO_EXTENSION) ?: 'webp',
                    'file_size_bytes' => $bytes,
                    'width' => $width > 0 ? $width : null,
                    'height' => $height > 0 ? $height : null,
                ]),
            'manufacturer' => Manufacturer::query()
                ->whereKey($this->modelId)
                ->update([
                    'logo_path' => $key,
                    'storage_disk' => $disk,
                    'optimized_at' => now(),
                ]),
            'blog_post' => BlogPost::query()
                ->whereKey($this->modelId)
                ->update([
                    'featured_image_path' => $key,
                    'storage_disk' => $disk,
                    'optimized_at' => now(),
                ]),
            'b2b_product_image' => B2bProductImage::query()
                ->whereKey($this->modelId)
                ->update([
                    'path' => $key,
                    'storage_disk' => $disk,
                    'optimized_at' => now(),
                ]),
            default => throw new InvalidArgumentException("Unknown media model type [{$this->modelType}]."),
        };
    }
}
