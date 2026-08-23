<?php

namespace App\Services\Sync;

use App\Jobs\OptimizeAndUploadImage;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Media\MediaStorage;
use App\Support\PublicStorageUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProductImageStorageService
{
    public function __construct(
        private readonly MediaStorage $mediaStorage,
    ) {}

    /**
     * Download a remote product image and queue optimization/upload to media storage.
     *
     * Already-stored R2 copies are left alone so an A1 CDN host change does not
     * re-download the catalogue. New images (no local_path) always go to R2.
     */
    public function storeFromRemote(ProductImage $image, Product $product, bool $force = false): bool
    {
        $remoteUrl = $this->resolveDownloadUrl($image);

        if ($remoteUrl === null) {
            return false;
        }

        if (! $force && filled($image->local_path)) {
            return true;
        }

        try {
            $response = Http::timeout((int) config('bnc.product_image_download_timeout', 30))
                ->retry(2, 500)
                ->withOptions([
                    'verify' => (bool) config('bnc.product_image_verify_ssl', true),
                ])
                ->get($remoteUrl);
        } catch (Throwable) {
            return false;
        }

        if (! $response->successful()) {
            return false;
        }

        $contents = $response->body();

        if ($contents === '') {
            return false;
        }

        $fileName = $this->resolveFileName($image);
        $directory = 'products/'.Str::slug((string) $product->external_product_id, '_');
        $path = $directory.'/'.$fileName;

        $tempPath = 'temp/media-jobs/product-image-'.$image->id.'-'.Str::uuid()->toString().'.bin';
        Storage::disk('local')->put($tempPath, $contents);

        OptimizeAndUploadImage::dispatch(
            modelType: 'product_image',
            modelId: (int) $image->id,
            tempPath: $tempPath,
            targetKey: $path,
            previousKey: $image->local_path,
            previousDisk: $image->storage_disk,
        );

        $this->forgetResolvedUrlCache($image);

        return true;
    }

    public function resolvedUrl(ProductImage $image): ?string
    {
        $cacheKey = $this->resolvedUrlCacheKey($image);
        $ttl = (int) config('bnc.resolved_image_url_cache_ttl', 3600);

        if ($cacheKey !== null && $ttl > 0) {
            $cached = Cache::get($cacheKey);

            if (is_string($cached)) {
                return $cached !== '' ? $cached : null;
            }
        }

        $resolved = $this->resolveUrlWithoutCache($image);

        if ($cacheKey !== null && $ttl > 0) {
            Cache::put($cacheKey, $resolved ?? '', $ttl);
        }

        return $resolved;
    }

    public function forgetResolvedUrlCache(ProductImage $image): void
    {
        $cacheKey = $this->resolvedUrlCacheKey($image);

        if ($cacheKey !== null) {
            Cache::forget($cacheKey);
        }
    }

    private function resolveUrlWithoutCache(ProductImage $image): ?string
    {
        if (filled($image->local_path) && $this->shouldUseLocalPath($image)) {
            return PublicStorageUrl::url((string) $image->local_path);
        }

        return $image->public_url ?: $image->image_url ?: $image->source_url;
    }

    private function shouldUseLocalPath(ProductImage $image): bool
    {
        if (blank($image->local_path)) {
            return false;
        }

        if (config('bnc.trust_local_image_path', true)) {
            return true;
        }

        return $this->mediaExists($image);
    }

    private function mediaExists(ProductImage $image): bool
    {
        if (blank($image->local_path)) {
            return false;
        }

        if ($image->storage_disk === 'r2' || ($image->storage_disk === null && $this->mediaStorage->usesR2())) {
            return $this->mediaStorage->exists((string) $image->local_path);
        }

        return Storage::disk('public')->exists((string) $image->local_path);
    }

    private function resolvedUrlCacheKey(ProductImage $image): ?string
    {
        if (! $image->id) {
            return null;
        }

        return 'product-image:resolved-url:'.$image->id;
    }

    private function resolveDownloadUrl(ProductImage $image): ?string
    {
        foreach ([$image->public_url, $image->image_url, $image->source_url] as $candidate) {
            $url = trim((string) $candidate);

            if ($this->isRemoteSourceUrl($url)) {
                return $url;
            }
        }

        return null;
    }

    /**
     * Only fetch from supplier CDNs. Skip our own /storage and images.bnc.ba URLs.
     */
    private function isRemoteSourceUrl(string $url): bool
    {
        if ($url === '' || str_starts_with($url, '/')) {
            return false;
        }

        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '') {
            return false;
        }

        if (
            $host === 'images.bnc.ba'
            || $host === 'bnc.ba'
            || $host === 'bncshop.ba'
            || str_ends_with($host, '.bnc.ba')
            || str_ends_with($host, '.bncshop.ba')
        ) {
            return false;
        }

        return true;
    }

    private function resolveFileName(ProductImage $image): string
    {
        $base = filled($image->external_image_id)
            ? (string) $image->external_image_id
            : 'image-'.$image->id;

        return $base.'.webp';
    }
}
