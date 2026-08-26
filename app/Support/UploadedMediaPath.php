<?php

namespace App\Support;

use App\Services\Media\MediaStorage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class UploadedMediaPath
{
    /**
     * Filament FileUpload dehydrates as a uuid-keyed array. Writing that onto a
     * string column 500s on Laravel 11 ("Array to string conversion").
     */
    public static function normalize(mixed $value, ?string $storeDirectory = null): ?string
    {
        if ($value instanceof TemporaryUploadedFile) {
            return $storeDirectory === null
                ? null
                : self::storeTemporary($value, $storeDirectory);
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $normalized = self::normalize($item, $storeDirectory);
                if ($normalized !== null) {
                    return $normalized;
                }
            }

            return null;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            $fromUrl = self::pathFromPublicUrl($value);
            if ($fromUrl !== null) {
                return $fromUrl;
            }
        }

        $path = ltrim(str_replace('\\', '/', $value), '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        return $path;
    }

    private static function pathFromPublicUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        return $path;
    }

    private static function storeTemporary(TemporaryUploadedFile $file, string $directory): ?string
    {
        try {
            $realPath = $file->getRealPath();
            if (! is_string($realPath) || $realPath === '' || ! is_readable($realPath)) {
                return null;
            }

            $contents = (string) file_get_contents($realPath);
            if ($contents === '') {
                return null;
            }

            $original = $file->getClientOriginalName();
            $baseName = pathinfo($original, PATHINFO_FILENAME);
            $baseName = Str::slug($baseName) ?: (string) Str::uuid();
            $mediaStorage = app(MediaStorage::class);

            if (str_ends_with(strtolower($original), '.svg')) {
                return $mediaStorage->storeOptimized(
                    trim($directory, '/').'/'.$baseName.'.svg',
                    $contents,
                )->key;
            }

            return $mediaStorage->storeFromBinary($contents, $directory, $baseName)->key;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
