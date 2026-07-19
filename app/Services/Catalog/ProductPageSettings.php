<?php

namespace App\Services\Catalog;

use App\Models\SystemSetting;

class ProductPageSettings
{
    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'show_short_description' => true,
            'show_messaging_order_buttons' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = SystemSetting::query()
            ->where('key', 'product_page')
            ->value('value');

        if (! is_array($stored)) {
            return $this->defaults();
        }

        return array_merge($this->defaults(), $stored);
    }

    public function showShortDescription(): bool
    {
        return (bool) ($this->all()['show_short_description'] ?? true);
    }

    public function showMessagingOrderButtons(): bool
    {
        return (bool) ($this->all()['show_messaging_order_buttons'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'product_page'],
            [
                'group' => 'shop',
                'value' => [
                    'show_short_description' => (bool) ($data['show_short_description'] ?? true),
                    'show_messaging_order_buttons' => (bool) ($data['show_messaging_order_buttons'] ?? false),
                ],
            ],
        );
    }

    /**
     * @return array<string, bool>
     */
    public function publicPayload(): array
    {
        return [
            'show_short_description' => $this->showShortDescription(),
            'show_messaging_order_buttons' => $this->showMessagingOrderButtons(),
        ];
    }
}
