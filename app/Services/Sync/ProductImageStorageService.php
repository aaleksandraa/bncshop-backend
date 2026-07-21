<?php

namespace App\Services\Sync;

use App\Models\Product;
use App\Models\ProductImage;
use App\Support\PublicStorageUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProductImageStorageService
{
    /**
     * Download a remote product image and persist it on the public disk.
     */
    public function storeFromRemote(ProductImage $image, Product $product, bool $force = false): bool
    {
        $remoteUrl = $this->resolveDownloadUrl($image);

        if ($remoteUrl === null) {
            return false;
        }

        if (
            ! $force
            && filled($image->local_path)
            && Storage::disk('public')->exists($image->local_path)
            && $this->remoteUrlUnchanged($image, $remoteUrl)
        ) {
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

        $extension = $this->resolveExtension($image, $remoteUrl, $response->header('Content-Type'));
        $fileName = $this->resolveFileName($image, $extension);
        $directory = 'products/'.Str::slug((string) $product->external_product_id, '_');
        $path = $directory.'/'.$fileName;

        if ($image->local_path && $image->local_path !== $path) {
            Storage::disk('public')->delete($image->local_path);
        }

        Storage::disk('public')->put($path, $contents);

        [$width, $height] = $this->resolveDimensions($contents);

        $image->forceFill([
            'local_path' => $path,
            'image_url' => PublicStorageUrl::url($path),
            'file_size_bytes' => strlen($contents),
            'width' => $width,
            'height' => $height,
            'file_extension' => $extension,
        ])->save();

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
        if (filled($image->local_path)) {
            if ((bool) config('bnc.trust_local_image_path', true)) {
                return PublicStorageUrl::url((string) $image->local_path);
            }

            if (Storage::disk('public')->exists($image->local_path)) {
                return PublicStorageUrl::url((string) $image->local_path);
            }
        }

        return $image->public_url ?: $image->image_url ?: $image->source_url;
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

            if ($url !== '') {
                return $url;
            }
        }

        return null;
    }

    private function remoteUrlUnchanged(ProductImage $image, string $remoteUrl): bool
    {
        $tracked = trim((string) ($image->public_url ?: $image->source_url ?: ''));

        return $tracked === '' || $tracked === $remoteUrl;
    }

    private function resolveExtension(ProductImage $image, string $remoteUrl, ?string $contentType): string
    {
        if (filled($image->file_extension)) {
            return Str::lower((string) $image->file_extension);
        }

        $pathExtension = Str::lower((string) pathinfo(parse_url($remoteUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

        if (in_array($pathExtension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'], true)) {
            return $pathExtension === 'jpeg' ? 'jpg' : $pathExtension;
        }

        return match (Str::lower((string) $contentType)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
            default => 'jpg',
        };
    }

    private function resolveFileName(ProductImage $image, string $extension): string
    {
        $base = filled($image->external_image_id)
            ? (string) $image->external_image_id
            : 'image-'.$image->id;

        return $base.'.'.$extension;
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function resolveDimensions(string $contents): array
    {
        $info = @getimagesizefromstring($contents);

        if (! is_array($info)) {
            return [null, null];
        }

        return [
            isset($info[0]) ? (int) $info[0] : null,
            isset($info[1]) ? (int) $info[1] : null,
        ];
    }
}
