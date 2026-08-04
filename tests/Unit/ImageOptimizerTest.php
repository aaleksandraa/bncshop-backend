<?php

namespace Tests\Unit;

use App\Services\Media\ImageOptimizer;
use Tests\TestCase;

class ImageOptimizerTest extends TestCase
{
    public function test_optimize_creates_webp_master_and_variants_for_large_image(): void
    {
        if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
            $this->markTestSkipped('GD or Imagick extension required.');
        }

        $optimizer = new ImageOptimizer;
        $png = $this->createTestPng(2000, 1200);

        $result = $optimizer->optimize($png, 'products/demo/sample.jpg');

        $this->assertSame('products/demo/sample.webp', $result->masterKey);
        $this->assertNotSame('', $result->masterContents);
        $this->assertLessThanOrEqual(1600, $result->width);
        $this->assertArrayHasKey(320, $result->variants);
        $this->assertArrayHasKey(640, $result->variants);
        $this->assertArrayHasKey(1280, $result->variants);
    }

    public function test_optimize_skips_upscaling_variants_for_small_image(): void
    {
        if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
            $this->markTestSkipped('GD or Imagick extension required.');
        }

        $optimizer = new ImageOptimizer;
        $png = $this->createTestPng(280, 280);

        $result = $optimizer->optimize($png, 'products/demo/tiny.png');

        $this->assertSame('products/demo/tiny.webp', $result->masterKey);
        $this->assertSame([], $result->variants);
    }

    public function test_optimize_passthrough_svg_without_variants(): void
    {
        $optimizer = new ImageOptimizer;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect width="100" height="100"/></svg>';

        $result = $optimizer->optimize($svg, 'manufacturers/logos/demo.svg');

        $this->assertTrue($result->passthrough);
        $this->assertSame($svg, $result->masterContents);
        $this->assertSame([], $result->variants);
    }

    public function test_variant_key_appends_width_before_extension(): void
    {
        $optimizer = new ImageOptimizer;

        $this->assertSame(
            'products/demo/sample_640.webp',
            $optimizer->variantKey('products/demo/sample.webp', 640),
        );
    }

    private function createTestPng(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        return $contents;
    }
}
