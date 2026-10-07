<?php

namespace App\Services\Ananas;

use App\Models\Product;

class AnanasEligibilityReporter
{
    public function __construct(
        private readonly AnanasExportScope $exportScope,
        private readonly AnanasEligibilityPolicy $eligibilityPolicy,
        private readonly AnanasPackageWeightResolver $packageWeightResolver,
    ) {}

    /**
     * @return array{
     *     total_scanned: int,
     *     eligible: int,
     *     not_eligible: int,
     *     reasons: array<string, int>,
     *     barcode_shapes: array<string, int>,
     *     samples: array<string, list<array<string, mixed>>>
     * }
     */
    public function summarize(int $samplePerReason = 8): array
    {
        $reasons = [];
        $eligible = 0;
        $notEligible = 0;
        $total = 0;
        $barcodeShapes = [];
        $samples = [];

        $this->exportScope->baseQuery()
            ->with(['images', 'attributeValues.attributeDefinition', 'manufacturer'])
            ->orderBy('id')
            ->chunkById(200, function ($products) use (
                &$reasons,
                &$eligible,
                &$notEligible,
                &$total,
                &$barcodeShapes,
                &$samples,
                $samplePerReason,
            ): void {
                foreach ($products as $product) {
                    if (! $product instanceof Product) {
                        continue;
                    }

                    $total++;
                    $shape = $this->barcodeShape($product->barcode);
                    $barcodeShapes[$shape] = ($barcodeShapes[$shape] ?? 0) + 1;

                    $result = $this->eligibilityPolicy->evaluate($product);

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
                        $weight = $this->packageWeightResolver->resolve($product);
                        $samples[$code][] = [
                            'product_id' => (int) $product->id,
                            'name' => mb_substr((string) $product->name, 0, 60),
                            'barcode' => $product->barcode,
                            'resolved_ean' => $this->eligibilityPolicy->resolveEan($product),
                            'weight_status' => $weight->parseStatus,
                            'weight_attr' => $weight->sourceAttributeName,
                            'weight_raw' => mb_substr((string) ($weight->rawValue ?? ''), 0, 40),
                        ];
                    }
                }
            });

        ksort($reasons);
        ksort($barcodeShapes);

        return [
            'total_scanned' => $total,
            'eligible' => $eligible,
            'not_eligible' => $notEligible,
            'reasons' => $reasons,
            'barcode_shapes' => $barcodeShapes,
            'samples' => $samples,
        ];
    }

    private function barcodeShape(?string $barcode): string
    {
        $raw = trim((string) $barcode);

        if ($raw === '') {
            return 'empty';
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === $raw && strlen($raw) === 13) {
            return 'ean13';
        }

        if ($digits === $raw && strlen($raw) === 8) {
            return 'ean8';
        }

        if ($digits === $raw && strlen($raw) === 12) {
            return 'upc12';
        }

        if ($digits !== '' && strlen($digits) === 13 && $raw !== $digits) {
            return 'ean13_with_separators';
        }

        if (preg_match('/[A-Za-z]/', $raw) === 1) {
            return 'contains_letters (likely SKU)';
        }

        return 'other_len_'.strlen($raw).'_digits_'.strlen($digits);
    }
}
