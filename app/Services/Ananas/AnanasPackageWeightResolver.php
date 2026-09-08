<?php

namespace App\Services\Ananas;

use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Models\ProductAttributeValue;

class AnanasPackageWeightResolver
{
    /** @var array<int, string>|null */
    private ?array $chainDefinitionIdsCache = null;

    /**
     * @return list<string>
     */
    public function approvedAttributeNames(): array
    {
        return config('bnc.ananas_weight_attribute_names', []);
    }

    public function resolve(Product $product): AnanasPackageWeightResult
    {
        $product->loadMissing(['attributeValues.attributeDefinition']);

        foreach ($this->chainDefinitionIds() as $definitionId => $chainName) {
            $value = $this->firstValueForDefinition($product, (int) $definitionId);

            if ($value === null) {
                continue;
            }

            $raw = $this->normalizeWhitespace((string) $value->raw_value);

            if ($raw === '') {
                continue;
            }

            return $this->parseRawValue($raw, $chainName);
        }

        return AnanasPackageWeightResult::missing();
    }

    private function parseRawValue(string $raw, string $sourceAttributeName): AnanasPackageWeightResult
    {
        if ($this->looksLikeCapacity($raw)) {
            return AnanasPackageWeightResult::unparseable($sourceAttributeName, $raw, 'Value resembles capacity, not package weight.');
        }

        if (preg_match('/^\s*(-?\d+(?:[.,]\d+)?)\s*(g|gram|grams|kg|kilogram|kilograms)\s*$/iu', $raw, $matches)) {
            $numeric = $this->parseDecimal($matches[1]);
            $unit = strtolower($matches[2]);

            if ($numeric === null) {
                return AnanasPackageWeightResult::unparseable($sourceAttributeName, $raw, 'Malformed numeric portion.');
            }

            $kg = str_starts_with($unit, 'g') ? $numeric / 1000 : $numeric;

            return $this->finalizeKg($kg, $sourceAttributeName, $raw);
        }

        if (preg_match('/^\s*(-?\d+(?:[.,]\d+)?)\s*$/u', $raw)) {
            return AnanasPackageWeightResult::unitlessAmbiguous($sourceAttributeName, $raw);
        }

        return AnanasPackageWeightResult::unparseable($sourceAttributeName, $raw, 'Unrecognized weight format.');
    }

    private function finalizeKg(float $kg, string $sourceAttributeName, string $raw): AnanasPackageWeightResult
    {
        if ($kg <= 0) {
            return AnanasPackageWeightResult::zeroOrNegative($sourceAttributeName, $raw);
        }

        return AnanasPackageWeightResult::ok(round($kg, 6), $sourceAttributeName, $raw);
    }

    private function looksLikeCapacity(string $raw): bool
    {
        return (bool) preg_match('/maksimalna\s+težina|težina\s+kartona|težina\s+plastike|ambalaž/i', $raw);
    }

    private function parseDecimal(string $value): ?float
    {
        $normalized = str_replace([' ', "\u{00A0}"], '', $value);
        $normalized = str_replace(',', '.', $normalized);

        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function normalizeWhitespace(string $value): string
    {
        $value = preg_replace('/[\x{00A0}\s]+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * @return array<int, string> definition_id => chain label
     */
    private function chainDefinitionIds(): array
    {
        if ($this->chainDefinitionIdsCache !== null) {
            return $this->chainDefinitionIdsCache;
        }

        $ordered = [];
        $names = $this->approvedAttributeNames();

        foreach ($names as $name) {
            $definitions = AttributeDefinition::query()
                ->where(function ($query) use ($name): void {
                    $query->where('name', $name)
                        ->orWhere('display_name', $name);
                })
                ->get();

            foreach ($definitions as $definition) {
                $canonicalId = $definition->resolveCanonicalId();
                $expanded = AttributeDefinition::expandedDefinitionIds($canonicalId);

                foreach ($expanded as $definitionId) {
                    $ordered[(int) $definitionId] = $name;
                }
            }
        }

        $this->chainDefinitionIdsCache = $ordered;

        return $this->chainDefinitionIdsCache;
    }

    private function firstValueForDefinition(Product $product, int $definitionId): ?ProductAttributeValue
    {
        foreach ($product->attributeValues as $value) {
            if ((int) $value->attribute_definition_id === $definitionId) {
                return $value;
            }

            $definition = $value->attributeDefinition;

            if ($definition !== null && $definition->resolveCanonicalId() === $definitionId) {
                return $value;
            }
        }

        return null;
    }
}
