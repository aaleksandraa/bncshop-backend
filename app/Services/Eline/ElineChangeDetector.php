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
        $knownProducts = $this->productsBySifra();

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
            $existingProduct = $this->existingProduct($knownProducts, $sifra);

            if ($existingProduct === null) {
                $newItems++;
                $changed->push($item);

                continue;
            }

            if ($existingProduct->is_set) {
                $unchanged++;

                continue;
            }

            // A row can already own this sifra after an admin save flipped it
            // off eLine. Keep importing it until the source is eLine again,
            // even when the stored hash still matches the feed.
            $needsRelink = $existingProduct->import_source !== 'eline';
            $knownHash = $existingProduct->eline_feed_hash;

            if ($needsRelink || $knownHash === null || $knownHash !== $hash) {
                $modifiedItems++;
                $changed->push($item);

                continue;
            }

            if ($this->productImporter->needsMappingReapply($existingProduct, $item, $mapping)) {
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

    /**
     * @return Collection<string, Product>
     */
    private function productsBySifra(): Collection
    {
        $products = Product::query()
            ->whereNotNull('eline_sifra')
            ->get([
                'id',
                'eline_sifra',
                'eline_feed_hash',
                'external_product_id',
                'import_source',
                'category_id',
                'is_refurbished',
                'is_new',
                'is_public',
                'status',
                'is_set',
            ]);

        $indexed = collect();

        foreach ($products as $product) {
            $indexed->put((string) $product->eline_sifra, $product);
        }

        foreach ($products as $product) {
            foreach (ElineSupport::sifraKeys((string) $product->eline_sifra) as $key) {
                if (! $indexed->has($key)) {
                    $indexed->put($key, $product);
                }
            }
        }

        return $indexed;
    }

    /**
     * @param  Collection<string, Product>  $knownProducts
     */
    private function existingProduct(Collection $knownProducts, string $sifra): ?Product
    {
        foreach (ElineSupport::sifraKeys($sifra) as $key) {
            $product = $knownProducts->get($key);

            if ($product instanceof Product) {
                return $product;
            }
        }

        return null;
    }
}
