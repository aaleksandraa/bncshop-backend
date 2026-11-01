<?php

namespace App\Services\Ananas;

use App\Models\AnanasDiscountAction;
use App\Models\AnanasProductMapping;
use App\Models\AnanasSyncRun;
use App\Models\Product;

class AnanasTakedownService
{
    public function __construct(
        private readonly AnanasApiClient $apiClient,
        private readonly AnanasCatalogWriteGuard $writeGuard,
        private readonly AnanasDiscountService $discountService,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $catalogRows
     * @return array{
     *   updated: int,
     *   skipped: int,
     *   errors: list<string>,
     *   failed_ids: list<string>,
     *   exclude_ids: list<string>
     * }
     */
    public function runTakedown(
        array $catalogRows,
        bool $allowProduction = false,
        ?AnanasSyncRun $run = null,
        ?AnanasSyncRunService $runService = null,
    ): array {
        $targets = $this->collectTakedownTargets($catalogRows);

        if ($targets === []) {
            return [
                'updated' => 0,
                'skipped' => 0,
                'errors' => [],
                'failed_ids' => [],
                'exclude_ids' => [],
            ];
        }

        $this->writeGuard->assertAllowed($allowProduction);

        $updated = 0;
        $skipped = 0;
        $errors = [];
        $failedIds = [];
        $excludeIds = [];

        foreach ($targets as $target) {
            if ($run !== null && $runService !== null && $runService->cancelRequested($runService->refreshCancel($run))) {
                break;
            }

            $inventoryId = (int) $target['inventory_id'];
            $excludeIds[] = (string) $inventoryId;
            $stateHash = (string) $target['state_hash'];

            $mapping = $target['mapping'] ?? null;

            if ($mapping instanceof AnanasProductMapping && $mapping->takedown_state_hash === $stateHash) {
                $skipped++;

                continue;
            }

            try {
                $this->apiClient->updateProductsBulk([
                    ['id' => $inventoryId, 'stockLevel' => 0],
                ], $allowProduction);
            } catch (\Throwable $e) {
                $errors[] = 'Inventory '.$inventoryId.': stock 0 failed — '.$e->getMessage();
                $failedIds[] = (string) $inventoryId;

                continue;
            }

            $remoteStatus = strtoupper((string) ($target['remote_status'] ?? ''));

            if ($remoteStatus === 'PUBLISHED') {
                try {
                    $this->apiClient->unpublishProducts([(string) $inventoryId], $allowProduction);
                } catch (\Throwable $e) {
                    $errors[] = 'Inventory '.$inventoryId.': unpublish failed — '.$e->getMessage();
                }
            }

            $activeSale = AnanasDiscountAction::query()
                ->where('merchant_inventory_id', (string) $inventoryId)
                ->where('local_status', AnanasDiscountAction::STATUS_SCHEDULED)
                ->latest('id')
                ->first();

            if ($activeSale !== null && filled($activeSale->ananas_discount_id)) {
                try {
                    $this->discountService->cancel((string) $activeSale->ananas_discount_id, dryRun: false, allowProduction: $allowProduction);
                } catch (\Throwable $e) {
                    $errors[] = 'Inventory '.$inventoryId.': cancel discount failed — '.$e->getMessage();
                }
            }

            if ($mapping instanceof AnanasProductMapping) {
                $mapping->update([
                    'takedown_state_hash' => $stateHash,
                    'stock_hash' => hash('sha256', '0'),
                    'remote_status' => $remoteStatus === 'PUBLISHED' ? 'UNPUBLISHED' : $mapping->remote_status,
                ]);
            }

            $updated++;

            if ($run !== null && $runService !== null) {
                $runService->heartbeat($run);
            }
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
            'failed_ids' => $failedIds,
            'exclude_ids' => array_values(array_unique($excludeIds)),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $catalogRows
     * @return list<array<string, mixed>>
     */
    private function collectTakedownTargets(array $catalogRows): array
    {
        $targets = [];

        foreach ($catalogRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $inventoryId = (string) ($row['inventory_id'] ?? '');

            if ($inventoryId === '') {
                continue;
            }

            $remoteStatus = (string) ($row['remote_status'] ?? '');
            $externalId = trim((string) ($row['external_id'] ?? ''));
            $productId = isset($row['product_id']) ? (int) $row['product_id'] : null;

            $reason = $this->takedownReason($externalId, $productId, $inventoryId);

            if ($reason === null) {
                continue;
            }

            $mapping = AnanasProductMapping::query()
                ->where(function ($q) use ($inventoryId): void {
                    $q->where('merchant_inventory_id', $inventoryId)
                        ->orWhere('ananas_product_id', $inventoryId);
                })
                ->first();

            $targets[] = [
                'inventory_id' => (int) $inventoryId,
                'remote_status' => $remoteStatus,
                'state_hash' => hash('sha256', $reason.'|'.$remoteStatus),
                'mapping' => $mapping,
            ];
        }

        return $targets;
    }

    /**
     * Build takedown candidates from stored catalog snapshot rows enriched with remote fields.
     *
     * @param  array<string, mixed>  $snapshot
     * @return list<array<string, mixed>>
     */
    public function rowsFromSnapshot(array $snapshot): array
    {
        $remoteRows = $snapshot['remote_rows'] ?? [];

        if (! is_array($remoteRows)) {
            return [];
        }

        $out = [];

        foreach ($remoteRows as $remote) {
            if (! is_array($remote)) {
                continue;
            }

            $inventoryId = isset($remote['id']) ? (string) $remote['id'] : '';
            $externalId = trim((string) ($remote['externalId'] ?? ''));
            $product = null;

            if ($externalId !== '' && ctype_digit($externalId)) {
                $product = Product::query()->find((int) $externalId);
            }

            $out[] = [
                'inventory_id' => $inventoryId,
                'external_id' => $externalId,
                'product_id' => $product?->id,
                'remote_status' => (string) ($remote['status'] ?? ''),
            ];
        }

        return $out;
    }

    private function takedownReason(string $externalId, ?int $productId, string $inventoryId): ?string
    {
        if ($externalId !== '' && ctype_digit($externalId)) {
            $product = Product::query()->find((int) $externalId);

            if ($product === null) {
                return 'deleted_product';
            }

            if ($product->status !== 'active' || ! $product->is_public) {
                return 'hidden_product';
            }

            $mapping = AnanasProductMapping::query()->where('product_id', $product->id)->first();

            if ($mapping !== null && ! $mapping->export_enabled) {
                return 'export_disabled';
            }

            return null;
        }

        $mapping = AnanasProductMapping::query()
            ->where(function ($q) use ($inventoryId): void {
                $q->where('merchant_inventory_id', $inventoryId)
                    ->orWhere('ananas_product_id', $inventoryId);
            })
            ->first();

        if ($mapping !== null && ! $mapping->export_enabled) {
            return 'export_disabled';
        }

        return null;
    }
}
