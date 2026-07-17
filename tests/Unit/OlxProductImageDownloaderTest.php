<?php

namespace Tests\Unit;

use App\Services\Olx\OlxProductImageDownloader;
use Tests\TestCase;

class OlxProductImageDownloaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['bnc.olx_image_watermark_enabled' => false]);
    }

    public function test_flattens_transparent_png_onto_white_background(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        $png = $this->makeTransparentPng();

        $jpeg = $this->convertBytesToJpeg($png);

        $this->assertNotNull($jpeg);
        $this->assertStringStartsWith("\xFF\xD8\xFF", $jpeg);

        $image = imagecreatefromstring($jpeg);
        $this->assertNotFalse($image);

        $topLeft = imagecolorat($image, 2, 2);
        $center = imagecolorat($image, 50, 50);

        imagedestroy($image);

        $this->assertSame(0xFFFFFF, $topLeft & 0xFFFFFF, 'Transparent corners should flatten to white.');
        $this->assertRedish($center);
    }

    public function test_converts_palette_png_with_transparency(): void
    {
        if (! function_exists('imagecreate')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        $png = $this->makePaletteTransparentPng();

        $jpeg = $this->convertBytesToJpeg($png);

        $this->assertNotNull($jpeg);

        $image = imagecreatefromstring($jpeg);
        $this->assertNotFalse($image);

        $topLeft = imagecolorat($image, 1, 1);
        $center = imagecolorat($image, 40, 40);

        imagedestroy($image);

        $this->assertWhitish($topLeft);
        $this->assertBluish($center);
    }

    private function assertWhitish(int $color): void
    {
        $red = ($color >> 16) & 0xFF;
        $green = ($color >> 8) & 0xFF;
        $blue = $color & 0xFF;

        $this->assertGreaterThanOrEqual(250, $red);
        $this->assertGreaterThanOrEqual(250, $green);
        $this->assertGreaterThanOrEqual(250, $blue);
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

    private function convertBytesToJpeg(string $bytes): ?string
    {
        $downloader = app(OlxProductImageDownloader::class);
        $method = new \ReflectionMethod($downloader, 'normalizeForOlx');
        $method->setAccessible(true);

        /** @var array{contents: string}|null $result */
        $result = $method->invoke($downloader, $bytes, 'https://example.test/image.png', 0);

        return $result['contents'] ?? null;
    }

    private function makeTransparentPng(): string
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

    private function makePaletteTransparentPng(): string
    {
        $image = imagecreate(80, 80);
        $blue = imagecolorallocate($image, 0, 0, 255);
        $transparent = imagecolorallocate($image, 255, 0, 255);
        imagecolortransparent($image, $transparent);
        imagefill($image, 0, 0, $transparent);
        imagefilledellipse($image, 40, 40, 60, 60, $blue);

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return $png;
    }
}
