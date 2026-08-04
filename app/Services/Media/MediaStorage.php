<?php

namespace App\Services\Media;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class StoredMediaResult
{
    public function __construct(
        public readonly string $key,
        public readonly string $disk,
        public readonly int $width,
        public readonly int $height,
        public readonly int $bytes,
    ) {}
}

class MediaStorage
{
    public function __construct(
        private readonly ImageOptimizer $optimizer,
    ) {}

    public function diskName(): string
    {
        return $this->usesR2() ? (string) config('bnc.media_disk', 'r2') : 'public';
    }

    public function usesR2(): bool
    {
        return filled(config('filesystems.disks.r2.key'))
            && filled(config('filesystems.disks.r2.secret'))
            && filled(config('filesystems.disks.r2.endpoint'));
    }

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    public function exists(string $key): bool
    {
        return $this->disk()->exists($this->normalizeKey($key));
    }

    public function existsOnAnyDisk(string $key, ?string $disk = null): bool
    {
        $key = $this->normalizeKey($key);

        if ($disk !== null && Storage::disk($disk)->exists($key)) {
            return true;
        }

        if (Storage::disk('public')->exists($key)) {
            return true;
        }

        if ($this->usesR2() && Storage::disk('r2')->exists($key)) {
            return true;
        }

        return false;
    }

    /**
     * Optimize and persist a binary payload at the given storage key.
     */
    public function storeOptimized(string $targetKey, string $contents, ?string $previousKey = null): StoredMediaResult
    {
        $set = $this->optimizer->optimize($contents, $targetKey);

        if ($previousKey !== null && $previousKey !== $set->masterKey) {
            $this->delete($previousKey);
        }

        $this->putObject($set->masterKey, $set->masterContents, $set->passthrough);

        foreach ($set->variants as $width => $variantContents) {
            $this->putObject(
                $this->optimizer->variantKey($set->masterKey, $width),
                $variantContents,
            );
        }

        return new StoredMediaResult(
            key: $set->masterKey,
            disk: $this->diskName(),
            width: $set->width,
            height: $set->height,
            bytes: strlen($set->masterContents),
        );
    }

    /**
     * Store an uploaded file under a directory with a generated or provided base name.
     */
    public function storeFromBinary(string $contents, string $directory, ?string $baseName = null): StoredMediaResult
    {
        $baseName = $baseName ?: (string) Str::uuid();
        $targetKey = trim($directory, '/').'/'.Str::slug($baseName).'.webp';

        return $this->storeOptimized($targetKey, $contents);
    }

    public function delete(?string $key): void
    {
        if (blank($key)) {
            return;
        }

        $key = $this->normalizeKey($key);
        $disk = $this->disk();

        if (! $disk->exists($key)) {
            return;
        }

        $disk->delete($key);

        if (! str_ends_with(strtolower($key), '.webp')) {
            return;
        }

        foreach ($this->optimizer->variantWidths() as $width) {
            $variantKey = $this->optimizer->variantKey($key, $width);

            if ($disk->exists($variantKey)) {
                $disk->delete($variantKey);
            }
        }
    }

    public function deleteFromAnyDisk(?string $key, ?string $disk = null): void
    {
        if (blank($key)) {
            return;
        }

        if ($disk !== null) {
            $this->deleteFromDisk($key, $disk);

            return;
        }

        $this->deleteFromDisk($key, 'public');
        $this->deleteFromDisk($key, 'r2');
    }

    private function deleteFromDisk(string $key, string $diskName): void
    {
        $disk = Storage::disk($diskName);
        $key = $this->normalizeKey($key);

        if (! $disk->exists($key)) {
            return;
        }

        $disk->delete($key);

        if (! str_ends_with(strtolower($key), '.webp')) {
            return;
        }

        foreach ($this->optimizer->variantWidths() as $width) {
            $variantKey = $this->optimizer->variantKey($key, $width);

            if ($disk->exists($variantKey)) {
                $disk->delete($variantKey);
            }
        }
    }

    private function putObject(string $key, string $contents, bool $passthrough = false): void
    {
        $key = $this->normalizeKey($key);
        $contentType = $passthrough && str_ends_with(strtolower($key), '.svg')
            ? 'image/svg+xml'
            : 'image/webp';

        $this->disk()->put($key, $contents, [
            'CacheControl' => 'public, max-age=31536000, immutable',
            'ContentType' => $contentType,
        ]);
    }

    private function normalizeKey(string $key): string
    {
        return ltrim(str_replace('\\', '/', $key), '/');
    }
}
