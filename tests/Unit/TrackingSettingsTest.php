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

    public function test_public_config_omits_empty_tracking_ids(): void
    {
        $settings = app(TrackingSettings::class);

        $this->assertNull($settings->publicConfig()['ga_measurement_id']);
        $this->assertNull($settings->publicConfig()['fb_pixel_id']);
    }
}
