<?php

namespace Tests\Unit;

use App\Models\Manufacturer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManufacturerLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_logo_takes_priority_over_external_url(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('manufacturers/logos/hp.png', 'logo-bytes');

        $manufacturer = Manufacturer::query()->create([
            'external_manufacturer_id' => (string) fake()->uuid(),
            'name' => 'HP',
            'slug' => 'hp',
            'logo_url' => 'https://example.com/external.png',
            'logo_path' => 'manufacturers/logos/hp.png',
        ]);

        $this->assertSame('/storage/manufacturers/logos/hp.png', $manufacturer->logoUrl());
    }

    public function test_falls_back_to_external_logo_url(): void
    {
        $manufacturer = Manufacturer::query()->create([
            'external_manufacturer_id' => (string) fake()->uuid(),
            'name' => 'Dell',
            'slug' => 'dell',
            'logo_url' => 'https://example.com/dell.png',
        ]);

        $this->assertSame('https://example.com/dell.png', $manufacturer->logoUrl());
    }
}
