<?php

namespace Tests\Unit;

use App\Models\ApiSource;
use App\Services\Sync\A1SyncSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class A1SyncSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_updates_auto_sync_and_preset_interval(): void
    {
        config(['bnc.a1_api_target_system_code' => 'bnc-shop']);

        $source = ApiSource::query()->create([
            'name' => 'A1 Technoshop',
            'target_system_code' => 'bnc-shop',
            'base_url' => 'https://a1team.ba',
            'username' => 'bnc',
            'password' => 'secret',
            'sync_interval_minutes' => 60,
            'auto_sync_enabled' => true,
            'is_active' => true,
        ]);

        $settings = app(A1SyncSettings::class);

        $settings->save([
            'auto_sync_enabled' => false,
            'interval_preset' => '120',
            'sync_interval_minutes' => 60,
        ]);

        $source->refresh();

        $this->assertFalse($source->auto_sync_enabled);
        $this->assertSame(120, $source->sync_interval_minutes);
    }

    public function test_save_custom_interval(): void
    {
        config(['bnc.a1_api_target_system_code' => 'bnc-shop']);

        $source = ApiSource::query()->create([
            'name' => 'A1 Technoshop',
            'target_system_code' => 'bnc-shop',
            'base_url' => 'https://a1team.ba',
            'username' => 'bnc',
            'password' => 'secret',
            'sync_interval_minutes' => 60,
            'auto_sync_enabled' => true,
            'is_active' => true,
        ]);

        app(A1SyncSettings::class)->save([
            'auto_sync_enabled' => true,
            'interval_preset' => 'custom',
            'sync_interval_minutes' => 90,
        ]);

        $source->refresh();

        $this->assertSame(90, $source->sync_interval_minutes);
    }

    public function test_resolve_preset(): void
    {
        $settings = app(A1SyncSettings::class);

        $this->assertSame('60', $settings->resolvePreset(60));
        $this->assertSame('custom', $settings->resolvePreset(90));
    }
}
