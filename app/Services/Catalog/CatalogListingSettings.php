<?php

namespace App\Services\Catalog;

use App\Models\SystemSetting;

class CatalogListingSettings
{
    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'hide_out_of_stock_refurbished_eline' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = SystemSetting::query()
            ->where('key', 'catalog_listing')
            ->value('value');

        if (! is_array($stored)) {
            return $this->defaults();
        }

        return array_merge($this->defaults(), $stored);
    }

    public function hideOutOfStockRefurbishedEline(): bool
    {
        return (bool) ($this->all()['hide_out_of_stock_refurbished_eline'] ?? true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'catalog_listing'],
            [
                'group' => 'shop',
                'value' => [
                    'hide_out_of_stock_refurbished_eline' => (bool) ($data['hide_out_of_stock_refurbished_eline'] ?? true),
                ],
            ],
        );
    }

    public function meilisearchExclusionFilter(): string
    {
        return '(available_stock > 0 OR (is_refurbished = false AND import_source != "eline"))';
    }
}
