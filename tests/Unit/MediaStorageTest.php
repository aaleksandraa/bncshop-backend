<?php

namespace Tests\Unit;

use App\Services\Media\ImageOptimizer;
use App\Services\Media\MediaStorage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStorageTest extends TestCase
{
    public function test_store_optimized_writes_master_and_variants_to_public_disk_when_r2_not_configured(): void
    {
        if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
            $this->markTestSkipped('GD or Imagick extension required.');
        }

        Storage::fake('public');

        config([
            'filesystems.disks.r2.key' => null,
            'filesystems.disks.r2.secret' => null,
            'filesystems.disks.r2.endpoint' => null,
        ]);

        $png = $this->createTestPng(1800, 900);
        $storage = new MediaStorage(new ImageOptimizer);

        $stored = $storage->storeOptimized('products/demo/sample.jpg', $png);

        $this->assertSame('public', $stored->disk);
        Storage::disk('public')->assertExists($stored->key);
        Storage::disk('public')->assertExists('products/demo/sample_320.webp');
        Storage::disk('public')->assertExists('products/demo/sample_640.webp');
        Storage::disk('public')->assertExists('products/demo/sample_1280.webp');
    }

    public function test_delete_removes_master_and_variants(): void
    {
        Storage::fake('public');

        config([
            'filesystems.disks.r2.key' => null,
            'filesystems.disks.r2.secret' => null,
            'filesystems.disks.r2.endpoint' => null,
        ]);

        Storage::disk('public')->put('products/demo/sample.webp', 'master');
        Storage::disk('public')->put('products/demo/sample_320.webp', 'v320');
        Storage::disk('public')->put('products/demo/sample_640.webp', 'v640');

        $storage = new MediaStorage(new ImageOptimizer);
        $storage->delete('products/demo/sample.webp');

        Storage::disk('public')->assertMissing('products/demo/sample.webp');
        Storage::disk('public')->assertMissing('products/demo/sample_320.webp');
        Storage::disk('public')->assertMissing('products/demo/sample_640.webp');
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
