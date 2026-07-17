<?php

namespace App\Services\Integrations;

use App\Models\SystemSetting;

class TrackingSettings
{
    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = SystemSetting::query()->where('key', 'tracking')->value('value');

        return array_merge($this->defaults(), is_array($stored) ? $stored : []);
    }

    /**
     * @return array<string, mixed>
     */
    public function publicConfig(): array
    {
        $settings = $this->all();

        return [
            'consent_enabled' => (bool) ($settings['consent_enabled'] ?? true),
            'consent_title' => (string) ($settings['consent_title'] ?? ''),
            'consent_message' => (string) ($settings['consent_message'] ?? ''),
            'privacy_page_slug' => (string) ($settings['privacy_page_slug'] ?? 'privatnost'),
            'ga_measurement_id' => filled($settings['ga_measurement_id'] ?? null)
                ? (string) $settings['ga_measurement_id']
                : null,
            'fb_pixel_id' => filled($settings['fb_pixel_id'] ?? null)
                ? (string) $settings['fb_pixel_id']
                : null,
            'load_scripts_only_with_consent' => (bool) ($settings['load_scripts_only_with_consent'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'tracking'],
            [
                'value' => array_merge($this->defaults(), $data),
                'group' => 'integrations',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'consent_enabled' => true,
            'consent_title' => 'Kolačići i privatnost',
            'consent_message' => 'Koristimo kolačiće kako bismo poboljšali vaše iskustvo, analizirali promet i prikazali relevantne ponude. Po defaultu su uključeni analitički i marketing kolačići; možete ih odbiti ili prilagoditi u postavkama kolačića.',
            'privacy_page_slug' => 'privatnost',
            'ga_measurement_id' => '',
            'fb_pixel_id' => '',
            'load_scripts_only_with_consent' => true,
        ];
    }
}
