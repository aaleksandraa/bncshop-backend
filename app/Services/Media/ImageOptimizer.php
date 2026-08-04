<?php

namespace App\Services\Media;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

class ImageOptimizer
{
    private ImageManager $manager;

    public function __construct()
    {
        $driver = extension_loaded('imagick')
            ? new ImagickDriver
            : new GdDriver;

        $this->manager = new ImageManager($driver);
    }

    /**
     * Optimize raw bytes into a WebP master and responsive variants.
     *
     * SVG and other non-raster inputs are returned unchanged (passthrough).
     */
    public function optimize(string $contents, string $targetKey): OptimizedImageSet
    {
        if ($this->isSvg($contents, $targetKey)) {
            return new OptimizedImageSet(
                masterKey: $targetKey,
                masterContents: $contents,
                width: 0,
                height: 0,
                variants: [],
                passthrough: true,
            );
        }

        $image = $this->manager->read($contents);
        $image->orient();

        $masterMaxWidth = (int) config('bnc.media_master_max_width', 1600);
        $image->scaleDown(width: $masterMaxWidth, height: $masterMaxWidth);

        $masterWidth = $image->width();
        $masterHeight = $image->height();
        $quality = (int) config('bnc.media_webp_quality', 82);

        $masterKey = $this->ensureWebpExtension($targetKey);
        $masterContents = (string) $image->toWebp(quality: $quality);

        $variants = [];
        foreach ($this->variantWidths() as $variantWidth) {
            if ($masterWidth <= $variantWidth) {
                continue;
            }

            $variantImage = $this->manager->read($masterContents);
            $variantImage->scaleDown(width: $variantWidth);
            $variants[$variantWidth] = (string) $variantImage->toWebp(quality: $quality);
        }

        return new OptimizedImageSet(
            masterKey: $masterKey,
            masterContents: $masterContents,
            width: $masterWidth,
            height: $masterHeight,
            variants: $variants,
        );
    }

    /**
     * @return list<int>
     */
    public function variantWidths(): array
    {
        $widths = config('bnc.media_variant_widths', [320, 640, 1280]);

        return array_values(array_unique(array_map('intval', $widths)));
    }

    public function variantKey(string $masterKey, int $width): string
    {
        $masterKey = $this->ensureWebpExtension($masterKey);
        $extension = '.webp';
        $base = str_ends_with(strtolower($masterKey), $extension)
            ? substr($masterKey, 0, -strlen($extension))
            : $masterKey;

        return $base.'_'.$width.$extension;
    }

    public function ensureWebpExtension(string $key): string
    {
        $pathInfo = pathinfo($key);
        $directory = isset($pathInfo['dirname']) && $pathInfo['dirname'] !== '.'
            ? $pathInfo['dirname'].'/'
            : '';

        $filename = $pathInfo['filename'] ?? 'image';

        return $directory.$filename.'.webp';
    }

    private function isSvg(string $contents, string $targetKey): bool
    {
        if (str_ends_with(strtolower($targetKey), '.svg')) {
            return true;
        }

        $snippet = ltrim(substr($contents, 0, 256));

        return str_starts_with($snippet, '<svg') || str_contains($snippet, '<svg');
    }
}
