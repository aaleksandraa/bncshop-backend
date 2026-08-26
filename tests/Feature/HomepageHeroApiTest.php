<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageHeroApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_hero_endpoint_returns_defaults_when_unset(): void
    {
        $this->getJson('/api/v1/homepage/hero')
            ->assertOk()
            ->assertJsonPath('data.welcome_enabled_desktop', true)
            ->assertJsonPath('data.welcome_enabled_mobile', true)
            ->assertJsonPath('data.banners_enabled', true)
            ->assertJsonPath('data.banners', []);
    }

    public function test_hero_endpoint_returns_saved_banners(): void
    {
        SystemSetting::query()->create([
            'key' => 'homepage_hero',
            'group' => 'homepage',
            'value' => [
                'welcome_enabled' => false,
                'banners' => [
                    [
                        'image_path' => 'homepage/banners/promo.webp',
                        'url' => '/kategorija/laptopi',
                        'alt' => 'Laptopi na akciji',
                    ],
                ],
            ],
        ]);

        $response = $this->getJson('/api/v1/homepage/hero')
            ->assertOk()
            ->assertJsonPath('data.welcome_enabled_desktop', false)
            ->assertJsonPath('data.welcome_enabled_mobile', false)
            ->assertJsonPath('data.banners_enabled', true)
            ->assertJsonPath('data.banners.0.url', '/kategorija/laptopi')
            ->assertJsonPath('data.banners.0.alt', 'Laptopi na akciji');

        $this->assertStringContainsString(
            'homepage/banners/promo.webp',
            (string) $response->json('data.banners.0.image_url'),
        );
    }

    public function test_hero_endpoint_returns_banners_enabled_flag(): void
    {
        SystemSetting::query()->create([
            'key' => 'homepage_hero',
            'group' => 'homepage',
            'value' => [
                'welcome_enabled_desktop' => true,
                'welcome_enabled_mobile' => true,
                'banners_enabled' => false,
                'banners' => [
                    [
                        'image_path' => 'homepage/banners/promo.webp',
                        'url' => '/kategorija/laptopi',
                        'alt' => 'Laptopi na akciji',
                    ],
                ],
            ],
        ]);

        $this->getJson('/api/v1/homepage/hero')
            ->assertOk()
            ->assertJsonPath('data.banners_enabled', false)
            ->assertJsonPath('data.banners.0.url', '/kategorija/laptopi');
    }
}
