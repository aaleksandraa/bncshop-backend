<?php

namespace App\Services\Integrations;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
        $settings = $this->all();

        return (bool) ($settings['enabled'] ?? false)
            && filled($settings['api_key_hash'] ?? null);
    }

    public function hasApiKey(): bool
    {
        return filled($this->all()['api_key_hash'] ?? null);
    }

    public function apiKeyHint(): ?string
    {
        $hint = $this->all()['api_key_hint'] ?? null;

        return filled($hint) ? (string) $hint : null;
    }

    public function endpointUrl(): string
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

    /**
     * @return array<int, string>
     */
    public function allowedIps(): array
    {
        $ips = $this->all()['allowed_ips'] ?? [];

        if (! is_array($ips)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $ip): string => trim((string) $ip),
            $ips,
        )));
    }

    public function rateLimitPerMinute(): int
    {
        return max(1, min(300, (int) ($this->all()['rate_limit_per_minute'] ?? 60)));
    }

    public function maxFailedAuthPerMinute(): int
    {
        return max(1, min(60, (int) ($this->all()['max_failed_auth_per_minute'] ?? 10)));
    }

    public function shouldLogAccess(): bool
    {
        return (bool) ($this->all()['log_access'] ?? true);
    }

    public function lastUsedAt(): ?string
    {
        $value = $this->all()['last_used_at'] ?? null;

        return filled($value) ? (string) $value : null;
    }

    public function lastUsedIp(): ?string
    {
        $value = $this->all()['last_used_ip'] ?? null;

        return filled($value) ? (string) $value : null;
    }

    public function verifyApiKey(string $plain): bool
    {
        $hash = $this->all()['api_key_hash'] ?? null;

        return filled($hash) && Hash::check($plain, (string) $hash);
    }

    public function rotateApiKey(): string
    {
        $plain = 'bncpe_'.Str::random(40);

        $this->save([
            'api_key_hash' => Hash::make($plain),
            'api_key_hint' => substr($plain, -4),
            'api_key_created_at' => now()->toIso8601String(),
        ]);

        return $plain;
    }

    public function recordSuccessfulUse(string $ip): void
    {
        $this->save([
            'last_used_at' => now()->toIso8601String(),
            'last_used_ip' => $ip,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        $current = $this->all();

        if (! array_key_exists('api_key_hash', $data)) {
            unset($data['api_key_hash'], $data['api_key_hint']);
        }

        if (array_key_exists('allowed_ips_text', $data)) {
            $data['allowed_ips'] = $this->parseAllowedIps((string) $data['allowed_ips_text']);
            unset($data['allowed_ips_text']);
        }

        SystemSetting::query()->updateOrCreate(
            ['key' => 'partner_export'],
            [
                'value' => array_merge($current, $data),
                'group' => 'integrations',
            ],
        );
    }

    /**
     * @return array<int, string>
     */
    public function invalidAllowedIps(string $value): array
    {
        $lines = preg_split('/\R/', $value) ?: [];
        $invalid = [];

        foreach ($lines as $line) {
            $entry = trim($line);

            if ($entry === '') {
                continue;
            }

            if (! $this->isValidIpOrCidr($entry)) {
                $invalid[] = $entry;
            }
        }

        return $invalid;
    }

    /**
     * @return array<int, string>
     */
    public function parseAllowedIps(string $value): array
    {
        $lines = preg_split('/\R/', $value) ?: [];

        return array_values(array_unique(array_filter(array_map(
            static fn (string $line): string => trim($line),
            $lines,
        ))));
    }

    private function isValidIpOrCidr(string $value): bool
    {
        if (str_contains($value, '/')) {
            [$ip, $mask] = explode('/', $value, 2);
            $maxMask = str_contains($ip, ':') ? 128 : 32;

            return filter_var($ip, FILTER_VALIDATE_IP) !== false
                && ctype_digit($mask)
                && (int) $mask >= 0
                && (int) $mask <= $maxMask;
        }

        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'enabled' => false,
            'partner_name' => '',
            'api_key_hash' => null,
            'api_key_hint' => null,
            'api_key_created_at' => null,
            'require_https' => ! app()->environment('local', 'testing'),
            'require_ip_allowlist' => ! app()->environment('local', 'testing'),
            'allowed_ips' => [],
            'rate_limit_per_minute' => 60,
            'max_failed_auth_per_minute' => 10,
            'log_access' => true,
            'last_used_at' => null,
            'last_used_ip' => null,
        ];
    }
}
