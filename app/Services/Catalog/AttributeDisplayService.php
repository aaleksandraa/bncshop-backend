<?php

namespace App\Services\Catalog;

use App\Models\AttributeDefinition;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Collection;

class AttributeDisplayService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function formatForProduct(ProductAttributeValue $value): array
    {
        $definition = $value->attributeDefinition?->resolveCanonical();

        return [
            'id' => $value->id,
            'attribute_definition_id' => $definition?->id ?? $value->attribute_definition_id,
            'display_name' => $this->displayName($definition, $value),
            'display_value' => $this->displayValue($definition, $value),
            'normalized_type' => $value->normalized_type,
            'sort_order' => $definition?->detail_sort_order ?? 0,
        ];
    }

    /**
     * @param  Collection<int, ProductAttributeValue>  $values
     * @return array<int, array<string, mixed>>
     */
    public function formatManyForProduct(Collection $values, ?int $categoryId = null): array
    {
        return $values
            ->filter(fn (ProductAttributeValue $value): bool => $this->shouldShowOnFrontend($value, $categoryId))
            ->groupBy(fn (ProductAttributeValue $value): int => $value->attributeDefinition?->resolveCanonicalId() ?? $value->attribute_definition_id)
            ->map(fn (Collection $group): ProductAttributeValue => $this->pickPreferredValue($group))
            ->sortBy(fn (ProductAttributeValue $value): array => [
                $this->sortOrderForCategory($value, $categoryId),
                $this->displayName($value->attributeDefinition?->resolveCanonical(), $value),
            ])
            ->values()
            ->map(fn (ProductAttributeValue $value): array => $this->formatForProduct($value))
            ->all();
    }

    /**
     * @param  Collection<int, ProductAttributeValue>  $values
     */
    private function pickPreferredValue(Collection $values): ProductAttributeValue
    {
        return $values->sortByDesc(fn (ProductAttributeValue $value): array => [
            (int) $value->is_locked,
            strlen(trim((string) ($value->normalized_value ?? $value->raw_value ?? ''))),
        ])->first();
    }

    private function sortOrderForCategory(ProductAttributeValue $value, ?int $categoryId): int
    {
        $definition = $value->attributeDefinition;

        if ($categoryId !== null && $definition) {
            $mapping = $definition->categoryMappings
                ->firstWhere('category_id', $categoryId);

            if ($mapping && $mapping->sort_order !== null) {
                return (int) $mapping->sort_order;
            }
        }

        return $definition?->detail_sort_order ?? 9999;
    }

    public function shouldShowOnFrontend(ProductAttributeValue $value, ?int $categoryId = null): bool
    {
        $definition = $value->attributeDefinition?->resolveCanonical();

        if (! $definition || ! $definition->is_public) {
            return false;
        }

        if ($categoryId !== null) {
            $mapping = $definition->categoryMappings
                ->firstWhere('category_id', $categoryId);

            if ($mapping && ! $mapping->is_public_enabled) {
                return false;
            }
        }

        $displayValue = $this->displayValue($definition, $value);

        return $displayValue !== null && $displayValue !== '';
    }

    public function displayName(?AttributeDefinition $definition, ProductAttributeValue $value): string
    {
        if ($definition?->display_name) {
            return $definition->display_name;
        }

        if ($definition?->name) {
            return $definition->name;
        }

        return $value->attribute_name_snapshot ?: 'Atribut';
    }

    public function displayValue(?AttributeDefinition $definition, ProductAttributeValue $value): ?string
    {
        $raw = trim((string) ($value->raw_value ?? ''));
        $normalized = trim((string) ($value->normalized_value ?? ''));

        if ($definition) {
            $mapped = $this->mapConfiguredValue($definition, $raw, $normalized);
            if ($mapped !== null) {
                return $mapped;
            }
        }

        if ($this->isTruthyBoolean($raw) || $this->isFalsyBoolean($raw)) {
            return $this->defaultBooleanLabel($raw);
        }

        if ($normalized === 'true' || $normalized === 'false') {
            return $this->defaultBooleanLabel($normalized);
        }

        if ($raw !== '' && ! $this->looksLikeRawBooleanToken($raw)) {
            return $raw;
        }

        if ($normalized !== '') {
            if ($definition?->display_unit && is_numeric(str_replace(',', '.', $normalized))) {
                return trim($normalized.' '.$definition->display_unit);
            }

            return $normalized;
        }

        return null;
    }

  private function formatMappedLabel(string $label): string
    {
        $normalized = strtolower(trim($label));

        if (in_array($normalized, ['true', '1', 'yes', 'da'], true)) {
            return 'Da';
        }

        if (in_array($normalized, ['false', '0', 'no', 'ne'], true)) {
            return 'Ne';
        }

        return $label;
    }

    private function mapConfiguredValue(AttributeDefinition $definition, string $raw, string $normalized): ?string
    {
        $mappings = array_merge(
            $this->normalizeMappingKeys($definition->value_mappings ?? []),
            $this->normalizeMappingKeys($definition->parsed_options ?? []),
        );

        foreach ([$raw, $normalized, strtolower($raw), strtolower($normalized)] as $key) {
            if ($key === '') {
                continue;
            }

            if (array_key_exists($key, $mappings)) {
                return $this->formatMappedLabel((string) $mappings[$key]);
            }
        }

        if ($definition->internal_type === 'boolean') {
            if ($normalized === 'true' || $this->isTruthyBoolean($raw)) {
                return (string) ($mappings['true'] ?? $mappings['1'] ?? 'Da');
            }

            if ($normalized === 'false' || $this->isFalsyBoolean($raw)) {
                return (string) ($mappings['false'] ?? $mappings['0'] ?? 'Ne');
            }
        }

        if (($normalized === 'true' || $normalized === 'false')
            && $this->hasBooleanDefaults($mappings)) {
            if ($normalized === 'true' || $this->isTruthyBoolean($raw)) {
                return (string) ($mappings['true'] ?? $mappings['1'] ?? 'Da');
            }

            if ($normalized === 'false' || $this->isFalsyBoolean($raw)) {
                return (string) ($mappings['false'] ?? $mappings['0'] ?? 'Ne');
            }
        }

        if ($definition->display_unit && is_numeric(str_replace(',', '.', $normalized))) {
            return trim($normalized.' '.$definition->display_unit);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $mappings
     * @return array<string, string>
     */
    private function normalizeMappingKeys(array $mappings): array
    {
        $normalized = [];

        foreach ($mappings as $key => $label) {
            if ($label === null || $label === '') {
                continue;
            }

            $normalized[(string) $key] = (string) $label;
            $normalized[strtolower((string) $key)] = (string) $label;
        }

        return $normalized;
    }

    /**
     * @param  array<string, string>  $mappings
     */
    private function hasBooleanDefaults(array $mappings): bool
    {
        return isset($mappings['true']) || isset($mappings['false']) || isset($mappings['da']) || isset($mappings['ne']);
    }

    private function defaultBooleanLabel(string $value): string
    {
        return $this->isTruthyBoolean($value) ? 'Da' : 'Ne';
    }

    private function isTruthyBoolean(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['true', 'da', '1', 'yes'], true);
    }

    private function isFalsyBoolean(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['false', 'ne', '0', 'no'], true);
    }

    private function looksLikeRawBooleanToken(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['true', 'false'], true);
    }
}
