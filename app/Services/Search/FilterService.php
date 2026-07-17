<?php

namespace App\Services\Search;

use App\Models\AttributeCategoryMapping;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\ProductAttributeValue;
use App\Services\Catalog\CategoryFilterLayoutService;
use App\Services\Catalog\CategoryScopeResolver;
use Illuminate\Support\Facades\DB;

class FilterService
{
    public function __construct(
        private readonly CategoryScopeResolver $categoryScopeResolver,
        private readonly CategoryFilterLayoutService $categoryFilterLayoutService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, string>
     */
    public function buildMeilisearchFilters(Category $category, array $filters = []): array
    {
        $categoryIds = $this->categoryScopeResolver->expandWithDescendants([(int) $category->id]);

        $expressions = [
            'category_id IN ['.implode(', ', $categoryIds).']',
            'is_public = true',
            "status = 'active'",
        ];

        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (str_starts_with((string) $key, 'attr_')) {
                $attributeId = (int) str_replace('attr_', '', (string) $key);
                $field = 'filter_attributes.'.$attributeId;
                $expressions[] = is_array($value)
                    ? $field.' IN ['.$this->quoteList($value).']'
                    : $field.' = '.$this->quote((string) $value);
                continue;
            }

            if (in_array($key, ['in_stock', 'on_sale', 'is_gaming', 'is_new', 'is_refurbished'], true) && ! $this->isTruthy($value)) {
                continue;
            }

            match ($key) {
                'manufacturer_id', 'brand_id' => $expressions[] = 'manufacturer_id = '.(int) $value,
                'min_price' => $expressions[] = 'display_price >= '.(float) $value,
                'max_price' => $expressions[] = 'display_price <= '.(float) $value,
                'in_stock' => $expressions[] = 'available_stock > 0',
                'on_sale' => $expressions[] = 'on_sale = true',
                'is_gaming' => $expressions[] = 'is_gaming = true',
                'is_new' => $expressions[] = 'is_new = true',
                'is_refurbished' => $expressions[] = 'is_refurbished = true',
                default => null,
            };
        }

        return array_values(array_filter($expressions));
    }

    /**
     * @return array{config: array<string, bool>, attributes: array<int, array<string, mixed>>, brands: array<int, array<string, mixed>>}
     */
    public function getCategoryFilterPayload(Category $category): array
    {
        $layout = $this->categoryFilterLayoutService->resolveLayout($category);
        $config = $this->categoryFilterLayoutService->configFromLayout($layout);
        $attributes = $this->getAvailableFilters($category);
        $attributesById = collect($attributes)->keyBy('attribute_definition_id');

        $orderedAttributes = [];

        foreach ($layout as $item) {
            if (($item['type'] ?? null) !== 'attribute' || ! ($item['enabled'] ?? false)) {
                continue;
            }

            $attribute = $attributesById->get((int) ($item['attribute_definition_id'] ?? 0));

            if ($attribute !== null) {
                $orderedAttributes[] = $attribute;
            }
        }

        $shopLayout = array_values(array_filter(
            $layout,
            fn (array $item): bool => (bool) ($item['enabled'] ?? false),
        ));

        return [
            'config' => $config,
            'layout' => $shopLayout,
            'attributes' => $orderedAttributes,
            'brands' => $config['brand'] ? $this->getAvailableBrands($category) : [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableFilters(Category $category): array
    {
        $categoryIds = $this->categoryScopeResolver->expandWithDescendants([(int) $category->id]);

        $mappings = AttributeCategoryMapping::query()
            ->whereIn('category_id', $categoryIds)
            ->where('is_filter_enabled', true)
            ->with(['attributeDefinition.canonicalDefinition'])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn ($mapping) => $mapping->attributeDefinition?->resolveCanonical()->is_filter)
            ->map(function ($mapping) {
                $definition = $mapping->attributeDefinition?->resolveCanonical();
                $mapping->setRelation('attributeDefinition', $definition);

                return $mapping;
            })
            ->unique(fn ($mapping) => $mapping->attributeDefinition?->id ?? $mapping->attribute_definition_id)
            ->values();

        if ($mappings->isEmpty()) {
            return [];
        }

        $definitionIds = $mappings->pluck('attribute_definition_id')->all();
        $valuesByDefinition = $this->distinctValuesForCategoriesBatch($categoryIds, $definitionIds);

        $filters = [];

        foreach ($mappings as $mapping) {
            $definition = $mapping->attributeDefinition;
            $values = $valuesByDefinition[$definition->id] ?? [];

            if ($values === []) {
                continue;
            }

            if ($this->isBooleanFilterDefinition($definition, $values)) {
                $trueCount = $this->countTruthyBooleanFilterValues($values);

                if ($trueCount === 0) {
                    continue;
                }

                $filters[] = [
                    'attribute_definition_id' => $definition->id,
                    'name' => filled($definition->display_name) ? $definition->display_name : $definition->name,
                    'type' => 'boolean',
                    'values' => [],
                    'true_count' => $trueCount,
                    'sort_order' => $mapping->sort_order,
                ];

                continue;
            }

            $filters[] = [
                'attribute_definition_id' => $definition->id,
                'name' => filled($definition->display_name) ? $definition->display_name : $definition->name,
                'type' => $definition->internal_type,
                'values' => $this->labelFilterValues($definition, $values),
                'sort_order' => $mapping->sort_order,
            ];
        }

        return $filters;
    }

    /**
     * @return array<int, array{id: int, name: string, slug: string, count: int}>
     */
    public function getAvailableBrands(Category $category): array
    {
        $categoryIds = $this->categoryScopeResolver->expandWithDescendants([(int) $category->id]);

        if ($categoryIds === []) {
            return [];
        }

        $rows = DB::table('products')
            ->join('manufacturers', 'manufacturers.id', '=', 'products.manufacturer_id')
            ->whereIn('products.category_id', $categoryIds)
            ->where('products.is_public', true)
            ->where('products.status', 'active')
            ->whereNotNull('products.manufacturer_id')
            ->groupBy('manufacturers.id', 'manufacturers.name', 'manufacturers.slug')
            ->select([
                'manufacturers.id',
                'manufacturers.name',
                'manufacturers.slug',
                DB::raw('COUNT(*) as count'),
            ])
            ->having(DB::raw('COUNT(*)'), '>', 0)
            ->orderBy('manufacturers.name')
            ->get();

        return $rows->map(fn ($row): array => [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'slug' => (string) $row->slug,
            'count' => (int) $row->count,
        ])->all();
    }

    /**
     * @param  array<int, int>  $categoryIds
     * @param  array<int, int>  $attributeDefinitionIds
     * @return array<int, array<int, array{value: string, label: string, count: int}>>
     */
    private function distinctValuesForCategoriesBatch(array $categoryIds, array $attributeDefinitionIds): array
    {
        if ($attributeDefinitionIds === [] || $categoryIds === []) {
            return [];
        }

        $rows = ProductAttributeValue::query()
            ->select([
                'product_attribute_values.attribute_definition_id',
                'product_attribute_values.normalized_value as value',
                DB::raw('COUNT(*) as count'),
            ])
            ->join('products', 'products.id', '=', 'product_attribute_values.product_id')
            ->whereIn('products.category_id', $categoryIds)
            ->where('products.is_public', true)
            ->where('products.status', 'active')
            ->whereIn(
                'product_attribute_values.attribute_definition_id',
                AttributeDefinition::expandedDefinitionIds(...$attributeDefinitionIds),
            )
            ->whereNotNull('product_attribute_values.normalized_value')
            ->groupBy(
                'product_attribute_values.attribute_definition_id',
                'product_attribute_values.normalized_value',
            )
            ->having(DB::raw('COUNT(*)'), '>', 0)
            ->orderBy('value')
            ->get();

        $aliasMap = AttributeDefinition::query()
            ->whereIn('canonical_attribute_definition_id', $attributeDefinitionIds)
            ->pluck('canonical_attribute_definition_id', 'id');

        $grouped = [];

        foreach ($rows as $row) {
            $definitionId = (int) $row->attribute_definition_id;
            $canonicalId = (int) ($aliasMap[$definitionId] ?? $definitionId);

            if (! in_array($canonicalId, $attributeDefinitionIds, true)) {
                continue;
            }

            $value = (string) $row->value;

            if (! isset($grouped[$canonicalId][$value])) {
                $grouped[$canonicalId][$value] = [
                    'value' => $value,
                    'label' => $this->booleanFilterLabel($value),
                    'count' => 0,
                ];
            }

            $grouped[$canonicalId][$value]['count'] += (int) $row->count;
        }

        return collect($grouped)
            ->map(fn (array $values): array => array_values($values))
            ->all();
    }

    /**
     * @param  array<int, array{value: string, label: string, count: int}>  $values
     * @return array<int, array{value: string, label: string, count: int}>
     */
    private function labelFilterValues(AttributeDefinition $definition, array $values): array
    {
        return array_map(function (array $entry) use ($definition): array {
            $entry['label'] = $this->booleanFilterLabel($entry['value'], $definition);

            return $entry;
        }, $values);
    }

    private function booleanFilterLabel(string $value, ?AttributeDefinition $definition = null): string
    {
        $normalized = strtolower(trim($value));

        if (in_array($normalized, ['true', '1', 'da', 'yes'], true)) {
            return 'Da';
        }

        if (in_array($normalized, ['false', '0', 'ne', 'no'], true)) {
            return 'Ne';
        }

        return $value;
    }

    /**
     * @param  array<int, array{value: string, label: string, count: int}>  $values
     */
    private function isBooleanFilterDefinition(AttributeDefinition $definition, array $values): bool
    {
        if ($definition->internal_type === 'boolean') {
            return true;
        }

        if ($values === []) {
            return false;
        }

        $booleanValues = ['true', 'false', '1', '0', 'da', 'ne', 'yes', 'no'];

        foreach ($values as $entry) {
            if (! in_array(strtolower($entry['value']), $booleanValues, true)) {
                return false;
            }
        }

        return count($values) <= 2;
    }

    /**
     * @param  array<int, array{value: string, label: string, count: int}>  $values
     */
    private function countTruthyBooleanFilterValues(array $values): int
    {
        $count = 0;

        foreach ($values as $entry) {
            if (in_array(strtolower($entry['value']), ['true', '1', 'da', 'yes'], true)) {
                $count += $entry['count'];
            }
        }

        return $count;
    }

    private function isTruthy(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }

    private function quote(string $value): string
    {
        return '"'.addslashes($value).'"';
    }

    /**
     * @param  array<int, string>  $values
     */
    private function quoteList(array $values): string
    {
        return implode(', ', array_map(fn (string $value): string => $this->quote($value), $values));
    }
}
