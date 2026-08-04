<?php

namespace App\Services\Media;

use App\Models\BlogPost;
use App\Models\B2bProductImage;
use App\Models\Manufacturer;
use App\Models\ProductImage;
use App\Support\PublicStorageUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MediaMigrationService
{
    public function __construct(
        private readonly MediaStorage $mediaStorage,
    ) {}

    /**
     * @return array{success: bool, message: string}
     */
    public function migrateProductImage(ProductImage $image, bool $force = false): array
    {
        if (
            ! $force
            && $image->storage_disk === $this->mediaStorage->diskName()
            && filled($image->optimized_at)
            && $this->mediaStorage->exists((string) $image->local_path)
        ) {
            return ['success' => true, 'message' => 'already migrated'];
        }

        $contents = $this->resolveBinaryForProductImage($image);

        if ($contents === null) {
            return ['success' => false, 'message' => 'source unavailable'];
        }

        $targetKey = $this->targetKeyForProductImage($image);

        $stored = $this->mediaStorage->storeOptimized(
            $targetKey,
            $contents,
            $image->local_path !== $targetKey ? $image->local_path : null,
        );

        if ($image->local_path && $image->local_path !== $stored->key) {
            $this->mediaStorage->deleteFromAnyDisk($image->local_path, $image->storage_disk);
        }

        $image->forceFill([
            'local_path' => $stored->key,
            'storage_disk' => $stored->disk,
            'optimized_at' => now(),
            'image_url' => PublicStorageUrl::url($stored->key),
            'file_extension' => 'webp',
            'file_size_bytes' => $stored->bytes,
            'width' => $stored->width,
            'height' => $stored->height,
        ])->save();

        return ['success' => true, 'message' => $stored->key];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function migrateManufacturer(Manufacturer $manufacturer, bool $force = false): array
    {
        if (
            ! $force
            && $manufacturer->storage_disk === $this->mediaStorage->diskName()
            && filled($manufacturer->optimized_at)
            && filled($manufacturer->logo_path)
            && $this->mediaStorage->exists((string) $manufacturer->logo_path)
        ) {
            return ['success' => true, 'message' => 'already migrated'];
        }

        $contents = $this->resolveBinaryForPathOrUrl(
            $manufacturer->logo_path,
            $manufacturer->storage_disk,
            $manufacturer->logo_url,
        );

        if ($contents === null) {
            return ['success' => false, 'message' => 'source unavailable'];
        }

        $slug = \Illuminate\Support\Str::slug($manufacturer->slug ?: $manufacturer->name) ?: 'brand';
        $targetKey = $manufacturer->logo_path ?: "manufacturers/logos/{$slug}-{$manufacturer->id}.webp";

        $stored = $this->mediaStorage->storeOptimized($targetKey, $contents, $manufacturer->logo_path);

        if ($manufacturer->logo_path && $manufacturer->logo_path !== $stored->key) {
            $this->mediaStorage->deleteFromAnyDisk($manufacturer->logo_path, $manufacturer->storage_disk);
        }

        $manufacturer->forceFill([
            'logo_path' => $stored->key,
            'storage_disk' => $stored->disk,
            'optimized_at' => now(),
        ])->save();

        return ['success' => true, 'message' => $stored->key];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function migrateBlogPost(BlogPost $post, bool $force = false): array
    {
        if (
            ! $force
            && $post->storage_disk === $this->mediaStorage->diskName()
            && filled($post->optimized_at)
            && filled($post->featured_image_path)
            && $this->mediaStorage->exists((string) $post->featured_image_path)
        ) {
            return ['success' => true, 'message' => 'already migrated'];
        }

        $contents = $this->resolveBinaryForPathOrUrl(
            $post->featured_image_path,
            $post->storage_disk,
            $post->featured_image_url,
        );

        if ($contents === null) {
            return ['success' => false, 'message' => 'source unavailable'];
        }

        $targetKey = $post->featured_image_path ?: 'blog/featured/'.\Illuminate\Support\Str::uuid()->toString().'.webp';
        $stored = $this->mediaStorage->storeOptimized($targetKey, $contents, $post->featured_image_path);

        if ($post->featured_image_path && $post->featured_image_path !== $stored->key) {
            $this->mediaStorage->deleteFromAnyDisk($post->featured_image_path, $post->storage_disk);
        }

        $post->forceFill([
            'featured_image_path' => $stored->key,
            'storage_disk' => $stored->disk,
            'optimized_at' => now(),
        ])->save();

        return ['success' => true, 'message' => $stored->key];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function migrateB2bProductImage(B2bProductImage $image, bool $force = false): array
    {
        if (
            ! $force
            && $image->storage_disk === $this->mediaStorage->diskName()
            && filled($image->optimized_at)
            && filled($image->path)
            && $this->mediaStorage->exists((string) $image->path)
        ) {
            return ['success' => true, 'message' => 'already migrated'];
        }

        $contents = $this->resolveBinaryForPathOrUrl($image->path, $image->storage_disk, $image->path);

        if ($contents === null) {
            return ['success' => false, 'message' => 'source unavailable'];
        }

        $targetKey = str_starts_with((string) $image->path, 'http')
            ? 'b2b-products/'.\Illuminate\Support\Str::uuid()->toString().'.webp'
            : (string) $image->path;

        $stored = $this->mediaStorage->storeOptimized($targetKey, $contents, $image->path);

        if ($image->path && $image->path !== $stored->key && ! str_starts_with($image->path, 'http')) {
            $this->mediaStorage->deleteFromAnyDisk($image->path, $image->storage_disk);
        }

        $image->forceFill([
            'path' => $stored->key,
            'storage_disk' => $stored->disk,
            'optimized_at' => now(),
        ])->save();

        return ['success' => true, 'message' => $stored->key];
    }

    private function targetKeyForProductImage(ProductImage $image): string
    {
        if (filled($image->local_path)) {
            return app(ImageOptimizer::class)->ensureWebpExtension((string) $image->local_path);
        }

        $product = $image->product;
        $directory = $product
            ? 'products/'.\Illuminate\Support\Str::slug((string) $product->external_product_id, '_')
            : 'products/misc';

        $base = filled($image->external_image_id)
            ? (string) $image->external_image_id
            : 'image-'.$image->id;

        return $directory.'/'.$base.'.webp';
    }

    private function resolveBinaryForProductImage(ProductImage $image): ?string
    {
        if (filled($image->local_path)) {
            $local = $this->readFromDisk((string) $image->local_path, $image->storage_disk);

            if ($local !== null) {
                return $local;
            }
        }

        foreach ([$image->public_url, $image->image_url, $image->source_url] as $url) {
            $remote = $this->downloadUrl((string) $url);

            if ($remote !== null) {
                return $remote;
            }
        }

        return null;
    }

    private function resolveBinaryForPathOrUrl(?string $path, ?string $disk, ?string $fallbackUrl): ?string
    {
        if (filled($path) && ! str_starts_with($path, 'http')) {
            $local = $this->readFromDisk($path, $disk);

            if ($local !== null) {
                return $local;
            }
        }

        $candidates = array_filter([
            str_starts_with((string) $path, 'http') ? $path : null,
            $fallbackUrl,
        ]);

        foreach ($candidates as $url) {
            $remote = $this->downloadUrl($url);

            if ($remote !== null) {
                return $remote;
            }
        }

        return null;
    }

    private function readFromDisk(string $path, ?string $disk): ?string
    {
        if ($disk !== null && Storage::disk($disk)->exists($path)) {
            return (string) Storage::disk($disk)->get($path);
        }

        if (Storage::disk('public')->exists($path)) {
            return (string) Storage::disk('public')->get($path);
        }

        if ($this->mediaStorage->usesR2() && Storage::disk('r2')->exists($path)) {
            return (string) Storage::disk('r2')->get($path);
        }

        return null;
    }

    private function downloadUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '' || ! str_starts_with($url, 'http')) {
            return null;
        }

        try {
            $response = Http::timeout(30)
                ->retry(1, 300)
                ->withOptions([
                    'verify' => (bool) config('bnc.product_image_verify_ssl', true),
                ])
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();

        return $body !== '' ? $body : null;
    }
}
