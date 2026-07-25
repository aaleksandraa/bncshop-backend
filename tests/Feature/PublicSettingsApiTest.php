<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_settings_excludes_internal_sitemap_cache(): void
    {
        SystemSetting::query()->create([
            'key' => 'store_name',
            'value' => 'BNC Shop',
            'group' => 'shop',
        ]);

        SystemSetting::query()->create([
            'key' => 'sitemap_cache',
            'value' => [
                'generated_at' => now()->toIso8601String(),
                'entries' => array_fill(0, 1000, [
                    'loc' => 'https://bncshop.ba/proizvod/test',
                    'lastmod' => now()->toAtomString(),
                ]),
            ],
            'group' => 'seo',
        ]);

        $response = $this->getJson('/api/v1/settings/public');

        $response->assertOk();
        $response->assertJsonPath('data.store_name', 'BNC Shop');
        $response->assertJsonMissing(['sitemap_cache']);
    }

    public function test_layout_shell_excludes_internal_sitemap_cache(): void
    {
        foreach (['header', 'footer'] as $slug) {
            Menu::query()->create([
                'name' => ucfirst($slug),
                'slug' => $slug,
                'is_active' => true,
            ]);
        }

        SystemSetting::query()->create([
            'key' => 'store_name',
            'value' => 'BNC Shop',
            'group' => 'shop',
        ]);

        SystemSetting::query()->create([
            'key' => 'sitemap_cache',
            'value' => [
                'generated_at' => now()->toIso8601String(),
                'entries' => array_fill(0, 500, ['loc' => 'https://bncshop.ba/test']),
            ],
            'group' => 'seo',
        ]);

        $response = $this->getJson('/api/v1/layout/shell');

        $response->assertOk();
        $response->assertJsonPath('data.settings.store_name', 'BNC Shop');
        $response->assertJsonMissing(['sitemap_cache']);
    }
}
