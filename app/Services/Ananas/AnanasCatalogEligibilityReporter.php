<?php

namespace App\Services\Ananas;

use App\Models\Product;

class AnanasCatalogEligibilityReporter
{
    public function __construct(
        private readonly AnanasEligibilityPolicy $eligibilityPolicy,
    ) {}

    /**
     * @return array{
     *   total_products: int,
     *   active_public: int,
     *   eligible: int,
     *   not_eligible: int,
     *   reasons: array<string, int>,
     *   samples: array<string, list<array{product_id: int, barcode: string|null, name: string}>>
     * }
     */
    public function summarize(int $samplePerReason = 3): array
    {
        $totalProducts = Product::query()->count();
        $activePublic = Product::query()->where('is_public', true)->where('status', 'active')->count();

        $reasons = [];
        $eligible = 0;
        $notEligible = 0;
        $samples = [];

        Product::query()
            ->where('is_public', true)
            ->where('status', 'active')
            ->select(['id', 'category_id', 'barcode', 'name', 'is_public', 'status', 'is_refurbished', 'is_set', 'available_stock'])
            ->with(['images', 'attributeValues.attributeDefinition', 'manufacturer'])
            ->orderBy('id')
            ->chunkById(200, function ($products) use (
                &$reasons,
                &$eligible,
                &$notEligible,
                &$samples,
                $samplePerReason,
            ): void {
                foreach ($products as $product) {
                    if (! $product instanceof Product) {
                        continue;
                    }

                    $result = $this->eligibilityPolicy->evaluateProductData($product);

                    if ($result->eligible) {
                        $eligible++;

                        continue;
                    }

                    $notEligible++;
                    $code = $result->reasonCode ?? 'NOT_ELIGIBLE';
                    $reasons[$code] = ($reasons[$code] ?? 0) + 1;

                    if (! isset($samples[$code])) {
                        $samples[$code] = [];
                    }

                    if (count($samples[$code]) < $samplePerReason) {
                        $samples[$code][] = [
                            'product_id' => (int) $product->id,
                            'barcode' => $product->barcode,
                            'name' => (string) $product->name,
                        ];
                    }
                }
            });

        ksort($reasons);

        return [
            'total_products' => $totalProducts,
            'active_public' => $activePublic,
            'eligible' => $eligible,
            'not_eligible' => $notEligible,
            'reasons' => $reasons,
            'samples' => $samples,
        ];
    }
}
