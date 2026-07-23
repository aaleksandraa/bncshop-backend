<?php

namespace Tests\Unit;

use App\Models\Manufacturer;
use App\Services\Catalog\ManufacturerLogoDownloader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManufacturerLogoDownloaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_downloads_remote_logo_into_logo_path(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://cdn.example.com/dell.png' => Http::response('png-bytes', 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $manufacturer = Manufacturer::query()->create([
            'external_manufacturer_id' => (string) fake()->uuid(),
            'name' => 'Dell',
            'slug' => 'dell',
            'logo_url' => 'https://cdn.example.com/dell.png',
        ]);

        $result = app(ManufacturerLogoDownloader::class)->downloadOne($manufacturer);

        $this->assertTrue($result);
        $manufacturer->refresh();
        $this->assertNotNull($manufacturer->logo_path);
        Storage::disk('public')->assertExists($manufacturer->logo_path);
    }

    public function test_skips_when_local_logo_already_exists(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('manufacturers/logos/dell-1.png', 'existing');

        $manufacturer = Manufacturer::query()->create([
            'external_manufacturer_id' => (string) fake()->uuid(),
            'name' => 'Dell',
            'slug' => 'dell',
            'logo_url' => 'https://cdn.example.com/dell.png',
            'logo_path' => 'manufacturers/logos/dell-1.png',
        ]);

        Http::fake();

        $result = app(ManufacturerLogoDownloader::class)->downloadOne($manufacturer);

        $this->assertNull($result);
        Http::assertNothingSent();
    }
}
