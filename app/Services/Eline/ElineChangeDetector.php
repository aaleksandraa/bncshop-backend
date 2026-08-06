<?php

namespace App\Services\Eline;

use App\Models\ElineCategoryMapping;
use App\Models\Product;
use Illuminate\Support\Collection;

class ElineChangeDetector
{
    public function __construct(
        private readonly ElineProductImporter $productImporter,
    ) {}

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  Collection<string, ElineCategoryMapping>  $mappingsByCategory
     * @return array{
     *     changed: Collection<int, array<string, mixed>>,
     *     all_feed_sifre: array<int, string>,
     *     scanned: int,
     *     unchanged: int,
     *     new_items: int,
     *     modified_items: int
     * }
     */
    public function detect(Collection $items, Collection $mappingsByCategory): array
    {
        $knownHashes = Product::query()
            ->fromEline()
            ->whereNotNull('eline_sifra')
            ->pluck('eline_feed_hash', 'eline_sifra');

        $knownProducts = Product::query()
            ->fromEline()
            ->whereNotNull('eline_sifra')
            ->get(['id', 'eline_sifra', 'category_id', 'is_refurbished', 'is_new', 'is_public', 'status'])
            ->keyBy('eline_sifra');

        $changed = collect();
        $allFeedSifre = [];
        $unchanged = 0;
        $newItems = 0;
        $modifiedItems = 0;

        foreach ($items as $item) {
            $sifra = (string) ($item['sifra'] ?? '');
            $category = trim((string) ($item['eline_category'] ?? ''));

            if ($sifra === '' || $category === '') {
                continue;
            }

            $mapping = $mappingsByCategory->get($category);

            if ($mapping === null || ! $mapping->is_enabled || $mapping->category_id === null) {
                continue;
            }

            $allFeedSifre[] = $sifra;
            $hash = ElineSupport::feedHash($item);
            $knownHash = $knownHashes->get($sifra);

            if ($knownHash === null) {
                $newItems++;
                $changed->push($item);

                continue;
            }

            if ($knownHash !== $hash) {
                $modifiedItems++;
                $changed->push($item);

                continue;
            }

            /** @var Product|null $existingProduct */
            $existingProduct = $knownProducts->get($sifra);

            if ($existingProduct !== null && $this->productImporter->needsMappingReapply($existingProduct, $item, $mapping)) {
                $modifiedItems++;
                $changed->push($item);

                continue;
            }

            $unchanged++;
        }

        return [
            'changed' => $changed->values(),
            'all_feed_sifre' => $allFeedSifre,
            'scanned' => count($allFeedSifre),
            'unchanged' => $unchanged,
            'new_items' => $newItems,
            'modified_items' => $modifiedItems,
        ];
    }
}
