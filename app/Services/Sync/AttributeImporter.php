<?php

namespace App\Services\Sync;

use App\Models\AttributeCategoryMapping;
use App\Models\AttributeDefinition;
use App\Models\Category;
use Illuminate\Support\Facades\Log;

class AttributeImporter
{
    /**
     * @param  array<int, array<string, mixed>>  $attributes
     * @return array{created: int, updated: int, mappings: int}
     */
    public function upsertMany(array $attributes): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'mappings' => 0];

        foreach ($attributes as $payload) {
            $result = $this->upsertOne($payload);
            $stats['created'] += $result['created'];
            $stats['updated'] += $result['updated'];
            $stats['mappings'] += $result['mappings'];
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{created: int, updated: int, mappings: int}
     */
    public function upsertOne(array $payload): array
    {
        $externalId = (string) (
            $payload['productAttributeDefinitionId']
            ?? $payload['attributeId']
            ?? $payload['external_attribute_id']
            ?? ''
        );

        $apiType = isset($payload['type']) ? (int) $payload['type'] : null;
        $internalType = config('bnc.attribute_type_map')[$apiType] ?? 'text';

        $optionsJson = $payload['optionsJson'] ?? $payload['options_json'] ?? null;
        $parsedOptions = $this->parseOptions($optionsJson);

        $existing = AttributeDefinition::query()
            ->where('external_attribute_id', $externalId)
            ->first();

        $attributes = [
            'external_attribute_id' => $externalId,
            'name' => (string) ($payload['name'] ?? ''),
            'external_id' => $payload['externalId'] ?? null,
            'olx_id' => $payload['olxId'] ?? null,
            'olx_name' => $payload['olxName'] ?? null,
            'api_type' => $apiType,
            'internal_type' => $internalType,
            'is_filter' => (bool) ($payload['isFilter'] ?? false),
            'olx_required' => (bool) ($payload['olxRequired'] ?? false),
            'options_json' => $optionsJson,
            'parsed_options' => $parsedOptions,
        ];

        if (! $existing?->is_public_locked) {
            $attributes['is_public'] = (bool) ($payload['isPublic'] ?? true);
        }

        if ($existing) {
            $existing->update($attributes);
            $definition = $existing;
            $created = 0;
            $updated = 1;
        } else {
            $attributes['is_public'] ??= (bool) ($payload['isPublic'] ?? true);
            $definition = AttributeDefinition::query()->create($attributes);
            $created = 1;
            $updated = 0;
        }

        $mappings = $this->syncCategoryMappings($definition, $payload['categories'] ?? []);

        return ['created' => $created, 'updated' => $updated, 'mappings' => $mappings];
    }

    /**
     * @param  array<int, array<string, mixed>>  $categories
     */
    private function syncCategoryMappings(AttributeDefinition $definition, array $categories): int
    {
        $count = 0;

        foreach ($categories as $index => $categoryPayload) {
            $externalCategoryId = (string) ($categoryPayload['categoryId'] ?? '');
            $category = Category::query()->where('external_category_id', $externalCategoryId)->first();

            if (! $category) {
                continue;
            }

            AttributeCategoryMapping::query()->updateOrCreate(
                [
                    'attribute_definition_id' => $definition->id,
                    'category_id' => $category->id,
                ],
                [
                    'external_category_id' => $externalCategoryId,
                    'category_name' => $categoryPayload['name'] ?? $category->name,
                    'is_filter_enabled' => $definition->is_filter,
                    'is_public_enabled' => $definition->is_public,
                    'sort_order' => $index,
                ]
            );

            $count++;
        }

        return $count;
    }

    private function parseOptions(mixed $optionsJson): ?array
    {
        if ($optionsJson === null || $optionsJson === '') {
            return null;
        }

        if (is_array($optionsJson)) {
            return $optionsJson;
        }

        $decoded = json_decode((string) $optionsJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Invalid attribute optionsJson', ['options' => $optionsJson]);

            return null;
        }

        return $decoded;
    }
}
