<?php

namespace App\Services\Eline;

use App\Models\ElineCategoryMapping;
use App\Models\ElineProductOverride;
use App\Models\Product;
use Illuminate\Support\Collection;

class ElineVisibilityReapplyService
{
    public function __construct(
        private readonly ElineApiClient $apiClient,
        private readonly ElineCategoryDiscoveryService $discoveryService,
        private readonly ElineProductImporter $productImporter,
    ) {}

    /**
     * Fix flags using each product's eLine feed category (correct for new vs refurbished).
     *
     * Does not touch name, price, stock, images, descriptions, slugs, or BNC category.
     *
     * @return array{scanned: int, updated: int, skipped: int, changes: array<int, array<string, mixed>>}
     */
    public function reapplyFromFeed(bool $dryRun = false): array
    {
        $artikli = $this->apiClient->fetchArtikli();
        $cjenovnici = $this->apiClient->fetchCjenovnici();
        $priceMap = $this->apiClient->buildPriceMap($cjenovnici);
        unset($cjenovnici);

        $mappingsByCategory = $this->normalizedMappings(
            $this->discoveryService->enabledMappingsByCategoryName(),
        );

        /** @var array<string, array<string, mixed>> $itemsBySifra */
        $itemsBySifra = [];

        foreach ($this->apiClient->mergeProductDataInChunks($artikli, $priceMap) as $chunk) {
            foreach ($chunk as $item) {
                $sifra = (string) ($item['sifra'] ?? '');

                if ($sifra !== '') {
                    $itemsBySifra[$sifra] = $item;
                }
            }
        }

        unset($artikli, $priceMap);

        $stats = [
            'scanned' => 0,
            'updated' => 0,
            'skipped' => 0,
            'changes' => [],
        ];

        Product::query()
            ->fromEline()
            ->whereNotNull('eline_sifra')
            ->orderBy('id')
            ->chunkById(200, function ($products) use ($itemsBySifra, $mappingsByCategory, $dryRun, &$stats): void {
                foreach ($products as $product) {
                    $stats['scanned']++;

                    /** @var Product $product */
                    $sifra = (string) $product->eline_sifra;
                    $item = $itemsBySifra[$sifra] ?? null;

                    if ($item === null) {
                        $stats['skipped']++;

                        continue;
                    }

                    $elineCategory = trim((string) ($item['eline_category'] ?? ''));
                    $mapping = $mappingsByCategory->get($this->normalizeCategoryKey($elineCategory));

                    if ($mapping === null) {
                        $stats['skipped']++;

                        continue;
                    }

                    $importState = $this->productImporter->resolveImportState($item, $mapping);
                    $updates = $this->buildVisibilityUpdates($product, $importState);

                    if ($updates === []) {
                        $stats['skipped']++;

                        continue;
                    }

                    $stats['changes'][] = [
                        'id' => $product->id,
                        'eline_sifra' => $product->eline_sifra,
                        'eline_category' => $elineCategory,
                        'name' => $product->name,
                        'updates' => $updates,
                    ];

                    if (! $dryRun) {
                        $product->update($updates);
                    }

                    $stats['updated']++;
                }
            });

        return $stats;
    }

    /**
     * Fix storefront visibility flags from current admin BNC category + enabled eLine mappings.
     *
     * Less accurate when "Novi" and "Refurbished" eLine categories map to the same BNC category.
     *
     * @return array{scanned: int, updated: int, skipped: int, changes: array<int, array<string, mixed>>}
     */
    public function reapplyFromDatabase(bool $dryRun = false): array
    {
        /** @var Collection<int, Collection<int, ElineCategoryMapping>> $mappingsByCategoryId */
        $mappingsByCategoryId = ElineCategoryMapping::query()
            ->where('is_enabled', true)
            ->whereNotNull('category_id')
            ->get()
            ->groupBy('category_id');

        $stats = [
            'scanned' => 0,
            'updated' => 0,
            'skipped' => 0,
            'changes' => [],
        ];

        Product::query()
            ->fromEline()
            ->orderBy('id')
            ->chunkById(200, function ($products) use ($mappingsByCategoryId, $dryRun, &$stats): void {
                foreach ($products as $product) {
                    $stats['scanned']++;

                    /** @var Product $product */
                    $mappings = $mappingsByCategoryId->get((int) $product->category_id);

                    if ($mappings === null || $mappings->isEmpty()) {
                        $stats['skipped']++;

                        continue;
                    }

                    $mapping = $this->resolveMapping($mappings, $product);
                    $isRefurbished = $mapping->product_condition === ElineCategoryMapping::CONDITION_REFURBISHED;
                    $isNew = $mapping->product_condition === ElineCategoryMapping::CONDITION_NEW;

                    $updates = $this->buildVisibilityUpdates($product, [
                        'is_refurbished' => $isRefurbished,
                        'is_new' => $isNew,
                        'is_public' => (bool) $product->is_public,
                        'status' => (string) $product->status,
                    ]);

                    if (! $product->is_public && $product->status === 'active' && (int) $product->available_stock > 0) {
                        $updates['is_public'] = true;
                    }

                    if ($updates === []) {
                        $stats['skipped']++;

                        continue;
                    }

                    $stats['changes'][] = [
                        'id' => $product->id,
                        'eline_sifra' => $product->eline_sifra,
                        'name' => $product->name,
                        'updates' => $updates,
                    ];

                    if (! $dryRun) {
                        $product->update($updates);
                    }

                    $stats['updated']++;
                }
            });

        return $stats;
    }

    /**
     * @param  Collection<string, ElineCategoryMapping>  $mappings
     * @return Collection<string, ElineCategoryMapping>
     */
    private function normalizedMappings(Collection $mappings): Collection
    {
        return $mappings->keyBy(
            fn (ElineCategoryMapping $mapping): string => $this->normalizeCategoryKey(
                (string) $mapping->elineCategory?->name,
            ),
        );
    }

    private function normalizeCategoryKey(string $name): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($name));

        return mb_strtolower($normalized ?? trim($name), 'UTF-8');
    }

    /**
     * @param  Collection<int, ElineCategoryMapping>  $mappings
     */
    private function resolveMapping(Collection $mappings, Product $product): ElineCategoryMapping
    {
        if ($mappings->count() === 1) {
            return $mappings->first();
        }

        $refurbished = $mappings->first(
            fn (ElineCategoryMapping $mapping): bool => $mapping->product_condition === ElineCategoryMapping::CONDITION_REFURBISHED,
        );

        if ($refurbished !== null) {
            return $refurbished;
        }

        $override = $product->eline_sifra
            ? ElineProductOverride::query()->where('eline_sifra', $product->eline_sifra)->first()
            : null;

        if ($override?->product_condition !== null) {
            $match = $mappings->first(
                fn (ElineCategoryMapping $mapping): bool => $mapping->product_condition === $override->product_condition,
            );

            if ($match !== null) {
                return $match;
            }
        }

        return $mappings->first();
    }

    /**
     * @param  array{
     *     is_refurbished: bool,
     *     is_new: bool,
     *     is_public: bool,
     *     status: string
     * }  $importState
     * @return array<string, mixed>
     */
    private function buildVisibilityUpdates(Product $product, array $importState): array
    {
        $updates = [];

        if ((bool) $product->is_refurbished !== $importState['is_refurbished']) {
            $updates['is_refurbished'] = $importState['is_refurbished'];
        }

        if ((bool) $product->is_new !== $importState['is_new']) {
            $updates['is_new'] = $importState['is_new'];
        }

        if ((bool) $product->is_public !== $importState['is_public']) {
            $updates['is_public'] = $importState['is_public'];
        }

        if ((string) $product->status !== $importState['status']) {
            $updates['status'] = $importState['status'];
        }

        if ($product->sync_status === 'missing_from_api') {
            $updates['sync_status'] = 'synced';
            $updates['marked_missing_at'] = null;
        }

        return $updates;
    }
}
