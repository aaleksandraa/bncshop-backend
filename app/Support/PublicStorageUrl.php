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
}
