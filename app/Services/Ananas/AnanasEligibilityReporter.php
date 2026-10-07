<?php

namespace App\Services\Ananas;

use App\Models\AnanasCategoryMapping;
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
     *     scope: string,
     *     total_scanned: int,
     *     eligible: int,
     *     not_eligible: int,
     *     reasons: array<string, int>,
     *     barcode_shapes: array<string, int>,
     *     samples: array<string, list<array<string, mixed>>>,
     *     mappings: list<array<string, mixed>>
     * }
     */
    /**
     * @param  (callable(int $scanned, int $estimated): void)|null  $onProgress
     */
    public function summarize(int $samplePerReason = 8, bool $includeDisabled = false, ?callable $onProgress = null): array
    {
        $reasons = [];
        $eligible = 0;
        $notEligible = 0;
        $total = 0;
        $barcodeShapes = [];
        $samples = [];
        $mappingStats = [];

        $this->eligibilityPolicy->warmDuplicateEanIndex();

        $query = $this->exportScope->mappedProductQuery(enabledOnly: ! $includeDisabled);
        $estimated = (int) (clone $query)->count();
        if ($onProgress !== null) {
            $onProgress(0, $estimated);
        }

        $query
            ->with(['images', 'attributeValues.attributeDefinition'])
            ->orderBy('id')
            ->chunkById(500, function ($products) use (
                &$reasons,
                &$eligible,
                &$notEligible,
                &$total,
                &$barcodeShapes,
                &$samples,
                &$mappingStats,
                $samplePerReason,
                $includeDisabled,
                $onProgress,
                $estimated,
            ): void {
                foreach ($products as $product) {
                    if (! $product instanceof Product) {
                        continue;
                    }

                    $total++;
                    $shape = $this->barcodeShape($product->barcode);
                    $barcodeShapes[$shape] = ($barcodeShapes[$shape] ?? 0) + 1;

                    $result = $includeDisabled
                        ? $this->eligibilityPolicy->evaluateProductData($product)
                        : $this->eligibilityPolicy->evaluate($product);

                    $mapping = $this->exportScope->resolveMappingForProduct($product, enabledOnly: ! $includeDisabled);
                    $mappingKey = $mapping !== null ? (string) $mapping->id : 'none';

                    if (! isset($mappingStats[$mappingKey])) {
                        $mappingStats[$mappingKey] = $this->emptyMappingStat($mapping);
                    }

                    $mappingStats[$mappingKey]['scanned']++;

                    if ($result->eligible) {
                        $eligible++;
                        $mappingStats[$mappingKey]['eligible']++;

                        continue;
                    }

                    $notEligible++;
                    $code = $result->reasonCode ?? 'NOT_ELIGIBLE';
                    $reasons[$code] = ($reasons[$code] ?? 0) + 1;
                    $mappingStats[$mappingKey]['reasons'][$code] = ($mappingStats[$mappingKey]['reasons'][$code] ?? 0) + 1;

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

                if ($onProgress !== null) {
                    $onProgress($total, $estimated);
                }
            });

        ksort($reasons);
        ksort($barcodeShapes);

        $mappings = array_values($mappingStats);
        usort($mappings, static fn (array $a, array $b): int => $b['scanned'] <=> $a['scanned']);

        foreach ($mappings as &$row) {
            arsort($row['reasons']);
            $top = array_key_first($row['reasons']);
            $row['top_reason'] = $top ?? '—';
            $row['top_reason_count'] = $top !== null ? (int) $row['reasons'][$top] : 0;
        }
        unset($row);

        return [
            'scope' => $includeDisabled ? 'all_mapped' : 'enabled',
            'total_scanned' => $total,
            'eligible' => $eligible,
            'not_eligible' => $notEligible,
            'reasons' => $reasons,
            'barcode_shapes' => $barcodeShapes,
            'samples' => $samples,
            'mappings' => $mappings,
        ];
    }

    /**
     * @return array{mapping_id: int|null, category_id: int|null, bnc_category: string, ananas_category: string, enabled: bool, scanned: int, eligible: int, reasons: array<string, int>, top_reason: string, top_reason_count: int}
     */
    private function emptyMappingStat(?AnanasCategoryMapping $mapping): array
    {
        $category = $mapping?->category;

        return [
            'mapping_id' => $mapping !== null ? (int) $mapping->id : null,
            'category_id' => $mapping !== null ? (int) $mapping->category_id : null,
            'bnc_category' => $category !== null ? (string) $category->publicName() : '—',
            'ananas_category' => $mapping !== null ? (string) ($mapping->ananas_category ?: '—') : '—',
            'enabled' => (bool) ($mapping?->is_enabled),
            'scanned' => 0,
            'eligible' => 0,
            'reasons' => [],
            'top_reason' => '—',
            'top_reason_count' => 0,
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
