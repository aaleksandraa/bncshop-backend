<?php

namespace App\Services\Integrations;

use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\ElineCategory;
use App\Models\ElineCategoryMapping;
use App\Models\OlxAttributeMapping;
use App\Models\OlxCategory;
use App\Models\OlxCategoryMapping;
use Illuminate\Support\Facades\File;
use RuntimeException;

class IntegrationMappingTransfer
{
    public const DEFAULT_PATH = 'database/seeders/data/integration_mappings.json';

    /**
     * @return array<string, mixed>
     */
    public function export(): array
    {
        return [
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'eline_category_mappings' => ElineCategoryMapping::query()
                ->with(['elineCategory', 'category'])
                ->get()
                ->map(fn (ElineCategoryMapping $mapping): array => [
                    'eline_category' => $mapping->elineCategory?->name,
                    'is_enabled' => $mapping->is_enabled,
                    'category_external_id' => $mapping->category?->external_category_id,
                    'category_full_slug' => $mapping->category?->full_slug,
                    'category_name' => $mapping->category?->name,
                    'product_condition' => $mapping->product_condition,
                    'margin_percentage' => $mapping->margin_percentage,
                ])
                ->values()
                ->all(),
            'olx_category_mappings' => OlxCategoryMapping::query()
                ->with('category')
                ->get()
                ->map(fn (OlxCategoryMapping $mapping): array => [
                    'category_external_id' => $mapping->category?->external_category_id,
                    'category_full_slug' => $mapping->category?->full_slug,
                    'category_name' => $mapping->category?->name,
                    'olx_category_id' => $mapping->olx_category_id,
                    'olx_category_path' => $mapping->olx_category_path,
                    'is_enabled' => $mapping->is_enabled,
                    'include_descendants' => $mapping->include_descendants,
                ])
                ->values()
                ->all(),
            'olx_attribute_mappings' => OlxAttributeMapping::query()
                ->with('attributeDefinition')
                ->get()
                ->map(fn (OlxAttributeMapping $mapping): array => [
                    'olx_category_id' => $mapping->olx_category_id,
                    'olx_attribute_id' => $mapping->olx_attribute_id,
                    'attribute_external_id' => $mapping->attributeDefinition?->external_attribute_id,
                    'bnc_attribute_aliases' => $mapping->bnc_attribute_aliases,
                    'parser_pattern' => $mapping->parser_pattern,
                    'default_value' => $mapping->default_value,
                    'value_mappings' => $mapping->value_mappings,
                    'is_required_for_publish' => $mapping->is_required_for_publish,
                ])
                ->values()
                ->all(),
        ];
    }

    public function exportToFile(?string $path = null): string
    {
        $path ??= base_path(self::DEFAULT_PATH);
        $payload = $this->export();

        File::ensureDirectoryExists(dirname($path));
        File::put(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        return $path;
    }

    /**
     * @return array{eline: int, olx_categories: int, olx_attributes: int, warnings: array<int, string>}
     */
    public function importFromFile(?string $path = null, bool $onlyEnabled = false, bool $skipEline = false, bool $skipOlx = false): array
    {
        $path ??= base_path(self::DEFAULT_PATH);

        if (! File::exists($path)) {
            throw new RuntimeException("Integration mappings file not found: {$path}");
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        $warnings = [];
        $elineCount = 0;
        $olxCategoryCount = 0;
        $olxAttributeCount = 0;

        foreach ($payload['eline_category_mappings'] ?? [] as $row) {
            if ($skipEline) {
                break;
            }

            if ($onlyEnabled && ! ($row['is_enabled'] ?? false)) {
                continue;
            }

            $elineName = trim((string) ($row['eline_category'] ?? ''));

            if ($elineName === '') {
                continue;
            }

            $elineCategory = ElineCategory::query()->firstOrCreate(
                ['name' => $elineName],
                ['product_count' => 0],
            );

            $categoryId = $this->resolveCategoryId($row, $warnings, "eLine \"{$elineName}\"");

            ElineCategoryMapping::query()->updateOrCreate(
                ['eline_category_id' => $elineCategory->id],
                [
                    'category_id' => $categoryId,
                    'is_enabled' => (bool) ($row['is_enabled'] ?? false),
                    'product_condition' => (string) ($row['product_condition'] ?? ElineCategoryMapping::CONDITION_REFURBISHED),
                    'margin_percentage' => $row['margin_percentage'] ?? null,
                ],
            );

            $elineCount++;
        }

        foreach ($payload['olx_category_mappings'] ?? [] as $row) {
            if ($skipOlx) {
                break;
            }

            if ($onlyEnabled && ! ($row['is_enabled'] ?? false)) {
                continue;
            }

            $olxCategoryId = (int) $row['olx_category_id'];
            $this->ensureOlxCategory($olxCategoryId, $row['olx_category_path'] ?? null);

            $categoryId = $this->resolveCategoryId($row, $warnings, 'OLX category mapping');

            if ($categoryId === null) {
                continue;
            }

            OlxCategoryMapping::query()->updateOrCreate(
                ['category_id' => $categoryId],
                [
                    'olx_category_id' => (int) $row['olx_category_id'],
                    'olx_category_path' => $row['olx_category_path'] ?? null,
                    'is_enabled' => (bool) ($row['is_enabled'] ?? false),
                    'include_descendants' => (bool) ($row['include_descendants'] ?? true),
                ],
            );

            $olxCategoryCount++;
        }

        foreach ($payload['olx_attribute_mappings'] ?? [] as $row) {
            if ($skipOlx) {
                break;
            }

            $olxCategoryId = (int) $row['olx_category_id'];
            $this->ensureOlxCategory($olxCategoryId, null);

            $attributeDefinitionId = null;
            $attributeExternalId = trim((string) ($row['attribute_external_id'] ?? ''));

            if ($attributeExternalId !== '') {
                $attributeDefinitionId = AttributeDefinition::query()
                    ->where('external_attribute_id', $attributeExternalId)
                    ->value('id');
            }

            OlxAttributeMapping::query()->updateOrCreate(
                [
                    'olx_category_id' => (int) $row['olx_category_id'],
                    'olx_attribute_id' => (int) $row['olx_attribute_id'],
                ],
                [
                    'attribute_definition_id' => $attributeDefinitionId,
                    'bnc_attribute_aliases' => $row['bnc_attribute_aliases'] ?? null,
                    'parser_pattern' => $row['parser_pattern'] ?? null,
                    'default_value' => $row['default_value'] ?? null,
                    'value_mappings' => $row['value_mappings'] ?? null,
                    'is_required_for_publish' => (bool) ($row['is_required_for_publish'] ?? false),
                ],
            );

            $olxAttributeCount++;
        }

        return [
            'eline' => $elineCount,
            'olx_categories' => $olxCategoryCount,
            'olx_attributes' => $olxAttributeCount,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveCategoryId(array $row, array &$warnings, string $context): ?int
    {
        $externalId = trim((string) ($row['category_external_id'] ?? ''));

        if ($externalId !== '') {
            $categoryId = Category::query()
                ->where('external_category_id', $externalId)
                ->value('id');

            if ($categoryId !== null) {
                return (int) $categoryId;
            }

            $warnings[] = "{$context}: category external id {$externalId} not found on this environment.";
        }

        $fullSlug = trim((string) ($row['category_full_slug'] ?? ''));

        if ($fullSlug !== '') {
            $categoryId = Category::query()
                ->where('full_slug', $fullSlug)
                ->value('id');

            if ($categoryId !== null) {
                return (int) $categoryId;
            }

            $warnings[] = "{$context}: category full slug {$fullSlug} not found on this environment.";
        }

        $name = trim((string) ($row['category_name'] ?? ''));

        if ($name === '') {
            return null;
        }

        $matches = Category::query()->where('name', $name)->pluck('id');

        if ($matches->count() === 1) {
            return (int) $matches->first();
        }

        if ($matches->count() > 1) {
            $warnings[] = "{$context}: category name \"{$name}\" is ambiguous ({$matches->count()} matches).";
        } else {
            $warnings[] = "{$context}: category name \"{$name}\" not found on this environment.";
        }

        return null;
    }

    private function ensureOlxCategory(int $olxCategoryId, ?string $path): void
    {
        $name = $this->guessOlxCategoryName($path) ?? "OLX {$olxCategoryId}";

        OlxCategory::query()->firstOrCreate(
            ['id' => $olxCategoryId],
            [
                'name' => $name,
                'path' => $path,
                'fetched_at' => now(),
            ],
        );
    }

    private function guessOlxCategoryName(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $segments = array_values(array_filter(explode('>', $path)));

        return trim((string) end($segments)) ?: null;
    }
}
