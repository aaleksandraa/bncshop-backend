<?php

namespace App\Services\Integrations;

use App\Models\SystemSetting;
use App\Services\Catalog\ProductReadCache;

class TrackingSettings
{
    public function __construct(
        private readonly ProductReadCache $productReadCache,
    ) {}
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
            'fb_pixel_id' => $this->resolvePublicPixelId($settings),
            'load_scripts_only_with_consent' => (bool) ($settings['load_scripts_only_with_consent'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        if (array_key_exists('ga_measurement_id', $data)) {
            $data['ga_measurement_id'] = trim((string) $data['ga_measurement_id']);
        }

        if (array_key_exists('fb_pixel_id', $data)) {
            $data['fb_pixel_id'] = trim((string) $data['fb_pixel_id']);
        }

        if (array_key_exists('ga_api_secret', $data)) {
            $secret = trim((string) $data['ga_api_secret']);
            if ($secret === '') {
                unset($data['ga_api_secret']);
            } else {
                $data['ga_api_secret'] = $secret;
            }
        }

        foreach (['fb_access_token', 'fb_dataset_id', 'fb_test_event_code', 'fb_crm_name', 'fb_catalog_feed_token'] as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = trim((string) $data[$key]);

            if ($key === 'fb_access_token' && $value === '') {
                unset($data['fb_access_token']);

                continue;
            }

            $data[$key] = $value;
        }

        foreach (['fb_catalog_include_category_slugs', 'fb_catalog_exclude_category_slugs', 'fb_catalog_exclude_name_keywords'] as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $data[$key] = trim((string) $data[$key]);
        }

        $merged = array_merge($this->all(), $data);
        if (trim((string) ($merged['fb_pixel_id'] ?? '')) === ''
            && trim((string) ($merged['fb_dataset_id'] ?? '')) !== '') {
            $data['fb_pixel_id'] = trim((string) $merged['fb_dataset_id']);
        }

        SystemSetting::query()->updateOrCreate(
            ['key' => 'tracking'],
            [
                'value' => array_merge($this->defaults(), $this->all(), $data),
                'group' => 'integrations',
            ],
        );

        $this->productReadCache->flushLayout();
        $this->productReadCache->flushSettings();
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
            'ga_api_secret' => '',
            'fb_pixel_id' => '',
            'fb_dataset_id' => '',
            'fb_access_token' => '',
            'fb_test_event_code' => '',
            'fb_crm_name' => 'BNC Shop',
            'fb_catalog_feed_token' => '',
            'fb_catalog_include_category_slugs' => '',
            'fb_catalog_exclude_category_slugs' => '',
            'fb_catalog_exclude_name_keywords' => '',
            'load_scripts_only_with_consent' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function resolvePublicPixelId(array $settings): ?string
    {
        $pixelId = trim((string) ($settings['fb_pixel_id'] ?? ''));
        if ($pixelId !== '') {
            return $pixelId;
        }

        $datasetId = trim((string) ($settings['fb_dataset_id'] ?? ''));
        if ($datasetId !== '') {
            return $datasetId;
        }

        return null;
    }
}
