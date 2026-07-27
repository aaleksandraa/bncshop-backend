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

        return Storage::disk('public')->url($path);
    }

    /**
     * Normalize a resolved image URL for API consumers.
     *
     * Rewrites legacy localhost storage URLs to the configured public asset origin.
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

    public static function storageOrigin(): string
    {
        $assetUrl = config('app.asset_url');

        if (is_string($assetUrl) && trim($assetUrl) !== '') {
            return rtrim(trim($assetUrl), '/');
        }

        $origin = rtrim((string) config('app.url'), '/');

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

    private static function normalizeStorageUrl(string $url): string
    {
        $parts = parse_url($url);
        $path = (string) ($parts['path'] ?? '');

        if (! str_starts_with($path, '/storage/')) {
            return $url;
        }

        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return self::storageOrigin().$path.$query;
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

            return self::storageOrigin().$path.$query;
        }

        return $url;
    }
}
