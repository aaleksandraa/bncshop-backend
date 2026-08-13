<?php

namespace App\Services\Integrations;

use App\Models\SystemSetting;

class PartnerExportSettings
{
    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = SystemSetting::query()->where('key', 'partner_export')->value('value');

        return array_merge($this->defaults(), is_array($stored) ? $stored : []);
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->all()['enabled'] ?? false);
    }

    public function legacyEndpointUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/api/v1/partner/products';
    }

    public function requireHttps(): bool
    {
        return (bool) ($this->all()['require_https'] ?? $this->defaults()['require_https']);
    }

    public function requiresIpAllowlist(): bool
    {
        return (bool) ($this->all()['require_ip_allowlist'] ?? $this->defaults()['require_ip_allowlist']);
    }

    public function maxFailedAuthPerMinute(): int
    {
        return max(1, min(60, (int) ($this->all()['max_failed_auth_per_minute'] ?? 10)));
    }

    public function shouldLogAccess(): bool
    {
        return (bool) ($this->all()['log_access'] ?? true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        $current = $this->all();

        SystemSetting::query()->updateOrCreate(
            ['key' => 'partner_export'],
            [
                'value' => array_merge($current, $data),
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
            'require_https' => ! app()->environment('local', 'testing'),
            'require_ip_allowlist' => ! app()->environment('local', 'testing'),
            'max_failed_auth_per_minute' => 10,
            'log_access' => true,
        ];
    }
}
