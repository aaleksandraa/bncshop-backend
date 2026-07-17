<?php

namespace App\Services\Catalog;

use App\Jobs\ReindexProductsJob;
use App\Models\AttributeCategoryMapping;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AttributeMergeService
{
    /**
     * @return array{products: int, mappings: int, aliases: int}
     */
    public function merge(AttributeDefinition $canonical, AttributeDefinition $source): array
    {
        if ($canonical->id === $source->id) {
            throw new InvalidArgumentException('Atribut se ne može spojiti sam u sebe.');
        }

        if ($source->canonical_attribute_definition_id !== null) {
            throw new InvalidArgumentException('Atribut je već spojen u drugi atribut.');
        }

        if ($canonical->canonical_attribute_definition_id !== null) {
            $canonical = $canonical->resolveCanonical();
        }

        $affectedProductIds = [];

        DB::transaction(function () use ($canonical, $source, &$affectedProductIds): void {
            $affectedProductIds = $this->moveProductValues($canonical, $source);
            $this->mergeCategoryMappings($canonical, $source);
            $this->updateCategoryFilterLayouts($canonical, $source);
            $this->mergeDefinitionMetadata($canonical, $source);

            $source->update([
                'canonical_attribute_definition_id' => $canonical->id,
                'is_public' => false,
                'is_filter' => false,
                'is_mapped' => true,
            ]);
        });

        if ($affectedProductIds !== []) {
            ReindexProductsJob::dispatch(array_values(array_unique($affectedProductIds)));
        }

        app(ProductReadCache::class)->flushProducts();

        return [
            'products' => count(array_unique($affectedProductIds)),
            'mappings' => AttributeCategoryMapping::query()
                ->where('attribute_definition_id', $canonical->id)
                ->count(),
            'aliases' => AttributeDefinition::query()
                ->where('canonical_attribute_definition_id', $canonical->id)
                ->count(),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function moveProductValues(AttributeDefinition $canonical, AttributeDefinition $source): array
    {
        $affectedProductIds = [];

        $sourceValues = ProductAttributeValue::query()
            ->where('attribute_definition_id', $source->id)
            ->get();

        foreach ($sourceValues as $sourceValue) {
            $affectedProductIds[] = (int) $sourceValue->product_id;

            $existing = ProductAttributeValue::query()
                ->where('product_id', $sourceValue->product_id)
                ->where('attribute_definition_id', $canonical->id)
                ->first();

            if ($existing === null) {
                $sourceValue->update(['attribute_definition_id' => $canonical->id]);

                continue;
            }

            if ($this->shouldReplaceExistingValue($existing, $sourceValue)) {
                $existing->update([
                    'attribute_name_snapshot' => $sourceValue->attribute_name_snapshot,
                    'raw_value' => $sourceValue->raw_value,
                    'normalized_value' => $sourceValue->normalized_value,
                    'normalized_type' => $sourceValue->normalized_type,
                    'is_locked' => $existing->is_locked || $sourceValue->is_locked,
                ]);
            }

            $sourceValue->delete();
        }

        return $affectedProductIds;
    }

    private function shouldReplaceExistingValue(
        ProductAttributeValue $existing,
        ProductAttributeValue $incoming,
    ): bool {
        if ($existing->is_locked && ! $incoming->is_locked) {
            return false;
        }

        if (! $existing->is_locked && $incoming->is_locked) {
            return true;
        }

        $existingValue = trim((string) ($existing->normalized_value ?? $existing->raw_value ?? ''));
        $incomingValue = trim((string) ($incoming->normalized_value ?? $incoming->raw_value ?? ''));

        if ($existingValue === '' && $incomingValue !== '') {
            return true;
        }

        return false;
    }

    private function mergeCategoryMappings(AttributeDefinition $canonical, AttributeDefinition $source): void
    {
        $sourceMappings = AttributeCategoryMapping::query()
            ->where('attribute_definition_id', $source->id)
            ->get();

        foreach ($sourceMappings as $mapping) {
            $existing = AttributeCategoryMapping::query()
                ->where('attribute_definition_id', $canonical->id)
                ->where('category_id', $mapping->category_id)
                ->first();

            if ($existing === null) {
                $mapping->update(['attribute_definition_id' => $canonical->id]);

                continue;
            }

            $existing->update([
                'is_filter_enabled' => $existing->is_filter_enabled || $mapping->is_filter_enabled,
                'is_public_enabled' => $existing->is_public_enabled || $mapping->is_public_enabled,
                'sort_order' => min((int) $existing->sort_order, (int) $mapping->sort_order),
            ]);

            $mapping->delete();
        }
    }

    private function updateCategoryFilterLayouts(AttributeDefinition $canonical, AttributeDefinition $source): void
    {
        Category::query()
            ->whereNotNull('filter_layout')
            ->chunkById(100, function ($categories) use ($canonical, $source): void {
                foreach ($categories as $category) {
                    $layout = $category->filter_layout;

                    if (! is_array($layout) || $layout === []) {
                        continue;
                    }

                    $updated = false;
                    $seenCanonical = false;
                    $normalized = [];

                    foreach ($layout as $item) {
                        if (! is_array($item) || ($item['type'] ?? null) !== 'attribute') {
                            $normalized[] = $item;

                            continue;
                        }

                        $attributeDefinitionId = (int) ($item['attribute_definition_id'] ?? 0);

                        if ($attributeDefinitionId === $source->id) {
                            $item['attribute_definition_id'] = $canonical->id;
                            $item['label'] = filled($canonical->display_name)
                                ? $canonical->display_name
                                : $canonical->name;
                            $updated = true;
                            $attributeDefinitionId = $canonical->id;
                        }

                        if ($attributeDefinitionId === $canonical->id) {
                            if ($seenCanonical) {
                                $updated = true;

                                continue;
                            }

                            $seenCanonical = true;
                        }

                        $normalized[] = $item;
                    }

                    if ($updated) {
                        $category->update(['filter_layout' => $normalized]);
                    }
                }
            });
    }

    private function mergeDefinitionMetadata(AttributeDefinition $canonical, AttributeDefinition $source): void
    {
        $updates = [];

        if (! filled($canonical->display_name) && filled($source->display_name)) {
            $updates['display_name'] = $source->display_name;
        }

        if (! filled($canonical->display_unit) && filled($source->display_unit)) {
            $updates['display_unit'] = $source->display_unit;
        }

        if (! $canonical->is_filter && $source->is_filter) {
            $updates['is_filter'] = true;
        }

        $updates['value_mappings'] = $this->mergeMappings(
            $canonical->value_mappings ?? [],
            $source->value_mappings ?? [],
        );

        $updates['parsed_options'] = $this->mergeMappings(
            $canonical->parsed_options ?? [],
            $source->parsed_options ?? [],
        );

        if ($updates !== []) {
            $canonical->update($updates);
        }
    }

    /**
     * @param  array<string|int, mixed>  $primary
     * @param  array<string|int, mixed>  $secondary
     * @return array<string|int, mixed>
     */
    private function mergeMappings(array $primary, array $secondary): array
    {
        foreach ($secondary as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (! array_key_exists($key, $primary) || $primary[$key] === null || $primary[$key] === '') {
                $primary[$key] = $value;
            }
        }

        return $primary;
    }
}
