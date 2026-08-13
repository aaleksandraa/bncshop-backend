<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PartnerApiClient extends Model
{
    public const TYPE_BASIC = 'basic';

    public const TYPE_FULL = 'full';

    protected $fillable = [
        'name',
        'code',
        'type',
        'enabled',
        'api_key_hash',
        'api_key_hint',
        'api_key_created_at',
        'require_ip_allowlist',
        'allowed_ips',
        'rate_limit_per_minute',
        'daily_page_limit',
        'last_used_at',
        'last_used_ip',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'require_ip_allowlist' => 'boolean',
            'allowed_ips' => 'array',
            'rate_limit_per_minute' => 'integer',
            'daily_page_limit' => 'integer',
            'api_key_created_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public static function findByPlainApiKey(string $plain): ?self
    {
        if (! self::isValidApiKeyFormat($plain)) {
            return null;
        }

        /** @var self|null $match */
        $match = self::query()
            ->where('enabled', true)
            ->whereNotNull('api_key_hash')
            ->get()
            ->first(fn (self $client): bool => Hash::check($plain, (string) $client->api_key_hash));

        return $match;
    }

    public static function isValidApiKeyFormat(?string $apiKey): bool
    {
        if ($apiKey === null || $apiKey === '') {
            return false;
        }

        return (bool) preg_match('/^bncpe_[A-Za-z0-9]{40}$/', $apiKey);
    }

    public function verifyApiKey(string $plain): bool
    {
        return filled($this->api_key_hash) && Hash::check($plain, (string) $this->api_key_hash);
    }

    public function hasApiKey(): bool
    {
        return filled($this->api_key_hash);
    }

    public function rotateApiKey(): string
    {
        $plain = 'bncpe_'.Str::random(40);

        $this->forceFill([
            'api_key_hash' => Hash::make($plain),
            'api_key_hint' => substr($plain, -4),
            'api_key_created_at' => now(),
        ])->save();

        return $plain;
    }

    /**
     * @return array<int, string>
     */
    public function allowedIpList(): array
    {
        $ips = $this->allowed_ips ?? [];

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
        return max(1, min(300, (int) ($this->rate_limit_per_minute ?: 60)));
    }

    public function dailyPageLimit(): int
    {
        return max(50, min(10000, (int) ($this->daily_page_limit ?: 2000)));
    }

    public function integrationProductsUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/api/integrations/'.$this->code.'/products';
    }

    public function legacyProductsUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/api/v1/partner/products';
    }

    public function isFullExport(): bool
    {
        return $this->type === self::TYPE_FULL;
    }

    public function recordSuccessfulUse(string $ip): void
    {
        $this->forceFill([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
        ])->saveQuietly();
    }

    /**
     * @return array<int, string>
     */
    public static function invalidAllowedIps(string $value): array
    {
        $lines = preg_split('/\R/', $value) ?: [];
        $invalid = [];

        foreach ($lines as $line) {
            $entry = trim($line);

            if ($entry === '') {
                continue;
            }

            if (! self::isValidIpOrCidr($entry)) {
                $invalid[] = $entry;
            }
        }

        return $invalid;
    }

    /**
     * @return array<int, string>
     */
    public static function parseAllowedIps(string $value): array
    {
        $lines = preg_split('/\R/', $value) ?: [];

        return array_values(array_unique(array_filter(array_map(
            static fn (string $line): string => trim($line),
            $lines,
        ))));
    }

    private static function isValidIpOrCidr(string $value): bool
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
}
