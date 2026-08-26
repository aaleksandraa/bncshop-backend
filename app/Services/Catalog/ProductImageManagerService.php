<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Catalog\ProductReadCache;
use App\Services\Media\MediaStorage;
use App\Support\PublicStorageUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ProductImageManagerService
{
    public function __construct(
        private readonly MediaStorage $mediaStorage,
        private readonly ProductReadCache $productReadCache,
    ) {}

    /**
     * @param  array{is_primary?: bool, sort_order?: int, status?: string}  $options
     */
    public function storeFromUpload(Product $product, UploadedFile $file, array $options = []): ProductImage
    {
        $directory = $this->productDirectory($product);
        $targetKey = $directory.'/admin-'.Str::uuid()->toString().'.webp';

        $stored = $this->mediaStorage->storeOptimized(
            $targetKey,
            (string) file_get_contents($file->getRealPath()),
        );

        return $this->createImageFromStoredKey($product, $stored->key, $stored->disk, [
            'bytes' => $stored->bytes,
            'width' => $stored->width,
            'height' => $stored->height,
            'original_file_name' => $file->getClientOriginalName(),
        ], $options);
    }

    /**
     * @param  array{bytes?: int, width?: int, height?: int, original_file_name?: string|null}  $meta
     * @param  array{is_primary?: bool, sort_order?: int, status?: string}  $options
     */
    public function createImageFromStoredKey(
        Product $product,
        string $storageKey,
        ?string $disk,
        array $meta = [],
        array $options = [],
    ): ProductImage {
        $maxSort = (int) ProductImage::query()
            ->where('product_id', $product->id)
            ->max('sort_order');

        $absoluteUrl = PublicStorageUrl::url($storageKey);
        $isPrimary = (bool) ($options['is_primary'] ?? false);

        $image = ProductImage::query()->create([
            'product_id' => $product->id,
            'local_path' => $storageKey,
            'storage_disk' => $disk,
            'optimized_at' => now(),
            'image_url' => $absoluteUrl,
            'public_url' => $absoluteUrl,
            'original_file_name' => $meta['original_file_name'] ?? basename($storageKey),
            'stored_file_name' => basename($storageKey),
            'content_type' => 'image/webp',
            'file_extension' => 'webp',
            'file_size_bytes' => $meta['bytes'] ?? null,
            'width' => $meta['width'] ?? null,
            'height' => $meta['height'] ?? null,
            'is_primary' => $isPrimary,
            'sort_order' => $options['sort_order'] ?? ($maxSort + 1),
            'status' => $options['status'] ?? 'active',
            'is_public' => true,
        ]);

        if ($isPrimary || $product->default_image_id === null) {
            $this->setPrimary($product, $image);
        }

        $this->forgetProductCache($product);

        return $image->fresh();
    }

    public function replaceFromStoredKey(Product $product, ProductImage $image, string $storageKey, ?string $disk = null): ProductImage
    {
        if ($image->local_path && $image->local_path !== $storageKey) {
            $this->mediaStorage->deleteFromAnyDisk($image->local_path, $image->storage_disk);
        }

        $absoluteUrl = PublicStorageUrl::url($storageKey);

        $image->update([
            'local_path' => $storageKey,
            'storage_disk' => $disk ?? ($this->mediaStorage->usesR2() ? 'r2' : 'public'),
            'optimized_at' => now(),
            'image_url' => $absoluteUrl,
            'public_url' => $absoluteUrl,
            'stored_file_name' => basename($storageKey),
            'content_type' => 'image/webp',
            'file_extension' => 'webp',
            'status' => 'active',
        ]);

        $this->setPrimary($product, $image);
        $this->forgetProductCache($product);

        return $image->fresh();
    }

    public function upsertPrimaryFromStoredKey(Product $product, string $storageKey): ProductImage
    {
        $product->loadMissing('defaultImage');
        $current = $product->defaultImage;

        if ($current !== null && $current->local_path === $storageKey) {
            return $current;
        }

        if ($current !== null) {
            $this->delete($product, $current);
        }

        return $this->createImageFromStoredKey(
            $product,
            $storageKey,
            $this->mediaStorage->usesR2() ? 'r2' : 'public',
            [],
            ['is_primary' => true, 'sort_order' => 0],
        );
    }

    public function delete(Product $product, ProductImage $image): void
    {
        $wasPrimary = (int) $product->default_image_id === (int) $image->id;

        if ($image->local_path) {
            $this->mediaStorage->deleteFromAnyDisk($image->local_path, $image->storage_disk);
        }

        $image->delete();

        if ($wasPrimary) {
            $nextImage = ProductImage::query()
                ->where('product_id', $product->id)
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->first();

            $product->update([
                'default_image_id' => $nextImage?->id,
            ]);
        }

        $this->forgetProductCache($product);
    }

    public function removeAllImages(Product $product): void
    {
        $product->loadMissing('images');

        foreach ($product->images as $image) {
            if ($image->local_path) {
                $this->mediaStorage->deleteFromAnyDisk($image->local_path, $image->storage_disk);
            }

            $image->delete();
        }

        $product->update(['default_image_id' => null]);
        $this->forgetProductCache($product);
    }

    public function setPrimary(Product $product, ProductImage $image): void
    {
        ProductImage::query()
            ->where('product_id', $product->id)
            ->whereKeyNot($image->id)
            ->update(['is_primary' => false]);

        $image->update(['is_primary' => true]);
        $product->update(['default_image_id' => $image->id]);

        $this->forgetProductCache($product);
    }

    public function primaryImageStorageKey(Product $product): ?string
    {
        $product->loadMissing('defaultImage');

        return $product->defaultImage?->local_path;
    }

    private function productDirectory(Product $product): string
    {
        return 'products/'.Str::slug((string) $product->external_product_id, '_');
    }

    private function forgetProductCache(Product $product): void
    {
        $this->productReadCache->forgetProduct($product->fresh());
    }
}
