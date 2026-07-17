<?php

namespace App\Services\Olx;

use GdImage;
use Throwable;

class OlxImageNormalizer
{
    private const JPEG_QUALITY = 92;

    /** @var array{0: int, 1: int, 2: int} */
    private const BACKGROUND_RGB = [255, 255, 255];

    public function __construct(
        private readonly OlxImageAlphaCompositor $compositor,
    ) {}

    public function toJpeg(string $bytes): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = $this->decode($bytes);

        if ($source === null) {
            return null;
        }

        $flattened = $this->compositor->flattenOntoBackground($source, self::BACKGROUND_RGB);

        return $this->encodeJpeg($flattened);
    }

    private function decode(string $bytes): ?GdImage
    {
        if ($this->isPng($bytes)) {
            $source = @imagecreatefrompng('data://application/octet-stream;base64,'.base64_encode($bytes));
        } elseif ($this->isWebp($bytes)) {
            if (! function_exists('imagecreatefromwebp')) {
                return null;
            }

            $source = @imagecreatefromwebp('data://application/octet-stream;base64,'.base64_encode($bytes));

            if ($source === false) {
                $tmp = tempnam(sys_get_temp_dir(), 'olxwebp');
                file_put_contents($tmp, $bytes);
                $source = @imagecreatefromwebp($tmp);
                @unlink($tmp);
            }
        } else {
            $source = @imagecreatefromstring($bytes);
        }

        if ($source === false) {
            return null;
        }

        return $this->compositor->toTrueColor($source);
    }

    private function encodeJpeg(GdImage $image): ?string
    {
        ob_start();
        $ok = imagejpeg($image, null, self::JPEG_QUALITY);
        imagedestroy($image);
        $jpeg = ob_get_clean();

        return ($ok && is_string($jpeg) && $jpeg !== '') ? $jpeg : null;
    }

    private function isPng(string $bytes): bool
    {
        return str_starts_with($bytes, "\x89PNG\r\n\x1a\n");
    }

    private function isWebp(string $bytes): bool
    {
        return strlen($bytes) >= 12
            && str_starts_with($bytes, 'RIFF')
            && substr($bytes, 8, 4) === 'WEBP';
    }
}
