<?php

namespace App\Services\Sync;

use App\Models\Product;

class ProductImportChangeTracker
{
    /** @var list<string> */
    public const TRACKED_FIELDS = [
        'name',
        'slug',
        'description',
        'short_description',
        'barcode',
        'sku',
        'eline_sifra',
        'category_id',
        'manufacturer_id',
        'is_gaming',
        'is_public',
        'is_new',
        'is_refurbished',
        'margin_percentage',
        'api_price',
        'api_final_price',
        'regular_price',
        'display_price',
        'api_stock',
        'available_stock',
        'stock_status',
        'status',
        'api_rebate',
        'api_rebate_valid_until',
        'api_rebate_type',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(Product $product): array
    {
        $data = [];

        foreach (self::TRACKED_FIELDS as $field) {
            $data[$field] = self::normalizeValue($product->{$field});
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $before
     * @return list<string>
     */
    public static function diff(array $before, Product $after): array
    {
        $changed = [];

        foreach (self::TRACKED_FIELDS as $field) {
            if (self::normalizeValue($after->{$field}) !== ($before[$field] ?? null)) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    private static function normalizeValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return round((float) $value, 4);
        }

        return (string) $value;
    }
}
