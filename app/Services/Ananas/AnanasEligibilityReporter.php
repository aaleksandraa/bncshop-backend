<?php

namespace App\Services\Ananas;

use App\Models\Product;

class AnanasEligibilityReporter
{
    public function __construct(
        private readonly AnanasExportScope $exportScope,
        private readonly AnanasEligibilityPolicy $eligibilityPolicy,
    ) {}

    /**
     * @return array{
     *     total_scanned: int,
     *     eligible: int,
     *     not_eligible: int,
     *     reasons: array<string, int>
     * }
     */
    public function summarize(): array
    {
        $reasons = [];
        $eligible = 0;
        $notEligible = 0;
        $total = 0;

        $this->exportScope->baseQuery()
            ->select(['id', 'category_id', 'barcode', 'is_public', 'status', 'is_refurbished', 'is_set', 'available_stock'])
            ->with(['images', 'attributeValues.attributeDefinition', 'manufacturer'])
            ->orderBy('id')
            ->chunkById(200, function ($products) use (&$reasons, &$eligible, &$notEligible, &$total): void {
                foreach ($products as $product) {
                    if (! $product instanceof Product) {
                        continue;
                    }

                    $total++;
                    $result = $this->eligibilityPolicy->evaluate($product);

                    if ($result->eligible) {
                        $eligible++;

                        continue;
                    }

                    $notEligible++;
                    $code = $result->reasonCode ?? 'NOT_ELIGIBLE';
                    $reasons[$code] = ($reasons[$code] ?? 0) + 1;
                }
            });

        ksort($reasons);

        return [
            'total_scanned' => $total,
            'eligible' => $eligible,
            'not_eligible' => $notEligible,
            'reasons' => $reasons,
        ];
    }
}
