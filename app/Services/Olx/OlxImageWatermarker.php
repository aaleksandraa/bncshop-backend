<?php

namespace App\Services\Olx;

use GdImage;

class OlxImageWatermarker
{
    private const JPEG_QUALITY = 92;

    public function __construct(
        private readonly OlxImageAlphaCompositor $compositor,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('bnc.olx_image_watermark_enabled', true)
            && is_file($this->logoPath());
    }

    public function versionToken(): string
    {
        if (! $this->isEnabled()) {
            return 'none';
        }

        $path = $this->logoPath();
        $ratio = (string) config('bnc.olx_image_watermark_width_ratio', 0.333333);
        $bottomOffset = (string) config('bnc.olx_image_watermark_bottom_offset_ratio', 0.2);

        return 'logo:'.hash('xxh128', $path.filemtime($path).':'.$ratio.':'.$bottomOffset);
    }

    public function applyToJpeg(string $jpegBytes): ?string
    {
        if (! $this->isEnabled() || ! function_exists('imagecreatefromstring')) {
            return $jpegBytes;
        }

        $canvas = @imagecreatefromstring($jpegBytes);

        if ($canvas === false) {
            return null;
        }

        $logo = $this->loadLogo();

        if ($logo === null) {
            imagedestroy($canvas);

            return $jpegBytes;
        }

        $resizedLogo = $this->resizeLogo($canvas, $logo);
        imagedestroy($logo);

        if ($resizedLogo === null) {
            imagedestroy($canvas);

            return $jpegBytes;
        }

        [$destX, $destY] = $this->position($canvas, $resizedLogo);
        $this->compositor->blendOverlay($canvas, $resizedLogo, $destX, $destY);
        imagedestroy($resizedLogo);

        ob_start();
        $ok = imagejpeg($canvas, null, self::JPEG_QUALITY);
        imagedestroy($canvas);
        $jpeg = ob_get_clean();

        return ($ok && is_string($jpeg) && $jpeg !== '') ? $jpeg : $jpegBytes;
    }

    private function logoPath(): string
    {
        return (string) config('bnc.olx_image_watermark_path', resource_path('olx/bnc-logo.png'));
    }

    private function loadLogo(): ?GdImage
    {
        $path = $this->logoPath();

        if (! is_file($path)) {
            return null;
        }

        $logo = @imagecreatefrompng($path);

        if ($logo === false) {
            return null;
        }

        $logo = $this->compositor->toTrueColor($logo);

        return $this->makeNearBlackTransparent($logo);
    }

    private function resizeLogo(GdImage $canvas, GdImage $logo): ?GdImage
    {
        $canvasWidth = imagesx($canvas);
        $canvasHeight = imagesy($canvas);
        $logoWidth = imagesx($logo);
        $logoHeight = imagesy($logo);

        if ($canvasWidth <= 0 || $canvasHeight <= 0 || $logoWidth <= 0 || $logoHeight <= 0) {
            return null;
        }

        $widthRatio = max(0.05, min(0.5, (float) config('bnc.olx_image_watermark_width_ratio', 0.333333)));
        $targetWidth = max(1, (int) round($canvasWidth * $widthRatio));
        $targetHeight = max(1, (int) round($logoHeight * ($targetWidth / $logoWidth)));

        $resizedLogo = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($resizedLogo, false);
        imagesavealpha($resizedLogo, true);

        $transparent = imagecolorallocatealpha($resizedLogo, 0, 0, 0, 127);
        imagefill($resizedLogo, 0, 0, $transparent);
        imagealphablending($resizedLogo, true);

        imagecopyresampled(
            $resizedLogo,
            $logo,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $logoWidth,
            $logoHeight,
        );

        return $resizedLogo;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function position(GdImage $canvas, GdImage $logo): array
    {
        $canvasWidth = imagesx($canvas);
        $canvasHeight = imagesy($canvas);
        $logoWidth = imagesx($logo);
        $logoHeight = imagesy($logo);
        $padding = max(8, (int) round($canvasWidth * 0.02));
        $bottomOffsetRatio = max(0.0, min(0.5, (float) config('bnc.olx_image_watermark_bottom_offset_ratio', 0.2)));
        $bottomGap = (int) round($canvasHeight * $bottomOffsetRatio);
        $destY = $canvasHeight - $logoHeight - max($padding, $bottomGap);

        return [
            $padding,
            max($padding, $destY),
        ];
    }

    private function makeNearBlackTransparent(GdImage $logo): GdImage
    {
        $threshold = max(0, min(64, (int) config('bnc.olx_image_watermark_black_threshold', 24)));
        $width = imagesx($logo);
        $height = imagesy($logo);

        imagealphablending($logo, false);
        imagesavealpha($logo, true);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($logo, $x, $y);
                $red = ($rgba >> 16) & 0xFF;
                $green = ($rgba >> 8) & 0xFF;
                $blue = $rgba & 0xFF;

                if ($red <= $threshold && $green <= $threshold && $blue <= $threshold) {
                    $transparent = imagecolorallocatealpha($logo, 0, 0, 0, 127);
                    imagesetpixel($logo, $x, $y, $transparent);
                }
            }
        }

        return $logo;
    }
}
