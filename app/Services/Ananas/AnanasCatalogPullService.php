<?php

namespace App\Services\Ananas;

use App\Models\AnanasProductMapping;
use App\Models\AnanasSyncRun;
use App\Models\Product;

class AnanasCatalogPullService
{
    public function __construct(
        private readonly AnanasApiClient $apiClient,
        private readonly AnanasProductMappingService $mappingService,
        private readonly AnanasEligibilityPolicy $eligibilityPolicy,
        private readonly AnanasExportScope $exportScope,
    ) {}

    /**
     * @return array{
     *   ok: bool,
     *   linked: int,
     *   skipped: int,
     *   errors: list<string>,
     *   rows: list<array<string, mixed>>,
     *   inventory_ids: list<string>,
     *   total_elements: int
     * }
     */
    public function pullFullCatalog(?AnanasSyncRun $run = null, ?AnanasSyncRunService $runService = null): array
    {
        $errors = [];
        $linked = 0;
        $skipped = 0;
        $rows = [];
        $inventoryIds = [];
        $all = [];
        $page = 0;
        $size = 50;
        $totalElements = null;

        while (true) {
            try {
                $payload = $this->apiClient->getProducts(['page' => $page, 'size' => $size]);
            } catch (\Throwable $e) {
                return $this->failPartial($all, $totalElements, $errors + [$e->getMessage()], $linked, $skipped, $rows, $inventoryIds);
            }

            if ($totalElements === null && is_array($payload) && isset($payload['totalElements'])) {
                $totalElements = (int) $payload['totalElements'];
            }

            $items = $this->apiClient->normalizeListPayload(is_array($payload) ? $payload : []);

            if ($items === []) {
                break;
            }

            foreach ($items as $item) {
                if (is_array($item)) {
                    $all[] = $item;
                }
            }

            if ($run !== null && $runService !== null) {
                $runService->heartbeat($runService->refreshCancel($run));
            }

            $page++;

            if ($totalElements !== null && count($all) >= $totalElements) {
                break;
            }

            if ($page > 5000) {
                $errors[] = 'Catalog pagination safety limit reached.';

                break;
            }
        }

        if ($totalElements !== null && count($all) < $totalElements) {
            return [
                'ok' => false,
                'linked' => 0,
                'skipped' => 0,
                'errors' => array_merge($errors, [
                    sprintf('Incomplete catalog GET: fetched %d of %d elements.', count($all), $totalElements),
                ]),
                'rows' => [],
                'inventory_ids' => [],
                'total_elements' => $totalElements,
            ];
        }

        foreach ($all as $remote) {
            $remoteId = isset($remote['id']) ? (string) $remote['id'] : '';

            if ($remoteId !== '') {
                $inventoryIds[] = $remoteId;
            }

            $row = $this->processRemoteRow($remote);
            $rows[] = $row;

            if ($row['action'] === 'linked') {
                $linked++;
            } else {
                $skipped++;
                if ($row['error'] !== null) {
                    $errors[] = $row['error'];
                }
            }
        }

        $this->markRemoteMissing($inventoryIds);

        return [
            'ok' => true,
            'linked' => $linked,
            'skipped' => $skipped,
            'errors' => $errors,
            'rows' => $rows,
            'inventory_ids' => array_values(array_unique($inventoryIds)),
            'remote_rows' => $all,
            'total_elements' => $totalElements ?? count($all),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $all
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    private function failPartial(
        array $all,
        ?int $totalElements,
        array $errors,
        int $linked,
        int $skipped,
        array $rows,
        array $inventoryIds,
    ): array {
        return [
            'ok' => false,
            'linked' => $linked,
            'skipped' => $skipped,
            'errors' => $errors,
            'rows' => $rows,
            'inventory_ids' => $inventoryIds,
            'total_elements' => $totalElements ?? count($all),
        ];
    }

    /**
     * @param  array<string, mixed>  $remote
     * @return array{action: string, inventory_id: string|null, product_id: int|null, error: string|null}
     */
    private function processRemoteRow(array $remote): array
    {
        $remoteId = isset($remote['id']) ? (string) $remote['id'] : '';
        $ean = trim((string) ($remote['ean'] ?? ''));
        $externalId = trim((string) ($remote['externalId'] ?? ''));

        $product = $this->resolveUniqueProduct($ean, $externalId);

        if ($product === null) {
            return [
                'action' => 'skipped',
                'inventory_id' => $remoteId !== '' ? $remoteId : null,
                'product_id' => null,
                'error' => $remoteId !== ''
                    ? sprintf('Inventory %s: no unique BNC match (externalId=%s, ean=%s).', $remoteId, $externalId, $ean)
                    : null,
            ];
        }

        if (! $this->exportScope->isInScopedCategory($product) || ! $this->eligibilityPolicy->evaluate($product)->eligible) {
            return [
                'action' => 'skipped',
                'inventory_id' => $remoteId,
                'product_id' => $product->id,
                'error' => null,
            ];
        }

        $mapping = $this->mappingService->findOrCreate($product);

        if (! $mapping->export_enabled
            || in_array($mapping->local_status, [
                AnanasProductMapping::LOCAL_NOT_ELIGIBLE,
                AnanasProductMapping::LOCAL_DISABLED,
            ], true)) {
            return [
                'action' => 'skipped',
                'inventory_id' => $remoteId,
                'product_id' => $product->id,
                'error' => null,
            ];
        }

        $this->mappingService->applyRemoteProduct($mapping, $remote);
        $mapping->update([
            'remote_missing_at' => null,
            'ean' => $ean !== '' ? $ean : $mapping->ean,
            'external_id' => $externalId !== '' ? $externalId : (string) $product->id,
        ]);

        return [
            'action' => 'linked',
            'inventory_id' => $remoteId,
            'product_id' => $product->id,
            'error' => null,
        ];
    }

    private function resolveUniqueProduct(string $ean, string $externalId): ?Product
    {
        if ($externalId !== '' && ctype_digit($externalId)) {
            $byId = Product::query()->find((int) $externalId);

            if ($byId instanceof Product) {
                return $byId;
            }
        }

        $candidates = AnanasEanLookup::candidateQueryValues($ean);

        if ($candidates === []) {
            return null;
        }

        $matches = Product::query()->whereIn('barcode', $candidates)->get();

        if ($matches->count() === 1) {
            $first = $matches->first();

            return $first instanceof Product ? $first : null;
        }

        return null;
    }

    /**
     * @param  list<string>  $seenInventoryIds
     */
    private function markRemoteMissing(array $seenInventoryIds): void
    {
        $seen = array_flip(array_map('strval', $seenInventoryIds));

        AnanasProductMapping::query()
            ->where('local_status', AnanasProductMapping::LOCAL_LINKED)
            ->whereNotNull('ananas_product_id')
            ->chunkById(200, function ($mappings) use ($seen): void {
                foreach ($mappings as $mapping) {
                    if (! $mapping instanceof AnanasProductMapping) {
                        continue;
                    }

                    $id = (string) ($mapping->merchant_inventory_id ?: $mapping->ananas_product_id);

                    if ($id === '' || isset($seen[$id])) {
                        if ($id !== '' && isset($seen[$id]) && $mapping->remote_missing_at !== null) {
                            $mapping->update(['remote_missing_at' => null]);
                        }

                        continue;
                    }

                    if ($mapping->remote_missing_at === null) {
                        $mapping->update(['remote_missing_at' => now()]);
                    }
                }
            });
    }
}
