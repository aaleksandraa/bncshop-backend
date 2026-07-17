<?php

namespace App\Services\Olx;

use GdImage;

class OlxImageAlphaCompositor
{
    /**
     * @param  array{0: int, 1: int, 2: int}  $backgroundRgb
     */
    public function flattenOntoBackground(GdImage $source, array $backgroundRgb): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);

        [$bgRed, $bgGreen, $bgBlue] = $backgroundRgb;

        $canvas = imagecreatetruecolor($width, $height);
        $background = imagecolorallocate($canvas, $bgRed, $bgGreen, $bgBlue);
        imagefill($canvas, 0, 0, $background);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, false);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($source, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;

                if ($alpha >= 127) {
                    continue;
                }

                $red = ($rgba >> 16) & 0xFF;
                $green = ($rgba >> 8) & 0xFF;
                $blue = $rgba & 0xFF;
                $opacity = (127 - $alpha) / 127;

                $blendedRed = (int) round($red * $opacity + $bgRed * (1 - $opacity));
                $blendedGreen = (int) round($green * $opacity + $bgGreen * (1 - $opacity));
                $blendedBlue = (int) round($blue * $opacity + $bgBlue * (1 - $opacity));

                $color = imagecolorallocate($canvas, $blendedRed, $blendedGreen, $blendedBlue);
                imagesetpixel($canvas, $x, $y, $color);
            }
        }

        imagedestroy($source);

        return $canvas;
    }

    public function blendOverlay(GdImage $canvas, GdImage $overlay, int $destX, int $destY): void
    {
        $overlayWidth = imagesx($overlay);
        $overlayHeight = imagesy($overlay);
        $canvasWidth = imagesx($canvas);
        $canvasHeight = imagesy($canvas);

        for ($y = 0; $y < $overlayHeight; $y++) {
            $targetY = $destY + $y;

            if ($targetY < 0 || $targetY >= $canvasHeight) {
                continue;
            }

            for ($x = 0; $x < $overlayWidth; $x++) {
                $targetX = $destX + $x;

                if ($targetX < 0 || $targetX >= $canvasWidth) {
                    continue;
                }

                $rgba = imagecolorat($overlay, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;

                if ($alpha >= 127) {
                    continue;
                }

                $red = ($rgba >> 16) & 0xFF;
                $green = ($rgba >> 8) & 0xFF;
                $blue = $rgba & 0xFF;
                $opacity = (127 - $alpha) / 127;

                $baseRgba = imagecolorat($canvas, $targetX, $targetY);
                $baseRed = ($baseRgba >> 16) & 0xFF;
                $baseGreen = ($baseRgba >> 8) & 0xFF;
                $baseBlue = $baseRgba & 0xFF;

                $blendedRed = (int) round($red * $opacity + $baseRed * (1 - $opacity));
                $blendedGreen = (int) round($green * $opacity + $baseGreen * (1 - $opacity));
                $blendedBlue = (int) round($blue * $opacity + $baseBlue * (1 - $opacity));

                $color = imagecolorallocate($canvas, $blendedRed, $blendedGreen, $blendedBlue);
                imagesetpixel($canvas, $targetX, $targetY, $color);
            }
        }
    }

    public function toTrueColor(GdImage $image): GdImage
    {
        if (imageistruecolor($image)) {
            imagealphablending($image, false);
            imagesavealpha($image, true);

            return $image;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $truecolor = imagecreatetruecolor($width, $height);
        imagealphablending($truecolor, false);
        imagesavealpha($truecolor, true);

        $transparent = imagecolorallocatealpha($truecolor, 0, 0, 0, 127);
        imagefill($truecolor, 0, 0, $transparent);
        imagealphablending($truecolor, true);
        imagecopy($truecolor, $image, 0, 0, 0, 0, $width, $height);
        imagedestroy($image);

        return $truecolor;
    }
}
