<?php

namespace App\Services\Commerce;

use App\Models\SystemSetting;

class CheckoutSettings
{
    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = SystemSetting::query()->where('key', 'checkout')->value('value');

        return array_merge($this->defaults(), is_array($stored) ? $stored : []);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'checkout'],
            [
                'value' => array_merge($this->all(), $data),
                'group' => 'checkout',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'payment_methods' => ['pay_on_delivery', 'bank_transfer'],
            'shipping_methods' => ['delivery', 'pickup'],
            'guest_checkout_enabled' => true,
            'guest_registration_prompt_checkout' => true,
            'terms_page_slug' => 'uslovi',
            'privacy_page_slug' => 'privatnost',
            'terms_default_checked' => true,
        ];
    }
}
