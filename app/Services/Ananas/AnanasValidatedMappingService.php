<?php

namespace App\Services\Ananas;

use App\Models\AnanasCategoryMapping;
use App\Models\AnanasProductType;
use App\Models\Category;
use App\Support\CategoryAdminSearch;
use Illuminate\Support\Carbon;

class AnanasValidatedMappingService
{
    public function __construct(
        private readonly AnanasExportScope $exportScope,
    ) {}

    /**
     * Stage-validated BNC → Ananas category strings (GET /products categories[]).
     *
     * @return list<array{
     *     category_id: int,
     *     ananas_product_type: string,
     *     ananas_category: string,
     *     include_descendants: bool,
     *     is_enabled: bool,
     *     observed_categories: list<string>,
     *     notes: string
     * }>
     */
    public function definitions(): array
    {
        $rows = config('bnc.ananas_validated_mappings', []);

        if (! is_array($rows)) {
            return [];
        }

        $definitions = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $categoryId = (int) ($row['category_id'] ?? 0);
            $ananasCategory = trim((string) ($row['ananas_category'] ?? ''));
            $productType = trim((string) ($row['ananas_product_type'] ?? 'ITShop'));

            if ($categoryId <= 0 || $ananasCategory === '' || $productType === '') {
                continue;
            }

            $observed = $row['observed_categories'] ?? [$ananasCategory];
            $observed = is_array($observed)
                ? array_values(array_filter(array_map(static fn ($value): string => trim((string) $value), $observed)))
                : [$ananasCategory];

            $definitions[] = [
                'category_id' => $categoryId,
                'ananas_product_type' => $productType,
                'ananas_category' => $ananasCategory,
                'include_descendants' => (bool) ($row['include_descendants'] ?? true),
                'is_enabled' => (bool) ($row['is_enabled'] ?? true),
                'observed_categories' => $observed !== [] ? $observed : [$ananasCategory],
                'notes' => trim((string) ($row['notes'] ?? '')),
            ];
        }

        return $definitions;
    }

    /**
     * @return array{
     *     applied: list<array{action: string, mapping_id: int, category_id: int, bnc_category: string, ananas_category: string, enabled: bool}>,
     *     skipped: list<string>,
     *     disabled: list<array{mapping_id: int, category_id: int, ananas_category: string, reason: string}>
     * }
     */
    public function apply(): array
    {
        $applied = [];
        $skipped = [];
        $keptCategoryIds = [];

        foreach ($this->definitions() as $definition) {
            $category = Category::query()->find($definition['category_id']);

            if ($category === null) {
                $skipped[] = sprintf(
                    'BNC kategorija #%d ne postoji — preskačem %s.',
                    $definition['category_id'],
                    $definition['ananas_category'],
                );

                continue;
            }

            $this->ensureProductType($definition['ananas_product_type']);

            $existed = AnanasCategoryMapping::query()->where('category_id', $definition['category_id'])->exists();

            $mapping = $this->upsert(
                categoryId: $definition['category_id'],
                productType: $definition['ananas_product_type'],
                ananasCategory: $definition['ananas_category'],
                enabled: $definition['is_enabled'],
                includeDescendants: $definition['include_descendants'],
                validationStatus: AnanasCategoryMapping::VALIDATION_VALIDATED,
                observedCategories: $definition['observed_categories'],
                notes: $definition['notes'] !== ''
                    ? $definition['notes']
                    : 'Primijenjeno iz Stage-validiranih mapiranja.',
            );

            $keptCategoryIds[] = (int) $mapping->category_id;

            $applied[] = [
                'action' => $existed ? 'updated' : 'created',
                'mapping_id' => (int) $mapping->id,
                'category_id' => (int) $mapping->category_id,
                'bnc_category' => CategoryAdminSearch::formatOptionLabel($category),
                'ananas_category' => (string) $mapping->ananas_category,
                'enabled' => (bool) $mapping->is_enabled,
            ];
        }

        $disabled = $this->disableStaleMappings($keptCategoryIds);
        $this->exportScope->flushCaches();

        return compact('applied', 'skipped', 'disabled');
    }

    /**
     * @param  list<string>|null  $observedCategories
     */
    public function upsert(
        int $categoryId,
        string $productType,
        ?string $ananasCategory,
        bool $enabled,
        bool $includeDescendants,
        string $validationStatus = AnanasCategoryMapping::VALIDATION_UNKNOWN,
        ?array $observedCategories = null,
        ?string $notes = null,
        bool $preserveEnabledWhenUpdating = false,
    ): AnanasCategoryMapping {
        $existing = AnanasCategoryMapping::query()->where('category_id', $categoryId)->first();

        $payload = [
            'ananas_product_type' => $productType,
            'ananas_category' => $ananasCategory !== null && trim($ananasCategory) !== '' ? trim($ananasCategory) : null,
            'include_descendants' => $includeDescendants,
            'category_validation_status' => $validationStatus,
        ];

        if (! ($preserveEnabledWhenUpdating && $existing instanceof AnanasCategoryMapping)) {
            $payload['is_enabled'] = $enabled;
        }

        if ($observedCategories !== null) {
            $payload['observed_categories'] = $observedCategories;
        }

        if ($notes !== null && trim($notes) !== '') {
            $payload['category_validation_notes'] = trim($notes);
        }

        if ($validationStatus === AnanasCategoryMapping::VALIDATION_VALIDATED) {
            $payload['category_validated_at'] = Carbon::now();
        }

        $mapping = AnanasCategoryMapping::query()->updateOrCreate(
            ['category_id' => $categoryId],
            $payload,
        );

        $this->exportScope->flushCaches();

        return $mapping;
    }

    /**
     * @return list<array{id: int, category_id: int, bnc_category: string, product_type: string, ananas_category: string, enabled: bool, validation: string, products_count: int}>
     */
    public function summaryRows(): array
    {
        return AnanasCategoryMapping::query()
            ->with('category')
            ->orderBy('id')
            ->get()
            ->map(function (AnanasCategoryMapping $mapping): array {
                $category = $mapping->category;

                return [
                    'id' => (int) $mapping->id,
                    'category_id' => (int) $mapping->category_id,
                    'bnc_category' => $category !== null
                        ? CategoryAdminSearch::formatOptionLabel($category)
                        : '#'.$mapping->category_id,
                    'product_type' => (string) $mapping->ananas_product_type,
                    'ananas_category' => (string) ($mapping->ananas_category ?: '—'),
                    'enabled' => (bool) $mapping->is_enabled,
                    'validation' => (string) ($mapping->category_validation_status ?: AnanasCategoryMapping::VALIDATION_UNKNOWN),
                    'products_count' => $this->exportScope->scopedProductCountForMapping($mapping),
                ];
            })
            ->all();
    }

    private function ensureProductType(string $name): void
    {
        AnanasProductType::query()->firstOrCreate(
            ['name' => $name],
            ['fetched_at' => Carbon::now()],
        );
    }

    /**
     * @param  list<int>  $keptCategoryIds
     * @return list<array{mapping_id: int, category_id: int, ananas_category: string, reason: string}>
     */
    private function disableStaleMappings(array $keptCategoryIds): array
    {
        $disabled = [];

        $stale = AnanasCategoryMapping::query()
            ->when($keptCategoryIds !== [], fn ($query) => $query->whereNotIn('category_id', $keptCategoryIds))
            ->get();

        foreach ($stale as $mapping) {
            if (! $mapping instanceof AnanasCategoryMapping) {
                continue;
            }

            $reason = $this->staleReason($mapping);

            if ($reason === null) {
                continue;
            }

            $alreadyDisabled = ! $mapping->is_enabled
                && $mapping->category_validation_status === AnanasCategoryMapping::VALIDATION_FAILED;

            if ($alreadyDisabled) {
                continue;
            }

            $mapping->update([
                'is_enabled' => false,
                'category_validation_status' => AnanasCategoryMapping::VALIDATION_FAILED,
                'category_validation_notes' => $reason,
            ]);

            $disabled[] = [
                'mapping_id' => (int) $mapping->id,
                'category_id' => (int) $mapping->category_id,
                'ananas_category' => (string) ($mapping->ananas_category ?: ''),
                'reason' => $reason,
            ];
        }

        return $disabled;
    }

    private function staleReason(AnanasCategoryMapping $mapping): ?string
    {
        $name = trim((string) $mapping->ananas_category);

        if ($name === '') {
            return 'Prazan Ananas category string — Stage probe nije validirao ovaj čvor (npr. stari „Laptopi“ bez SKU).';
        }

        if (mb_strtolower($name) === 'laptopi') {
            return 'Kandidat „Laptopi“ nije tačan: Stage GET vraća „Gaming laptopi“ za laptop SKU.';
        }

        return null;
    }
}
