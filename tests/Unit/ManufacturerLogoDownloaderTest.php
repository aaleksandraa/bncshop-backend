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

    public function test_parses_a1_brand_directory_html(): void
    {
        $html = <<<'HTML'
        <a href="https://a1team.ba/brendovi/dell">
          <img src="https://a1team.ba/storage/images/95238e14-909f-4801-aa0f-a096f4c068a6.webp" alt="Dell">
        </a>
        <a href="/brendovi/tp-link-2"><img src="/storage/images/096b159f-0874-433e-af17-cee136a11e34.webp"></a>
        HTML;

        $catalog = app(ManufacturerLogoDownloader::class)
            ->parseBrandLogoCatalog($html, 'https://a1team.ba');

        $this->assertSame(
            'https://a1team.ba/storage/images/95238e14-909f-4801-aa0f-a096f4c068a6.webp',
            $catalog['dell'] ?? null,
        );
        $this->assertSame(
            'https://a1team.ba/storage/images/096b159f-0874-433e-af17-cee136a11e34.webp',
            $catalog['tplink'] ?? null,
        );
    }

    public function test_resolves_and_downloads_from_a1_catalog(): void
    {
        Storage::fake('public');
        config(['bnc.a1_api_base_url' => 'https://a1team.ba']);

        Http::fake([
            'https://a1team.ba/brendovi' => Http::response(
                '<a href="/brendovi/hp"><img src="/storage/images/hp-logo.webp" alt="HP"></a>',
                200,
            ),
            'https://a1team.ba/storage/images/hp-logo.webp' => Http::response('webp-bytes', 200, [
                'Content-Type' => 'image/webp',
            ]),
            'https://a1team.ba/brendovi/*' => Http::response('not found', 404),
        ]);

        $manufacturer = Manufacturer::query()->create([
            'external_manufacturer_id' => (string) fake()->uuid(),
            'name' => 'HP',
            'slug' => 'hp',
        ]);

        $result = app(ManufacturerLogoDownloader::class)->downloadMissing();

        $this->assertSame(1, $result['resolved']);
        $this->assertSame(1, $result['downloaded']);

        $manufacturer->refresh();
        $this->assertSame('https://a1team.ba/storage/images/hp-logo.webp', $manufacturer->logo_url);
        $this->assertNotNull($manufacturer->logo_path);
        Storage::disk('public')->assertExists($manufacturer->logo_path);
    }
}
