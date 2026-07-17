<?php

namespace Tests\Unit;

use App\Services\Olx\OlxImageWatermarker;
use Tests\TestCase;

class OlxImageWatermarkerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'bnc.olx_image_watermark_enabled' => true,
            'bnc.olx_image_watermark_path' => resource_path('olx/bnc-logo.png'),
            'bnc.olx_image_watermark_width_ratio' => 0.333333,
            'bnc.olx_image_watermark_bottom_offset_ratio' => 0.2,
        ]);
    }

    public function test_positions_logo_with_bottom_offset_ratio(): void
    {
        if (! function_exists('imagecreatetruecolor') || ! is_file(resource_path('olx/bnc-logo.png'))) {
            $this->markTestSkipped('GD or logo asset is not available.');
        }

        $canvasHeight = 600;
        $source = imagecreatetruecolor(900, $canvasHeight);
        $white = imagecolorallocate($source, 255, 255, 255);
        imagefill($source, 0, 0, $white);

        ob_start();
        imagejpeg($source, null, 92);
        $jpeg = ob_get_clean();
        imagedestroy($source);

        $watermarked = app(OlxImageWatermarker::class)->applyToJpeg($jpeg);
        $image = imagecreatefromstring((string) $watermarked);
        $this->assertNotFalse($image);

        $bottomHasLogo = false;

        for ($y = (int) round($canvasHeight * 0.55); $y < $canvasHeight - (int) round($canvasHeight * 0.15); $y++) {
            if ((imagecolorat($image, 30, $y) & 0xFFFFFF) !== 0xFFFFFF) {
                $bottomHasLogo = true;
                break;
            }
        }

        $farBottom = imagecolorat($image, 30, $canvasHeight - 3);

        imagedestroy($image);

        $this->assertTrue($bottomHasLogo);
        $this->assertSame(0xFFFFFF, $farBottom & 0xFFFFFF);
    }

    public function test_applies_logo_to_bottom_left_of_image(): void
    {
        if (! function_exists('imagecreatetruecolor') || ! is_file(resource_path('olx/bnc-logo.png'))) {
            $this->markTestSkipped('GD or logo asset is not available.');
        }

        $source = imagecreatetruecolor(900, 600);
        $white = imagecolorallocate($source, 255, 255, 255);
        imagefill($source, 0, 0, $white);

        ob_start();
        imagejpeg($source, null, 92);
        $jpeg = ob_get_clean();
        imagedestroy($source);

        $watermarked = app(OlxImageWatermarker::class)->applyToJpeg($jpeg);

        $this->assertNotNull($watermarked);

        $image = imagecreatefromstring($watermarked);
        $this->assertNotFalse($image);

        $topLeft = imagecolorat($image, 5, 5);
        $center = imagecolorat($image, 450, 300);

        imagedestroy($image);

        $this->assertSame(0xFFFFFF, $topLeft & 0xFFFFFF);
        $this->assertSame(0xFFFFFF, $center & 0xFFFFFF);
        $this->assertNotSame(md5($jpeg), md5((string) $watermarked));
    }
}
