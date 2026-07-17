<?php

namespace App\Services\Olx;

use App\Models\OlxAttributeMapping;
use App\Models\OlxCategoryAttribute;
use App\Models\Product;

class OlxWarrantyMapper
{
    /** @var array<int, int> */
    private const CATEGORY_WARRANTY_ATTR = [
        163 => 5160,
        38 => 5156,
        39 => 5157,
        162 => 5161,
        166 => 5159,
        1748 => 5152,
    ];

    public function warrantyAttributeId(int $olxCategoryId): ?int
    {
        return self::CATEGORY_WARRANTY_ATTR[$olxCategoryId] ?? null;
    }

    public function mapMonths(?string $rawValue, int $olxCategoryId): ?string
    {
        if ($rawValue === null || trim($rawValue) === '') {
            return null;
        }

        if (preg_match('/(\d+)/', $rawValue, $m)) {
            $months = (int) $m[1];

            if ($months >= 60) {
                return $olxCategoryId === 39 ? '5+ godina' : '3+ godine';
            }

            return (string) $months;
        }

        return null;
    }

    public function resolveForProduct(Product $product, int $olxCategoryId): ?string
    {
        $attrId = $this->warrantyAttributeId($olxCategoryId);

        if ($attrId === null) {
            return null;
        }

        $fromAttr = $this->findAttributeValueByNames($product, ['Garancija', 'Jamstvo', 'Warranty']);

        if ($fromAttr !== null) {
            $mapped = $this->mapMonths($fromAttr, $olxCategoryId);

            if ($mapped !== null) {
                return $mapped;
            }
        }

        if (preg_match('/(\d+)\s*mjesec/i', (string) $product->description, $m)) {
            return $this->mapMonths($m[0], $olxCategoryId);
        }

        if ($product->is_refurbished) {
            return '12';
        }

        return null;
    }

    /**
     * @param  array<int, string>  $names
     */
    private function findAttributeValueByNames(Product $product, array $names): ?string
    {
        $product->loadMissing(['attributeValues.attributeDefinition']);

        foreach ($product->attributeValues as $value) {
            $definition = $value->attributeDefinition?->resolveCanonical() ?? $value->attributeDefinition;
            $label = $definition?->display_name ?? $definition?->name ?? '';

            foreach ($names as $name) {
                if (strcasecmp($label, $name) === 0) {
                    return trim((string) ($value->display_value ?? $value->normalized_value ?? $value->raw_value ?? ''));
                }
            }
        }

        return null;
    }
}
