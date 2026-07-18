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
     * Rewrites legacy localhost storage URLs to the configured APP_URL origin.
     */
    public static function absoluteFromResolved(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return self::rewriteLocalhostStorageUrl($url);
        }

        if (str_starts_with($url, '/')) {
            return rtrim((string) config('app.url'), '/').$url;
        }

        return self::absoluteUrl($url);
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

            return rtrim((string) config('app.url'), '/').$path.$query;
        }

        return $url;
    }
}
