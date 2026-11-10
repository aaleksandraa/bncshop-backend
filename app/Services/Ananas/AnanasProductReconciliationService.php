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
     * Ananas GET /products allows 200 items per request and 60 requests per minute.
     * One reconcile reads that list in pages and matches locally, instead of one GET per EAN.
     */
    private const PAGE_SIZE = 200;

    /**
     * @return array{linked: int, pending: int, failed: int, details: list<string>, pages: int, remote_count: int}
     */
    public function reconcileSubmittedMappings(int $limit = 50, ?string $ean = null, bool $includeLinked = false): array
    {
        $statuses = [
            AnanasProductMapping::LOCAL_SUBMITTED,
            AnanasProductMapping::LOCAL_PENDING_ONBOARDING,
        ];

        if ($includeLinked || ($ean !== null && trim($ean) !== '')) {
            $statuses[] = AnanasProductMapping::LOCAL_LINKED;
        }

        $query = AnanasProductMapping::query()
            ->forCurrentRemoteEnvironment()
            ->whereIn('local_status', $statuses)
            ->whereNotNull('ean')
            ->orderByDesc('last_submitted_at')
            ->limit(max(1, $limit));

        if ($ean !== null && trim($ean) !== '') {
            $query->where('ean', trim($ean));
        }

        $mappings = $query->get()->filter(fn ($mapping): bool => $mapping instanceof AnanasProductMapping)->values();

        if ($ean !== null && trim($ean) !== '') {
            return $this->finishCounts($this->reconcileOneByOne($mappings));
        }

        $catalog = $this->fetchMerchantCatalog();
        $index = $this->indexRemoteRows($catalog['rows']);
        $rows = [];

        foreach ($mappings as $mapping) {
            $remote = $this->findIndexedRemote($mapping, $index);
            $rows[] = $remote === null
                ? $this->pendingMapping($mapping)
                : $this->linkMapping($mapping, $remote);
        }

        $result = $this->finishCounts($rows);
        $result['pages'] = $catalog['pages'];
        $result['remote_count'] = count($catalog['rows']);

        return $result;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AnanasProductMapping>  $mappings
     * @return list<array{status: string, message: string}>
     */
    private function reconcileOneByOne($mappings): array
    {
        $rows = [];

        foreach ($mappings as $mapping) {
            $rows[] = $this->reconcileMapping($mapping);
        }

        return $rows;
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
            return $this->pendingMapping($mapping);
        }

        return $this->linkMapping($mapping, $remote);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, pages: int}
     */
    private function fetchMerchantCatalog(): array
    {
        $rows = [];
        $pages = 0;
        $total = null;

        for ($page = 0; $page < 500; $page++) {
            $payload = $this->getProductPage($page, self::PAGE_SIZE);
            $pages++;

            if ($total === null && is_array($payload) && isset($payload['totalElements'])) {
                $total = (int) $payload['totalElements'];
            }

            $items = $this->apiClient->normalizeListPayload(is_array($payload) ? $payload : []);

            if ($items === []) {
                break;
            }

            foreach ($items as $item) {
                $rows[] = $item;
            }

            if ($total !== null && count($rows) >= $total) {
                break;
            }

            if (count($items) < self::PAGE_SIZE) {
                break;
            }
        }

        return ['rows' => $rows, 'pages' => $pages];
    }

    /**
     * @return array<string, mixed>|array<int, mixed>
     */
    private function getProductPage(int $page, int $size): array
    {
        $attempt = 0;

        while (true) {
            try {
                return $this->apiClient->getProducts(['page' => $page, 'size' => $size]);
            } catch (\Throwable $e) {
                if ($attempt >= 2 || ! str_contains($e->getMessage(), 'HTTP 429')) {
                    throw $e;
                }

                $attempt++;
                sleep(max(1, 61 - (int) now()->second));
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{ean: array<string, array<string, mixed>>, external: array<string, array<string, mixed>>}
     */
    private function indexRemoteRows(array $rows): array
    {
        $byEan = [];
        $byExternal = [];

        foreach ($rows as $row) {
            $ean = trim((string) ($row['ean'] ?? ''));

            foreach (AnanasEanLookup::candidateQueryValues($ean) as $candidate) {
                $byEan[$candidate] ??= $row;
                $normalized = ltrim($candidate, '0');

                if ($normalized !== '') {
                    $byEan[$normalized] ??= $row;
                }
            }

            $externalId = trim((string) ($row['externalId'] ?? ''));

            if ($externalId !== '') {
                $byExternal[$externalId] ??= $row;
            }
        }

        return ['ean' => $byEan, 'external' => $byExternal];
    }

    /**
     * @param  array{ean: array<string, array<string, mixed>>, external: array<string, array<string, mixed>>}  $index
     * @return array<string, mixed>|null
     */
    private function findIndexedRemote(AnanasProductMapping $mapping, array $index): ?array
    {
        foreach (AnanasEanLookup::candidateQueryValues((string) $mapping->ean) as $candidate) {
            if (isset($index['ean'][$candidate])) {
                return $index['ean'][$candidate];
            }

            $normalized = ltrim($candidate, '0');

            if ($normalized !== '' && isset($index['ean'][$normalized])) {
                return $index['ean'][$normalized];
            }
        }

        $externalId = trim((string) ($mapping->external_id ?: $mapping->product_id));

        return $externalId !== '' ? ($index['external'][$externalId] ?? null) : null;
    }

    /**
     * @param  array<string, mixed>  $remote
     * @return array{status: string, message: string}
     */
    private function linkMapping(AnanasProductMapping $mapping, array $remote): array
    {
        $this->mappingService->applyRemoteProduct($mapping, $remote);
        $this->refreshCategoryValidationFromRemote($mapping, $remote);

        $remoteId = isset($remote['id']) ? (string) $remote['id'] : 'unknown';

        return [
            'status' => 'linked',
            'message' => "Mapping {$mapping->id} (EAN {$mapping->ean}) linked to remote id {$remoteId}.",
        ];
    }

    /**
     * @return array{status: string, message: string}
     */
    private function pendingMapping(AnanasProductMapping $mapping): array
    {
        return [
            'status' => 'pending',
            'message' => "Mapping {$mapping->id} (EAN {$mapping->ean}): not yet visible via GET /products.",
        ];
    }

    /**
     * @param  list<array{status: string, message: string}>  $rows
     * @return array{linked: int, pending: int, failed: int, details: list<string>, pages: int, remote_count: int}
     */
    private function finishCounts(array $rows): array
    {
        $linked = 0;
        $pending = 0;
        $failed = 0;
        $details = [];

        foreach ($rows as $row) {
            $details[] = $row['message'];

            match ($row['status']) {
                'linked' => $linked++,
                'failed' => $failed++,
                default => $pending++,
            };
        }

        return [
            'linked' => $linked,
            'pending' => $pending,
            'failed' => $failed,
            'details' => $details,
            'pages' => 0,
            'remote_count' => 0,
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
