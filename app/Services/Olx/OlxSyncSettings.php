<?php

namespace App\Services\Olx;

use App\Models\ApiImportJob;
use App\Models\ApiSource;
use App\Models\SystemSetting;

class OlxSyncSettings
{
    public const TARGET_SYSTEM_CODE = 'olx';

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'enabled' => true,
            'auto_sync_enabled' => true,
            'sync_times' => config('bnc.olx_sync_times', ['06:00', '18:00']),
            'description_footer' => '',
            'country_id' => config('bnc.olx_default_country_id', 49),
            'city_id' => config('bnc.olx_default_city_id', 133),
            'location_lat' => config('bnc.olx_default_location_lat'),
            'location_lon' => config('bnc.olx_default_location_lon'),
            'listing_type' => 'sell',
            'shipping' => 'no_shipping',
            'reconcile_weekly' => true,
            'batch_size' => 20,
            'device_name' => config('bnc.olx_api_device_name', 'bncshopweb_integration'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = SystemSetting::query()->where('key', 'olx_export')->value('value');

        return array_merge($this->defaults(), is_array($stored) ? $stored : []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        $merged = array_merge($this->all(), $data);

        SystemSetting::query()->updateOrCreate(
            ['key' => 'olx_export'],
            ['value' => $merged],
        );
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->all()['enabled'] ?? false);
    }

    public function autoSyncEnabled(): bool
    {
        return (bool) ($this->all()['auto_sync_enabled'] ?? false);
    }

    public function descriptionFooter(): string
    {
        return trim((string) ($this->all()['description_footer'] ?? ''));
    }

    public function apiSource(): ?ApiSource
    {
        return ApiSource::query()
            ->where('target_system_code', self::TARGET_SYSTEM_CODE)
            ->first();
    }

    /**
     * @return array{base_url: string, username: string, password: string, device_name: string}
     */
    public function credentials(): array
    {
        $source = $this->apiSource();

        return [
            'base_url' => (string) ($source?->base_url ?: config('bnc.olx_api_base_url', 'https://api.olx.ba')),
            'username' => (string) ($source?->username ?: config('bnc.olx_api_username', '')),
            'password' => (string) ($source?->password ?: config('bnc.olx_api_password', '')),
            'device_name' => (string) ($this->all()['device_name'] ?? config('bnc.olx_api_device_name', 'bncshopweb_integration')),
        ];
    }

    public function hasCredentials(): bool
    {
        $credentials = $this->credentials();

        return $credentials['username'] !== '' && $credentials['password'] !== '';
    }

    /**
     * @param  array{base_url?: string, username?: string, password?: string|null}  $data
     */
    public function saveCredentials(array $data): ApiSource
    {
        $source = $this->resolveSource();
        $update = [];

        if (array_key_exists('base_url', $data) && filled($data['base_url'])) {
            $update['base_url'] = $data['base_url'];
        }

        if (array_key_exists('username', $data)) {
            $update['username'] = $data['username'];
        }

        if (array_key_exists('password', $data) && filled($data['password'])) {
            $update['password'] = $data['password'];
        }

        if ($update !== []) {
            $source->update($update);
        }

        return $source->fresh() ?? $source;
    }

    public function resolveSource(): ApiSource
    {
        $existing = $this->apiSource();
        $envUsername = (string) config('bnc.olx_api_username');
        $envPassword = (string) config('bnc.olx_api_password');
        $baseUrl = (string) config('bnc.olx_api_base_url');

        return ApiSource::query()->updateOrCreate(
            ['target_system_code' => self::TARGET_SYSTEM_CODE],
            [
                'name' => 'OLX / PIK export',
                'base_url' => $existing?->base_url ?: ($baseUrl !== '' ? $baseUrl : 'https://api.olx.ba'),
                'username' => $existing?->username ?: ($envUsername !== '' ? $envUsername : null),
                'password' => $existing?->password ?: ($envPassword !== '' ? $envPassword : null),
                'is_active' => true,
                'auto_sync_enabled' => $this->autoSyncEnabled(),
                'connection_status' => $existing?->connection_status ?? 'unknown',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $source = ApiSource::query()->where('target_system_code', 'olx')->first();
        $latestJob = $source
            ? ApiImportJob::query()->where('api_source_id', $source->id)->latest()->first()
            : null;

        return [
            'source_exists' => $source !== null,
            'source_id' => $source?->id,
            'connection_status' => $source?->connection_status,
            'last_successful_sync_at' => $source?->last_successful_sync_at,
            'last_error' => $source?->last_error,
            'auto_sync_enabled' => $this->autoSyncEnabled(),
            'sync_times' => $this->all()['sync_times'] ?? [],
            'has_credentials' => $this->hasCredentials(),
            'credentials_source' => $this->apiSource()?->username ? 'admin' : (config('bnc.olx_api_username') ? 'env' : 'none'),
            'shop_username' => $this->credentials()['username'],
            'latest_job' => $latestJob ? [
                'id' => $latestJob->id,
                'type' => $latestJob->type,
                'status' => $latestJob->status,
                'started_at' => $latestJob->started_at,
                'completed_at' => $latestJob->completed_at,
                'stats' => $latestJob->stats,
            ] : null,
        ];
    }
}
