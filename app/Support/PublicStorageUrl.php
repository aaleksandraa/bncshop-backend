<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class PublicStorageUrl
{
    /**
     * Build a URL for a file on the public disk.
     *
     * Uploaded assets are returned as site-root relative paths so API consumers
     * can resolve them against their configured backend origin.
     */
    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return '/storage/'.ltrim($path, '/');
    }

    /**
     * Absolute URL for contexts that require a full origin (e.g. emails, Filament).
     */
    public static function absoluteUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return self::absoluteFromResolved(self::url($path));
    }

    /**
     * Rewrite any /storage/ URLs inside cached API payloads at response time.
     *
     * @param  mixed  $value
     * @return mixed
     */
    public static function rewriteStorageUrlsInValue(mixed $value): mixed
    {
        if (is_string($value)) {
            if (! str_contains($value, '/storage/')) {
                return $value;
            }

            return self::absoluteFromResolved($value) ?? $value;
        }

        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = self::rewriteStorageUrlsInValue($item);
        }

        return $value;
    }

    /**
     * Normalize a resolved image URL for API consumers.
     */
    public static function absoluteFromResolved(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return self::normalizeStorageUrl(self::rewriteLocalhostStorageUrl($url));
        }

        if (str_starts_with($url, '/')) {
            return self::normalizeStorageUrl($url);
        }

        return self::absoluteUrl($url);
    }

    /**
     * CDN origin for optimized media (images.bnc.ba). Null keeps legacy routing.
     */
    public static function mediaOrigin(): ?string
    {
        $origin = config('bnc.media_origin');

        if (! is_string($origin) || trim($origin) === '') {
            return null;
        }

        return rtrim(trim($origin), '/');
    }

    /**
     * Default public origin for legacy synced assets.
     */
    public static function storageOrigin(): string
    {
        return self::legacyStorageOrigin();
    }

    public static function appStorageOrigin(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public static function legacyStorageOrigin(): string
    {
        $legacyUrl = config('bnc.legacy_storage_url');

        if (is_string($legacyUrl) && trim($legacyUrl) !== '') {
            return rtrim(trim($legacyUrl), '/');
        }

        $origin = self::appStorageOrigin();

        if (app()->environment('production') && str_contains($origin, 'localhost')) {
            return 'https://api.bncshop.ba';
        }

        if (
            app()->environment('production')
            && str_ends_with(parse_url($origin, PHP_URL_HOST) ?: '', 'api.bnc.ba')
        ) {
            return 'https://api.bncshop.ba';
        }

        return $origin;
    }

    public static function storageOriginForPath(string $storagePath): string
    {
        if ($mediaOrigin = self::mediaOrigin()) {
            return $mediaOrigin;
        }

        if (self::isSellerManagedStoragePath($storagePath)) {
            return self::appStorageOrigin();
        }

        if (self::existsOnAppPublicDisk($storagePath)) {
            return self::appStorageOrigin();
        }

        return self::legacyStorageOrigin();
    }

    /**
     * Whether the file for a /storage/... URL exists on this app's public disk.
     */
    public static function existsOnAppPublicDisk(string $storagePath): bool
    {
        if (! str_starts_with($storagePath, '/storage/')) {
            return false;
        }

        $relativePath = ltrim(substr($storagePath, strlen('/storage/')), '/');

        if ($relativePath === '') {
            return false;
        }

        return Storage::disk('public')->exists($relativePath);
    }

    public static function isSellerManagedStoragePath(string $storagePath): bool
    {
        return (bool) preg_match('#/seller-[a-f0-9-]+\.(?:jpg|jpeg|png|webp|gif|avif)$#i', $storagePath);
    }

    private static function normalizeStorageUrl(string $url): string
    {
        $parts = parse_url($url);
        $path = (string) ($parts['path'] ?? '');

        if (! str_starts_with($path, '/storage/')) {
            return $url;
        }

        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        if ($mediaOrigin = self::mediaOrigin()) {
            $relativePath = ltrim(substr($path, strlen('/storage/')), '/');

            return $mediaOrigin.'/'.$relativePath.$query;
        }

        return self::storageOriginForPath($path).$path.$query;
    }

    private static function rewriteLocalhostStorageUrl(string $url): string
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        if (
            in_array($host, ['localhost', '127.0.0.1'], true)
            && str_starts_with($path, '/storage/')
        ) {
            $query = isset($parts['query']) ? '?'.$parts['query'] : '';

            return self::storageOriginForPath($path).$path.$query;
        }

        return $url;
    }
}
