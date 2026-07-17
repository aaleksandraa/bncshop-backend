<?php

namespace App\Services\Marketing;

use App\Models\SystemSetting;

class BrevoSettings
{
    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = SystemSetting::query()->where('key', 'brevo')->value('value');

        return array_merge($this->defaults(), is_array($stored) ? $stored : []);
    }

    public function isEnabled(): bool
    {
        $settings = $this->all();

        return (bool) ($settings['enabled'] ?? false)
            && filled($settings['api_key'] ?? null)
            && filled($settings['sender_email'] ?? null);
    }

    public function apiKey(): ?string
    {
        $key = $this->all()['api_key'] ?? null;

        return filled($key) ? (string) $key : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'brevo'],
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
            'enabled' => false,
            'api_key' => '',
            'sender_email' => '',
            'sender_name' => 'BNC Shop',
            'default_list_id' => null,
            'sync_on_order' => true,
            'sync_registered' => true,
        ];
    }
}
