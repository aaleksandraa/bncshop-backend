<?php

namespace App\Services\Ananas;

use App\Models\Product;

class AnanasMasterEanCatalogService
{
    private const BATCH_SIZE = 25;

    public function __construct(
        private readonly AnanasApiClient $apiClient,
        private readonly AnanasEligibilityPolicy $eligibilityPolicy,
    ) {}

    public function isInMasterCatalog(string $ean): bool
    {
        return $this->apiClient->eanExistsInMasterCatalog($ean);
    }

    /**
     * @return array<string, bool>
     */
    public function checkEans(array $eans): array
    {
        $unique = array_values(array_unique(array_filter(array_map(
            static fn (mixed $ean): ?string => is_string($ean) && trim($ean) !== '' ? trim($ean) : null,
            $eans,
        ))));

        if ($unique === []) {
            return [];
        }

        $result = [];

        foreach (array_chunk($unique, self::BATCH_SIZE) as $chunk) {
            $result = array_merge($result, $this->apiClient->checkEansExist($chunk));
        }

        return $result;
    }

    public function findFirstEligibleProductWithMasterEan(?int $scanLimit = null): ?Product
    {
        $query = Product::query()
            ->where('is_public', true)
            ->where('status', 'active')
            ->whereNotNull('barcode')
            ->where('barcode', '!=', '')
            ->with(['images', 'manufacturer', 'attributeValues.attributeDefinition'])
            ->orderBy('id');

        if ($scanLimit !== null) {
            $query->limit($scanLimit);
        }

        $pendingEans = [];
        $productsByEan = [];

        $query->chunkById(200, function ($products) use (&$pendingEans, &$productsByEan): bool {
            foreach ($products as $product) {
                if (! $product instanceof Product) {
                    continue;
                }

                if (! $this->eligibilityPolicy->evaluateProductData($product)->eligible) {
                    continue;
                }

                $ean = trim((string) $product->barcode);

                if ($ean === '') {
                    continue;
                }

                $pendingEans[] = $ean;
                $productsByEan[$ean] = $product;

                if (count($pendingEans) >= self::BATCH_SIZE) {
                    $match = $this->firstMatchFromBatch($pendingEans, $productsByEan);

                    if ($match !== null) {
                        return false;
                    }

                    $pendingEans = [];
                    $productsByEan = [];
                }
            }

            return true;
        });

        if ($pendingEans !== []) {
            return $this->firstMatchFromBatch($pendingEans, $productsByEan);
        }

        return null;
    }

    /**
     * @param  list<string>  $eans
     * @param  array<string, Product>  $productsByEan
     */
    private function firstMatchFromBatch(array $eans, array $productsByEan): ?Product
    {
        foreach ($this->checkEans($eans) as $ean => $exists) {
            if ($exists && isset($productsByEan[$ean])) {
                return $productsByEan[$ean];
            }
        }

        return null;
    }
}
