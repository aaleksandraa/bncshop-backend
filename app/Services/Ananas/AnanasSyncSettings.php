<?php

namespace App\Services\Ananas;

use App\Models\AnanasSyncRun;
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
            'vat_rate' => 17,
            'discount_currency' => AnanasDiscountPolicy::CURRENCY_BAM,
            'daily_jobs_master_enabled' => false,
            'daily_job_auto' => [
                AnanasSyncRun::JOB_CATALOG => true,
                AnanasSyncRun::JOB_PRICE_STOCK => true,
                AnanasSyncRun::JOB_DISCOUNTS => true,
                AnanasSyncRun::JOB_IMPORT => true,
                AnanasSyncRun::JOB_INTRADAY_STOCK => true,
            ],
            'import_daily_limit' => 2000,
            'price_lookup_verified' => [
                self::ENV_STAGE => false,
                self::ENV_PRODUCTION => false,
            ],
            'stock_only_verified' => [
                self::ENV_STAGE => false,
                self::ENV_PRODUCTION => false,
            ],
            'price_apply_mode' => [
                self::ENV_STAGE => null,
                self::ENV_PRODUCTION => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge($this->defaults(), $this->storedSettings());
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

    public function discountCurrency(): string
    {
        $stored = strtoupper(trim((string) ($this->storedSettings()['discount_currency'] ?? '')));

        return app(AnanasDiscountPolicy::class)->normalizeCurrency(
            $stored !== '' ? $stored : AnanasDiscountPolicy::CURRENCY_BAM,
        );
    }

    public function environment(): string
    {
        $configured = strtolower(trim((string) config('bnc.ananas_env', '')));

        if ($configured === self::ENV_PRODUCTION) {
            return self::ENV_PRODUCTION;
        }

        if ($this->environmentLockedByConfig() && $configured === self::ENV_STAGE) {
            return self::ENV_STAGE;
        }

        $stored = strtolower(trim((string) ($this->storedSettings()['environment'] ?? '')));

        return $stored === self::ENV_PRODUCTION ? self::ENV_PRODUCTION : self::ENV_STAGE;
    }

    public function environmentLockedByConfig(): bool
    {
        return (bool) config('bnc.ananas_env_locked', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function storedSettings(): array
    {
        try {
            $stored = SystemSetting::query()->where('key', 'ananas_export')->value('value');
        } catch (\Throwable) {
            return [];
        }

        return is_array($stored) ? $stored : [];
    }

    public function isStage(): bool
    {
        return $this->environment() === self::ENV_STAGE;
    }

    public function isProduction(): bool
    {
        return $this->environment() === self::ENV_PRODUCTION;
    }

    public function dailyJobsMasterEnabled(): bool
    {
        return (bool) ($this->all()['daily_jobs_master_enabled'] ?? false);
    }

    public function isDailyJobAutoEnabled(string $jobKey): bool
    {
        $jobs = $this->all()['daily_job_auto'] ?? [];

        if (! is_array($jobs)) {
            return true;
        }

        return (bool) ($jobs[$jobKey] ?? true);
    }

    public function importDailyLimit(): int
    {
        return max(1, min(5000, (int) ($this->all()['import_daily_limit'] ?? 2000)));
    }

    public function envProbeFlag(string $key): bool
    {
        $env = $this->environment();
        $all = $this->all();
        $map = $all[$key] ?? [];

        if (! is_array($map)) {
            return false;
        }

        return (bool) ($map[$env] ?? false);
    }

    public function isPriceLookupVerifiedForCurrentEnv(): bool
    {
        return $this->envProbeFlag('price_lookup_verified');
    }

    public function isStockOnlyVerifiedForCurrentEnv(): bool
    {
        return $this->envProbeFlag('stock_only_verified');
    }

    /**
     * @param  'basePrice'|'newBasePrice'|null  $mode
     */
    public function setPriceApplyModeForCurrentEnv(?string $mode): void
    {
        $all = $this->all();
        $map = is_array($all['price_apply_mode'] ?? null) ? $all['price_apply_mode'] : [];
        $map[$this->environment()] = $mode;
        $all['price_apply_mode'] = $map;
        $this->save($all);
    }

    public function priceApplyModeForCurrentEnv(): ?string
    {
        $all = $this->all();
        $map = $all['price_apply_mode'] ?? [];

        if (! is_array($map)) {
            return null;
        }

        $mode = $map[$this->environment()] ?? null;

        return is_string($mode) ? $mode : null;
    }

    public function setEnvProbe(string $key, bool $value): void
    {
        $all = $this->all();
        $map = is_array($all[$key] ?? null) ? $all[$key] : [];
        $map[$this->environment()] = $value;
        $all[$key] = $map;
        $this->save($all);
    }

    public function cronGateReason(string $jobKey, bool $manual = false): ?string
    {
        if ($manual) {
            return null;
        }

        if (! $this->hasCredentials()) {
            return 'Ananas credentials missing.';
        }

        if (! $this->isEnabled()) {
            return 'Ananas export disabled.';
        }

        if (! $this->dailyJobsMasterEnabled()) {
            return 'Daily jobs master switch is off.';
        }

        if (! $this->isDailyJobAutoEnabled($jobKey)) {
            return 'Automatic run disabled for this job.';
        }

        if (! $this->allowCatalogWrites()) {
            return 'Catalog writes disabled.';
        }

        if ($jobKey === AnanasSyncRun::JOB_INTRADAY_STOCK && ! $this->isStockOnlyVerifiedForCurrentEnv()) {
            return 'Stock-only probe not verified for this environment.';
        }

        if ($jobKey === AnanasSyncRun::JOB_DISCOUNTS && ! $this->isPriceLookupVerifiedForCurrentEnv()) {
            return 'Price lookup probe not verified for this environment.';
        }

        if ($this->isProduction() && $this->environment() !== self::ENV_PRODUCTION) {
            return 'Production writes require ANANAS_ENV=production.';
        }

        return null;
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
        $envClientId = (string) config('bnc.ananas_client_id', '');
        $envClientSecret = (string) config('bnc.ananas_client_secret', '');

        if ($envClientId !== '' && $envClientSecret !== '') {
            return [
                'client_id' => $envClientId,
                'client_secret' => $envClientSecret,
            ];
        }

        try {
            $source = $this->apiSource();
        } catch (\Throwable) {
            $source = null;
        }

        return [
            'client_id' => (string) ($source?->username ?: $envClientId),
            'client_secret' => (string) ($source?->password ?: $envClientSecret),
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
            'credentials_source' => $this->credentialsSourceLabel(),
            'iam_base_url' => $this->iamBaseUrl(),
            'token_endpoint_url' => $this->tokenEndpointUrl(),
            'product_base_url' => $this->productBaseUrl(),
            'svc_base_url' => $this->svcBaseUrl(),
        ];
    }

    private function credentialsSourceLabel(): string
    {
        if ((string) config('bnc.ananas_client_id', '') !== '' && (string) config('bnc.ananas_client_secret', '') !== '') {
            return 'env';
        }

        try {
            return $this->apiSource()?->username ? 'admin' : 'none';
        } catch (\Throwable) {
            return 'none';
        }
    }
}
