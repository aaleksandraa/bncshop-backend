<?php

namespace App\Services\Ananas;

use App\Models\ApiSource;
use App\Models\SystemSetting;

class AnanasSyncSettings
{
    public const TARGET_SYSTEM_CODE = 'ananas';

    public const ENV_STAGE = 'stage';

    public const ENV_PRODUCTION = 'production';

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'enabled' => false,
            'allow_catalog_writes' => false,
            'environment' => config('bnc.ananas_env', self::ENV_STAGE),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = SystemSetting::query()->where('key', 'ananas_export')->value('value');

        return array_merge($this->defaults(), is_array($stored) ? $stored : []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        $merged = array_merge($this->all(), $data);

        SystemSetting::query()->updateOrCreate(
            ['key' => 'ananas_export'],
            ['value' => $merged],
        );
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->all()['enabled'] ?? false);
    }

    public function allowCatalogWrites(): bool
    {
        if ((bool) config('bnc.ananas_allow_catalog_writes', false)) {
            return true;
        }

        return (bool) ($this->all()['allow_catalog_writes'] ?? false);
    }

    public function environment(): string
    {
        $env = strtolower(trim((string) ($this->all()['environment'] ?? config('bnc.ananas_env', self::ENV_STAGE))));

        return $env === self::ENV_PRODUCTION ? self::ENV_PRODUCTION : self::ENV_STAGE;
    }

    public function isStage(): bool
    {
        return $this->environment() === self::ENV_STAGE;
    }

    public function apiSource(): ?ApiSource
    {
        return ApiSource::query()
            ->where('target_system_code', self::TARGET_SYSTEM_CODE)
            ->first();
    }

    /**
     * @return array{client_id: string, client_secret: string}
     */
    public function credentials(): array
    {
        $source = $this->apiSource();

        return [
            'client_id' => (string) ($source?->username ?: config('bnc.ananas_client_id', '')),
            'client_secret' => (string) ($source?->password ?: config('bnc.ananas_client_secret', '')),
        ];
    }

    public function hasCredentials(): bool
    {
        $credentials = $this->credentials();

        return $credentials['client_id'] !== '' && $credentials['client_secret'] !== '';
    }

    public function iamBaseUrl(): string
    {
        $key = $this->isStage()
            ? 'bnc.ananas_stage_iam_base_url'
            : 'bnc.ananas_production_iam_base_url';

        return rtrim((string) config($key), '/');
    }

    public function tokenEndpointUrl(): string
    {
        $key = $this->isStage()
            ? 'bnc.ananas_stage_token_url'
            : 'bnc.ananas_production_token_url';

        return rtrim((string) config($key), '/');
    }

    public function productBaseUrl(): string
    {
        $key = $this->isStage()
            ? 'bnc.ananas_stage_product_base_url'
            : 'bnc.ananas_production_product_base_url';

        return rtrim((string) config($key), '/');
    }

    public function svcBaseUrl(): string
    {
        $key = $this->isStage()
            ? 'bnc.ananas_stage_svc_base_url'
            : 'bnc.ananas_production_svc_base_url';

        return rtrim((string) config($key), '/');
    }

    /**
     * @param  array{client_id?: string, client_secret?: string|null}  $data
     */
    public function saveCredentials(array $data): ApiSource
    {
        $source = $this->resolveSource();
        $update = [];

        if (array_key_exists('client_id', $data)) {
            $update['username'] = $data['client_id'];
        }

        if (array_key_exists('client_secret', $data) && filled($data['client_secret'])) {
            $update['password'] = $data['client_secret'];
        }

        if ($update !== []) {
            $source->update($update);
        }

        return $source->fresh() ?? $source;
    }

    public function resolveSource(): ApiSource
    {
        $existing = $this->apiSource();
        $envClientId = (string) config('bnc.ananas_client_id');
        $envClientSecret = (string) config('bnc.ananas_client_secret');

        return ApiSource::query()->updateOrCreate(
            ['target_system_code' => self::TARGET_SYSTEM_CODE],
            [
                'name' => 'Ananas Merchant API',
                'base_url' => $this->productBaseUrl(),
                'username' => $existing?->username ?: ($envClientId !== '' ? $envClientId : null),
                'password' => $existing?->password ?: ($envClientSecret !== '' ? $envClientSecret : null),
                'is_active' => true,
                'auto_sync_enabled' => false,
                'connection_status' => $existing?->connection_status ?? 'unknown',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $source = $this->apiSource();

        return [
            'source_exists' => $source !== null,
            'source_id' => $source?->id,
            'connection_status' => $source?->connection_status,
            'environment' => $this->environment(),
            'allow_catalog_writes' => $this->allowCatalogWrites(),
            'has_credentials' => $this->hasCredentials(),
            'credentials_source' => $this->apiSource()?->username ? 'admin' : (config('bnc.ananas_client_id') ? 'env' : 'none'),
            'iam_base_url' => $this->iamBaseUrl(),
            'token_endpoint_url' => $this->tokenEndpointUrl(),
            'product_base_url' => $this->productBaseUrl(),
            'svc_base_url' => $this->svcBaseUrl(),
        ];
    }
}
