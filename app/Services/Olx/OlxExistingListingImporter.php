<?php

namespace App\Services\Olx;

use App\Models\OlxListingRegistry;
use App\Models\Product;

class OlxExistingListingImporter
{
    public function __construct(
        private readonly OlxApiClient $client,
    ) {}

    /**
     * @return array{imported: int, matched: int, pages: int}
     */
    public function import(string $username = null): array
    {
        $username ??= (string) config('bnc.olx_shop_username', 'bnc');
        $page = 1;
        $imported = 0;
        $matched = 0;
        $pages = 0;

        do {
            $response = $this->client->getUserListings($username, $page, 100);
            $pages++;
            $items = collect($response['data'] ?? []);
            $lastPage = (int) ($response['meta']['last_page'] ?? $page);

            foreach ($items as $item) {
                $listingId = (int) ($item['id'] ?? 0);

                if ($listingId <= 0) {
                    continue;
                }

                $detail = $this->client->getListing($listingId) ?? $item;
                $sku = isset($detail['sku_number']) ? (string) $detail['sku_number'] : null;
                $match = $this->matchProduct($sku, (string) ($detail['title'] ?? ''));

                $registry = OlxListingRegistry::query()->updateOrCreate(
                    ['olx_listing_id' => $listingId],
                    [
                        'sku_number' => $sku,
                        'title' => (string) ($detail['title'] ?? $item['title'] ?? ''),
                        'category_id' => isset($detail['category_id']) ? (int) $detail['category_id'] : null,
                        'state' => (string) ($detail['state'] ?? $item['state'] ?? ''),
                        'status' => (string) ($detail['status'] ?? $item['status'] ?? ''),
                        'sync_mode' => OlxListingRegistry::SYNC_MODE_LEGACY,
                        'imported_at' => now(),
                    ],
                );

                if ($match !== null) {
                    /** @var Product $product */
                    $product = $match['product'];
                    $registry->update([
                        'product_id' => $product->id,
                        'match_method' => $match['method'],
                        'matched_at' => now(),
                    ]);

                    $product->update([
                        'olx_listing_id' => (string) $listingId,
                        'olx_listing_status' => (string) ($detail['status'] ?? 'active'),
                        'olx_managed' => false,
                        'olx_synced_at' => now(),
                    ]);

                    $matched++;
                }

                $imported++;
            }

            $page++;
        } while ($page <= $lastPage);

        return [
            'imported' => $imported,
            'matched' => $matched,
            'pages' => $pages,
        ];
    }

    /**
     * @return array{product: Product, method: string}|null
     */
    private function matchProduct(?string $sku, string $title): ?array
    {
        if ($sku !== null && $sku !== '') {
            $bySku = Product::query()->where('sku', $sku)->first();

            if ($bySku !== null) {
                return ['product' => $bySku, 'method' => 'sku'];
            }

            if (ctype_digit($sku)) {
                $byId = Product::query()->find((int) $sku);

                if ($byId !== null) {
                    return ['product' => $byId, 'method' => 'id'];
                }
            }

            $byEline = Product::query()->where('eline_sifra', $sku)->first();

            if ($byEline !== null) {
                return ['product' => $byEline, 'method' => 'eline_sifra'];
            }
        }

        return null;
    }
}
