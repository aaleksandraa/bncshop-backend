<?php

namespace App\Services\Ananas;

use App\Models\AnanasCategoryMapping;
use App\Models\AnanasCategoryProbe;
use App\Models\AnanasProductMapping;
use App\Models\Product;

class AnanasProductReconciliationService
{
    public function __construct(
        private readonly AnanasApiClient $apiClient,
        private readonly AnanasProductMappingService $mappingService,
        private readonly AnanasCategoryProbeService $categoryProbeService,
    ) {}

    /**
     * @return array{linked: int, pending: int, failed: int, details: list<string>}
     */
    public function reconcileSubmittedMappings(int $limit = 50, ?string $ean = null): array
    {
        $query = AnanasProductMapping::query()
            ->whereIn('local_status', [
                AnanasProductMapping::LOCAL_SUBMITTED,
                AnanasProductMapping::LOCAL_PENDING_ONBOARDING,
                AnanasProductMapping::LOCAL_LINKED,
            ])
            ->whereNotNull('ean')
            ->orderByDesc('last_submitted_at')
            ->limit(max(1, $limit));

        if ($ean !== null && trim($ean) !== '') {
            $query->where('ean', trim($ean));
        }

        $linked = 0;
        $pending = 0;
        $failed = 0;
        $details = [];

        foreach ($query->get() as $mapping) {
            if (! $mapping instanceof AnanasProductMapping) {
                continue;
            }

            $result = $this->reconcileMapping($mapping);
            $details[] = $result['message'];

            match ($result['status']) {
                'linked' => $linked++,
                'failed' => $failed++,
                default => $pending++,
            };
        }

        return compact('linked', 'pending', 'failed', 'details');
    }

    /**
     * @return array{status: string, message: string}
     */
    public function reconcileMapping(AnanasProductMapping $mapping): array
    {
        $ean = trim((string) $mapping->ean);

        if ($ean === '') {
            $this->mappingService->markFailed($mapping, 'Missing EAN on local mapping.');

            return [
                'status' => 'failed',
                'message' => "Mapping {$mapping->id}: missing EAN.",
            ];
        }

        $remote = $this->apiClient->findProductByEan($ean);

        if ($remote === null) {
            return [
                'status' => 'pending',
                'message' => "Mapping {$mapping->id} (EAN {$ean}): not yet visible via GET /products.",
            ];
        }

        $this->mappingService->applyRemoteProduct($mapping, $remote);

        $this->refreshCategoryValidationFromRemote($mapping, $remote);

        $remoteId = isset($remote['id']) ? (string) $remote['id'] : 'unknown';

        return [
            'status' => 'linked',
            'message' => "Mapping {$mapping->id} (EAN {$ean}) linked to remote id {$remoteId}.",
        ];
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function refreshCategoryValidationFromRemote(AnanasProductMapping $mapping, array $remote): void
    {
        $product = Product::query()->find($mapping->product_id);

        if ($product === null) {
            return;
        }

        $categoryMapping = app(AnanasExportScope::class)->resolveCategoryMapping($product);

        if (! $categoryMapping instanceof AnanasCategoryMapping) {
            return;
        }

        $candidate = trim((string) $categoryMapping->ananas_category);

        if ($candidate === '') {
            return;
        }

        $observed = $this->categoryProbeService->extractCategories($remote);
        $matches = $this->categoryProbeService->categoryMatches($candidate, $observed);

        $categoryMapping->update([
            'observed_categories' => $observed,
            'category_validated_at' => now(),
            'category_validation_status' => $matches
                ? AnanasCategoryMapping::VALIDATION_VALIDATED
                : AnanasCategoryMapping::VALIDATION_FAILED,
            'category_validation_notes' => $matches
                ? 'Validated during product reconciliation.'
                : sprintf('Observed categories [%s] do not contain candidate "%s".', implode(', ', $observed), $candidate),
        ]);

        $latestProbe = AnanasCategoryProbe::query()
            ->where('category_mapping_id', $categoryMapping->id)
            ->latest('id')
            ->first();

        if ($latestProbe instanceof AnanasCategoryProbe && $latestProbe->status === AnanasCategoryProbe::STATUS_PENDING) {
            $latestProbe->update([
                'status' => $matches ? AnanasCategoryProbe::STATUS_VALIDATED : AnanasCategoryProbe::STATUS_FAILED,
                'observed_categories' => $observed,
                'observed_product_type' => isset($remote['productType']) ? (string) $remote['productType'] : null,
                'remote_product_id' => isset($remote['id']) ? (string) $remote['id'] : null,
                'error' => $matches ? null : $categoryMapping->category_validation_notes,
            ]);
        }
    }
}
