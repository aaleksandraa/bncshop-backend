<?php

namespace App\Services\B2b;

use App\Models\B2bAttributeDefinition;
use App\Models\B2bAttributeOption;
use App\Models\B2bCategory;
use App\Models\B2bProduct;
use App\Models\B2bProductAttributeValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class B2bProductAttributeService
{
    /**
     * @return Collection<int, B2bAttributeDefinition>
     */
    public function definitionsForCategory(?int $categoryId): Collection
    {
        if ($categoryId === null) {
            return collect();
        }

        $category = B2bCategory::query()->find($categoryId);

        if ($category === null) {
            return collect();
        }

        return $category->attributeDefinitions()
            ->where('b2b_attribute_definitions.is_active', true)
            ->with('options')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function hydrateFormState(B2bProduct $product): array
    {
        $product->loadMissing(['attributeValues.definition']);

        $state = [];

        foreach ($product->attributeValues as $attributeValue) {
            $definition = $attributeValue->definition;

            if ($definition === null) {
                continue;
            }

            $key = 'attr_'.$definition->slug;

            if ($definition->isMultiselect()) {
                $existing = $state[$key] ?? [];
                $existing[] = $attributeValue->value;
                $state[$key] = array_values(array_unique($existing));
            } else {
                $state[$key] = $attributeValue->value;
            }
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    public function syncFromForm(B2bProduct $product, array $formData): void
    {
        $definitions = $this->definitionsForCategory($product->b2b_category_id);

        if ($definitions->isEmpty()) {
            B2bProductAttributeValue::query()
                ->where('b2b_product_id', $product->id)
                ->delete();

            return;
        }

        $rows = [];

        foreach ($definitions as $definition) {
            $key = 'attr_'.$definition->slug;
            $raw = $formData[$key] ?? null;

            if ($definition->isMultiselect()) {
                $values = is_array($raw) ? $raw : [];
            } elseif ($definition->isText()) {
                $values = filled($raw) ? [(string) $raw] : [];
            } else {
                $values = filled($raw) ? [(string) $raw] : [];
            }

            foreach ($values as $value) {
                $value = trim((string) $value);

                if ($value === '') {
                    continue;
                }

                if (! $definition->isText()) {
                    $this->ensureOption($definition, $value);
                }

                $rows[] = [
                    'b2b_product_id' => $product->id,
                    'b2b_attribute_definition_id' => $definition->id,
                    'value' => $value,
                ];
            }
        }

        B2bProductAttributeValue::query()
            ->where('b2b_product_id', $product->id)
            ->delete();

        if ($rows !== []) {
            B2bProductAttributeValue::query()->insert(
                collect($rows)->map(fn (array $row): array => [
                    ...$row,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all(),
            );
        }
    }

    public function ensureOption(B2bAttributeDefinition $definition, string $value): B2bAttributeOption
    {
        return B2bAttributeOption::query()->firstOrCreate(
            [
                'b2b_attribute_definition_id' => $definition->id,
                'value' => $value,
            ],
            [
                'sort_order' => (int) ($definition->options()->max('sort_order') ?? 0) + 1,
            ],
        );
    }

    /**
     * @return array<int, array{slug: string, name: string, values: array<int, string>}>
     */
    public function formatForProduct(B2bProduct $product): array
    {
        $product->loadMissing(['attributeValues.definition']);

        return $product->attributeValues
            ->groupBy('b2b_attribute_definition_id')
            ->map(function (Collection $values): array {
                /** @var B2bProductAttributeValue $first */
                $first = $values->first();
                $definition = $first->definition;

                return [
                    'slug' => $definition?->slug ?? '',
                    'name' => $definition?->name ?? '',
                    'values' => $values->pluck('value')->values()->all(),
                ];
            })
            ->filter(fn (array $item): bool => $item['slug'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{slug: string, name: string, input_type: string, values: array<int, string>}>
     */
    public function filtersForCategory(B2bCategory $category): array
    {
        $definitions = $category->attributeDefinitions()
            ->where('b2b_attribute_definitions.is_active', true)
            ->where('b2b_attribute_definitions.is_filterable', true)
            ->orderBy('b2b_category_attribute.sort_order')
            ->orderBy('b2b_attribute_definitions.sort_order')
            ->orderBy('b2b_attribute_definitions.name')
            ->get();

        if ($definitions->isEmpty()) {
            return [];
        }

        $definitionIds = $definitions->pluck('id')->all();

        $distinctValues = B2bProductAttributeValue::query()
            ->select('b2b_attribute_definition_id', 'value')
            ->whereIn('b2b_attribute_definition_id', $definitionIds)
            ->whereHas('product', function ($query) use ($category): void {
                $query->where('is_active', true)
                    ->where('b2b_category_id', $category->id);
            })
            ->distinct()
            ->orderBy('value')
            ->get()
            ->groupBy('b2b_attribute_definition_id');

        return $definitions
            ->map(function (B2bAttributeDefinition $definition) use ($distinctValues): array {
                $values = $distinctValues->get($definition->id, collect())
                    ->pluck('value')
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'slug' => $definition->slug,
                    'name' => $definition->name,
                    'input_type' => $definition->input_type,
                    'values' => $values,
                ];
            })
            ->filter(fn (array $filter): bool => $filter['values'] !== [])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array<int, string>|string>  $attributeFilters
     */
    public function applyFilters($query, array $attributeFilters): void
    {
        foreach ($attributeFilters as $slug => $values) {
            $values = is_array($values) ? $values : [$values];
            $values = array_values(array_filter(array_map(
                fn ($value) => trim((string) $value),
                $values,
            )));

            if ($values === []) {
                continue;
            }

            $definitionId = B2bAttributeDefinition::query()
                ->where('slug', (string) $slug)
                ->where('is_active', true)
                ->value('id');

            if ($definitionId === null) {
                $query->whereRaw('0 = 1');

                continue;
            }

            $query->whereHas('attributeValues', function ($attributeQuery) use ($definitionId, $values): void {
                $attributeQuery
                    ->where('b2b_attribute_definition_id', $definitionId)
                    ->whereIn('value', $values);
            });
        }
    }

    public static function slugFromName(string $name): string
    {
        return Str::slug($name);
    }
}
