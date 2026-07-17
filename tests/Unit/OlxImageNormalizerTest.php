<?php

namespace Tests\Unit;

use App\Services\Olx\OlxImageAlphaCompositor;
use App\Services\Olx\OlxImageNormalizer;
use Tests\TestCase;

class OlxImageNormalizerTest extends TestCase
{
    public function test_flattens_transparent_png_without_color_artifacts(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        $png = $this->makeTransparentPngWithRedCenter();
        $jpeg = app(OlxImageNormalizer::class)->toJpeg($png);

        $this->assertNotNull($jpeg);

        $image = imagecreatefromstring($jpeg);
        $this->assertNotFalse($image);

        $corner = imagecolorat($image, 2, 2);
        $center = imagecolorat($image, 50, 50);

        imagedestroy($image);

        $this->assertSame(0xFFFFFF, $corner & 0xFFFFFF);
        $this->assertRedish($center);
    }

    public function test_compositor_keeps_opaque_pixels_on_white_background(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        $source = imagecreatetruecolor(40, 40);
        imagealphablending($source, false);
        imagesavealpha($source, true);
        $transparent = imagecolorallocatealpha($source, 0, 0, 0, 127);
        imagefill($source, 0, 0, $transparent);
        $blue = imagecolorallocatealpha($source, 0, 0, 255, 0);
        imagefilledellipse($source, 20, 20, 30, 30, $blue);

        $flattened = app(OlxImageAlphaCompositor::class)->flattenOntoBackground($source, [255, 255, 255]);

        $corner = imagecolorat($flattened, 1, 1);
        $center = imagecolorat($flattened, 20, 20);
        imagedestroy($flattened);

        $this->assertSame(0xFFFFFF, $corner & 0xFFFFFF);
        $this->assertBluish($center);
    }

    private function assertRedish(int $color): void
    {
        $red = ($color >> 16) & 0xFF;
        $green = ($color >> 8) & 0xFF;
        $blue = $color & 0xFF;

        $this->assertGreaterThanOrEqual(230, $red);
        $this->assertLessThanOrEqual(30, $green);
        $this->assertLessThanOrEqual(30, $blue);
    }

    private function assertBluish(int $color): void
    {
        $red = ($color >> 16) & 0xFF;
        $green = ($color >> 8) & 0xFF;
        $blue = $color & 0xFF;

        $this->assertLessThanOrEqual(30, $red);
        $this->assertLessThanOrEqual(30, $green);
        $this->assertGreaterThanOrEqual(230, $blue);
    }

    private function makeTransparentPngWithRedCenter(): string
    {
        $image = imagecreatetruecolor(100, 100);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        $red = imagecolorallocate($image, 255, 0, 0);
        imagefilledellipse($image, 50, 50, 80, 80, $red);

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return $png;
    }
}
