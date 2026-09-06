<?php

namespace Tests\Unit;

use App\Models\SystemSetting;
use App\Services\Integrations\TrackingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_save_ga_measurement_id(): void
    {
        $settings = app(TrackingSettings::class);

        $settings->save(['ga_measurement_id' => 'G-ABC123XYZ']);

        $stored = SystemSetting::query()->where('key', 'tracking')->first();

        $this->assertNotNull($stored);
        $this->assertSame('integrations', $stored->group);
        $this->assertSame('G-ABC123XYZ', $stored->value['ga_measurement_id']);
        $this->assertSame('G-ABC123XYZ', $settings->publicConfig()['ga_measurement_id']);
    }

    public function test_trims_tracking_ids_on_save(): void
    {
        $settings = app(TrackingSettings::class);

        $settings->save([
            'ga_measurement_id' => '  G-TRIM123  ',
            'fb_pixel_id' => ' 1234567890 ',
        ]);

        $stored = SystemSetting::query()->where('key', 'tracking')->first();

        $this->assertNotNull($stored);
        $this->assertSame('G-TRIM123', $stored->value['ga_measurement_id']);
        $this->assertSame('1234567890', $stored->value['fb_pixel_id']);
    }

    public function test_public_config_omits_empty_tracking_ids(): void
    {
        $settings = app(TrackingSettings::class);

        $this->assertNull($settings->publicConfig()['ga_measurement_id']);
        $this->assertNull($settings->publicConfig()['fb_pixel_id']);
        $this->assertArrayNotHasKey('ga_api_secret', $settings->publicConfig());
    }

    public function test_public_config_falls_back_to_dataset_id_for_pixel(): void
    {
        $settings = app(TrackingSettings::class);
        $settings->save([
            'fb_dataset_id' => '786294308773690',
            'fb_access_token' => 'secret-token',
        ]);

        $this->assertSame('786294308773690', $settings->publicConfig()['fb_pixel_id']);
    }

    public function test_save_auto_fills_pixel_id_from_dataset_id(): void
    {
        $settings = app(TrackingSettings::class);
        $settings->save([
            'fb_dataset_id' => '786294308773690',
        ]);

        $this->assertSame('786294308773690', $settings->all()['fb_pixel_id']);
    }
}
