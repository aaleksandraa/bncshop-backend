<?php

namespace App\Services\Loyalty;

use App\Models\SystemSetting;

class LoyaltySettings
{
    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = SystemSetting::query()->where('key', 'loyalty')->value('value');

        return array_merge($this->defaults(), is_array($stored) ? $stored : []);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function isProgramActive(): bool
    {
        $settings = $this->all();

        if (! ($settings['enabled'] ?? false)) {
            return false;
        }

        $startsAt = $settings['starts_at'] ?? null;
        if ($startsAt && now()->lt($startsAt)) {
            return false;
        }

        $endsAt = $settings['ends_at'] ?? null;
        if ($endsAt && now()->gt($endsAt)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'loyalty'],
            [
                'value' => array_merge($this->all(), $values),
                'group' => 'loyalty',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(): array
    {
        $settings = $this->all();

        return [
            'enabled' => $this->isProgramActive(),
            'program_name' => $settings['program_name'] ?? 'BNC bodovi',
            'program_description' => $settings['program_description'] ?? null,
            'points_per_km' => (float) ($settings['points_per_km'] ?? 1),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'enabled' => false,
            'program_name' => 'BNC bodovi',
            'program_description' => 'Skupljajte bodove za svaku isporučenu narudžbu i iskoristite nagrade.',
            'starts_at' => null,
            'ends_at' => null,
            'points_per_km' => 1,
            'combine_with_coupons' => false,
            'combine_with_discounts' => true,
            'expiry_mode' => 'never',
            'expiry_months' => 12,
            'guest_registration_prompt' => true,
        ];
    }
}
