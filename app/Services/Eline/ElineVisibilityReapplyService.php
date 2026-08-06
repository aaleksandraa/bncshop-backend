<?php

namespace App\Services\Eline;

use App\Models\ElineCategoryMapping;
use App\Models\ElineProductOverride;
use App\Models\Product;
use Illuminate\Support\Collection;

class ElineVisibilityReapplyService
{
    /**
     * Fix storefront visibility flags from current admin category + enabled eLine mappings.
     *
     * Does not touch name, price, stock, images, descriptions, or slugs.
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
                    $updates = $this->buildUpdates($product, $mapping);

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
     * @param  Collection<int, ElineCategoryMapping>  $mappings
     */
    private function resolveMapping(Collection $mappings, Product $product): ElineCategoryMapping
    {
        if ($mappings->count() === 1) {
            return $mappings->first();
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
     * @return array<string, mixed>
     */
    private function buildUpdates(Product $product, ElineCategoryMapping $mapping): array
    {
        $updates = [];

        $isRefurbished = $mapping->product_condition === ElineCategoryMapping::CONDITION_REFURBISHED;
        $isNew = $mapping->product_condition === ElineCategoryMapping::CONDITION_NEW;

        if ((bool) $product->is_refurbished !== $isRefurbished) {
            $updates['is_refurbished'] = $isRefurbished;
        }

        if ((bool) $product->is_new !== $isNew) {
            $updates['is_new'] = $isNew;
        }

        if (! $product->is_public && $product->status === 'active' && (int) $product->available_stock > 0) {
            $updates['is_public'] = true;
        }

        if ($product->sync_status === 'missing_from_api') {
            $updates['sync_status'] = 'synced';
            $updates['marked_missing_at'] = null;
        }

        return $updates;
    }
}
