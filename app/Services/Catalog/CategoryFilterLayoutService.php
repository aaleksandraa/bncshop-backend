<?php

namespace App\Services\Catalog;

use App\Models\AttributeCategoryMapping;
use App\Models\Category;

class CategoryFilterLayoutService
{
    /**
     * @var array<string, array{label: string, column: string}>
     */
    public const STANDARD_FILTERS = [
        'price' => [
            'label' => 'Cijena (min / max)',
            'column' => 'filter_price_enabled',
        ],
        'brand' => [
            'label' => 'Brend',
            'column' => 'filter_brand_enabled',
        ],
        'in_stock' => [
            'label' => 'Samo na stanju',
            'column' => 'filter_in_stock_enabled',
        ],
        'on_sale' => [
            'label' => 'Na akciji',
            'column' => 'filter_on_sale_enabled',
        ],
        'is_new' => [
            'label' => 'Novo',
            'column' => 'filter_is_new_enabled',
        ],
        'is_refurbished' => [
            'label' => 'Refurbished',
            'column' => 'filter_is_refurbished_enabled',
        ],
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildFormLayout(Category $category): array
    {
        return $this->resolveLayout($category->loadMissing(['attributeMappings.attributeDefinition']));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function resolveLayout(Category $category): array
    {
        $stored = $category->filter_layout;

        if (is_array($stored) && $stored !== []) {
            return $this->normalizeLayout($category, $stored);
        }

        return $this->defaultLayout($category);
    }

    /**
     * @return array<string, bool>
     */
    public function configFromLayout(array $layout): array
    {
        $config = [];

        foreach (self::STANDARD_FILTERS as $key => $meta) {
            $item = $this->findStandardItem($layout, $key);
            $config[$key] = $item !== null && ($item['enabled'] ?? false);
        }

        return $config;
    }

    /**
     * @param  array<int, array<string, mixed>>  $layout
     * @return array<int, array<string, mixed>>
     */
    public function applyLayoutToCategory(Category $category, array $layout): array
    {
        $normalized = $this->normalizeLayout($category, $layout);

        foreach (self::STANDARD_FILTERS as $key => $meta) {
            $item = $this->findStandardItem($normalized, $key);
            $category->{$meta['column']} = $item !== null && ($item['enabled'] ?? false);
        }

        $category->filter_layout = $normalized;

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $layout
     */
    public function syncAttributeMappings(Category $category, array $layout): void
    {
        $attributeOrder = 0;

        foreach ($layout as $item) {
            if (($item['type'] ?? null) !== 'attribute') {
                continue;
            }

            $attributeDefinitionId = (int) ($item['attribute_definition_id'] ?? 0);

            if ($attributeDefinitionId === 0) {
                continue;
            }

            AttributeCategoryMapping::query()
                ->where('category_id', $category->id)
                ->where('attribute_definition_id', $attributeDefinitionId)
                ->update([
                    'is_filter_enabled' => (bool) ($item['enabled'] ?? false),
                    'sort_order' => $attributeOrder,
                ]);

            $attributeOrder++;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultLayout(Category $category): array
    {
        $layout = [];

        foreach (self::STANDARD_FILTERS as $key => $meta) {
            $layout[] = [
                'type' => 'standard',
                'key' => $key,
                'label' => $meta['label'],
                'enabled' => (bool) ($category->{$meta['column']} ?? true),
            ];
        }

        $mappings = $category->attributeMappings
            ->sortBy('sort_order')
            ->values();

        $seenAttributes = [];

        foreach ($mappings as $mapping) {
            $definition = $mapping->attributeDefinition?->resolveCanonical();

            if ($definition === null || ! $definition->is_filter || isset($seenAttributes[$definition->id])) {
                continue;
            }

            $seenAttributes[$definition->id] = true;

            $layout[] = [
                'type' => 'attribute',
                'attribute_definition_id' => $definition->id,
                'label' => filled($definition->display_name) ? $definition->display_name : $definition->name,
                'enabled' => (bool) $mapping->is_filter_enabled,
            ];
        }

        return $layout;
    }

    /**
     * @param  array<int, array<string, mixed>>  $layout
     * @return array<int, array<string, mixed>>
     */
    private function normalizeLayout(Category $category, array $layout): array
    {
        $category->loadMissing(['attributeMappings.attributeDefinition']);

        $normalized = [];
        $seenStandard = [];
        $seenAttributes = [];

        foreach ($layout as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = (string) ($item['type'] ?? '');

            if ($type === 'standard') {
                $key = (string) ($item['key'] ?? '');

                if (! array_key_exists($key, self::STANDARD_FILTERS) || isset($seenStandard[$key])) {
                    continue;
                }

                $seenStandard[$key] = true;
                $normalized[] = [
                    'type' => 'standard',
                    'key' => $key,
                    'label' => self::STANDARD_FILTERS[$key]['label'],
                    'enabled' => (bool) ($item['enabled'] ?? false),
                ];

                continue;
            }

            if ($type === 'attribute') {
                $attributeDefinitionId = (int) ($item['attribute_definition_id'] ?? 0);

                if ($attributeDefinitionId === 0) {
                    continue;
                }

                $mapping = $category->attributeMappings
                    ->firstWhere('attribute_definition_id', $attributeDefinitionId);

                $definition = $mapping?->attributeDefinition?->resolveCanonical();

                if ($definition === null || ! $definition->is_filter || isset($seenAttributes[$definition->id])) {
                    continue;
                }

                $seenAttributes[$definition->id] = true;
                $normalized[] = [
                    'type' => 'attribute',
                    'attribute_definition_id' => $definition->id,
                    'label' => filled($definition->display_name) ? $definition->display_name : $definition->name,
                    'enabled' => (bool) ($item['enabled'] ?? false),
                ];
            }
        }

        foreach (self::STANDARD_FILTERS as $key => $meta) {
            if (isset($seenStandard[$key])) {
                continue;
            }

            $normalized[] = [
                'type' => 'standard',
                'key' => $key,
                'label' => $meta['label'],
                'enabled' => (bool) ($category->{$meta['column']} ?? true),
            ];
        }

        foreach ($category->attributeMappings->sortBy('sort_order') as $mapping) {
            $definition = $mapping->attributeDefinition?->resolveCanonical();

            if ($definition === null || ! $definition->is_filter || isset($seenAttributes[$definition->id])) {
                continue;
            }

            $seenAttributes[$definition->id] = true;

            $normalized[] = [
                'type' => 'attribute',
                'attribute_definition_id' => $definition->id,
                'label' => filled($definition->display_name) ? $definition->display_name : $definition->name,
                'enabled' => (bool) $mapping->is_filter_enabled,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $layout
     * @return array<string, mixed>|null
     */
    private function findStandardItem(array $layout, string $key): ?array
    {
        foreach ($layout as $item) {
            if (($item['type'] ?? null) === 'standard' && ($item['key'] ?? null) === $key) {
                return $item;
            }
        }

        return null;
    }
}
