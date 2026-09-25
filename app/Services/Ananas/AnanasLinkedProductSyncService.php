<?php

namespace App\Services\Ananas;

use App\Models\AnanasProductMapping;
use App\Models\Product;
use App\Services\Pricing\PriceCalculator;

class AnanasLinkedProductSyncService
{
    public function __construct(
        private readonly AnanasApiClient $apiClient,
        private readonly AnanasPackageWeightResolver $packageWeightResolver,
        private readonly AnanasEligibilityPolicy $eligibilityPolicy,
        private readonly PriceCalculator $priceCalculator,
    ) {}

    /**
     * @return array{updated: int, skipped: int, errors: list<string>}
     */
    public function syncLinkedStockAndPrice(
        int $limit = 25,
        bool $dryRun = false,
        bool $allowProduction = false,
        array $inventoryIds = [],
        bool $force = false,
    ): array {
        $limit = max(1, min($limit, 2000));
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $payloads = [];
        $wanted = array_values(array_unique(array_filter(array_map('intval', $inventoryIds), static fn (int $id): bool => $id > 0)));

        $query = AnanasProductMapping::query()
            ->where('local_status', AnanasProductMapping::LOCAL_LINKED)
            ->whereNotNull('ananas_product_id')
            ->orderByDesc('last_success_at')
            ->limit($limit)
            ->with(['product.attributeValues.attributeDefinition']);

        if ($wanted !== []) {
            $query->where(function ($inner) use ($wanted): void {
                $inner->whereIn('merchant_inventory_id', array_map('strval', $wanted))
                    ->orWhereIn('ananas_product_id', array_map('strval', $wanted));
            });
        }

        $mappings = $query->get();

        foreach ($mappings as $mapping) {
            if (! $mapping instanceof AnanasProductMapping) {
                continue;
            }

            $product = $mapping->product;

            if ($product === null) {
                $skipped++;

                continue;
            }

            $item = $this->buildBulkUpdateItem($mapping, $product, $force);

            if ($item === null) {
                $skipped++;

                continue;
            }

            $payloads[] = ['mapping' => $mapping, 'item' => $item];
        }

        if ($payloads === []) {
            return compact('updated', 'skipped', 'errors') + ['items' => []];
        }

        $itemsPreview = array_map(static function (array $row): array {
            $item = $row['item'];
            $mapping = $row['mapping'];

            return [
                'id' => $item['id'] ?? null,
                'product_id' => $mapping->product_id,
                'ean' => $mapping->ean,
                'basePrice' => $item['basePrice'] ?? null,
                'stockLevel' => $item['stockLevel'] ?? null,
            ];
        }, $payloads);

        if ($dryRun) {
            return [
                'updated' => count($payloads),
                'skipped' => $skipped,
                'errors' => [],
                'items' => $itemsPreview,
            ];
        }

        $items = array_map(static fn (array $row): array => $row['item'], $payloads);

        try {
            $responses = $this->apiClient->updateProductsBulk($items, $allowProduction);
        } catch (\Throwable $e) {
            return [
                'updated' => 0,
                'skipped' => $skipped,
                'errors' => [$e->getMessage()],
                'items' => $itemsPreview,
            ];
        }

        foreach ($payloads as $index => $row) {
            /** @var AnanasProductMapping $mapping */
            $mapping = $row['mapping'];
            $response = $responses[$index] ?? null;
            $status = is_array($response) ? (string) ($response['status'] ?? '') : '';

            if (strtoupper($status) === 'SUCCESS') {
                $updated++;
                $item = $row['item'];
                $mapping->update([
                    'stock_hash' => hash('sha256', (string) ($item['stockLevel'] ?? 0)),
                    'price_hash' => hash('sha256', (string) ($item['basePrice'] ?? 0)),
                    'last_success_at' => now(),
                ]);

                continue;
            }

            $errors[] = sprintf(
                'Mapping %d product %s: %s',
                $mapping->id,
                $mapping->ananas_product_id,
                is_array($response) ? implode('; ', $response['errors'] ?? ['FAIL']) : 'unknown',
            );
        }

        return compact('updated', 'skipped', 'errors') + ['items' => $itemsPreview];
    }

    /**
     * @param  list<int>  $inventoryIds
     * @return array{published: int, progress_id: string|null, errors: list<string>, inventory_ids: list<int>}
     */
    public function publishReadyLinked(
        int $limit = 25,
        bool $dryRun = false,
        bool $allowProduction = false,
        array $inventoryIds = [],
    ): array {
        return $this->runInventoryJob(
            limit: $limit,
            dryRun: $dryRun,
            allowProduction: $allowProduction,
            remoteStatus: 'READY_FOR_PUBLISH',
            action: 'publish',
            inventoryIds: $inventoryIds,
        );
    }

    /**
     * @param  list<int>  $inventoryIds
     * @return array{published: int, progress_id: string|null, errors: list<string>, inventory_ids: list<int>}
     */
    public function unpublishPublished(
        int $limit = 25,
        bool $dryRun = false,
        bool $allowProduction = false,
        array $inventoryIds = [],
    ): array {
        return $this->runInventoryJob(
            limit: $limit,
            dryRun: $dryRun,
            allowProduction: $allowProduction,
            remoteStatus: 'PUBLISHED',
            action: 'unpublish',
            inventoryIds: $inventoryIds,
        );
    }

    /**
     * @param  list<int>  $inventoryIds
     * @return array{published: int, progress_id: string|null, errors: list<string>, inventory_ids: list<int>}
     */
    private function runInventoryJob(
        int $limit,
        bool $dryRun,
        bool $allowProduction,
        string $remoteStatus,
        string $action,
        array $inventoryIds = [],
    ): array {
        $query = AnanasProductMapping::query()
            ->where('local_status', AnanasProductMapping::LOCAL_LINKED)
            ->where('remote_status', $remoteStatus)
            ->orderByDesc('last_success_at');

        $wanted = array_values(array_unique(array_filter(array_map('intval', $inventoryIds), fn (int $id): bool => $id > 0)));

        if ($wanted !== []) {
            $query->where(function ($inner) use ($wanted): void {
                $inner->whereIn('merchant_inventory_id', $wanted)
                    ->orWhereIn('ananas_product_id', array_map('strval', $wanted));
            });
        }

        $mappings = $query->limit(max(1, $limit))->get();

        $ids = $mappings
            ->map(fn (AnanasProductMapping $mapping): int => $mapping->inventoryId())
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return ['published' => 0, 'progress_id' => null, 'errors' => [], 'inventory_ids' => []];
        }

        if ($dryRun) {
            return ['published' => count($ids), 'progress_id' => null, 'errors' => [], 'inventory_ids' => $ids];
        }

        try {
            $result = $action === 'publish'
                ? $this->apiClient->publishProducts($ids, $allowProduction)
                : $this->apiClient->unpublishProducts($ids, $allowProduction);
        } catch (\Throwable $e) {
            return ['published' => 0, 'progress_id' => null, 'errors' => [$e->getMessage()], 'inventory_ids' => $ids];
        }

        $progressId = $result['progress_id'];

        foreach ($mappings as $mapping) {
            if (! $mapping instanceof AnanasProductMapping) {
                continue;
            }

            $mapping->update([
                'last_progress_id' => $progressId,
                'last_submitted_at' => now(),
            ]);
        }

        return [
            'published' => count($ids),
            'progress_id' => $progressId,
            'errors' => [],
            'inventory_ids' => $ids,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildBulkUpdateItem(AnanasProductMapping $mapping, Product $product, bool $force = false): ?array
    {
        $remoteId = $mapping->inventoryId();

        if ($remoteId <= 0) {
            return null;
        }

        $weight = $this->packageWeightResolver->resolve($product);

        if (! $weight->isOk()) {
            return null;
        }

        $pricing = $this->priceCalculator->calculate($product);
        $vat = $this->eligibilityPolicy->resolvedVatRate();

        if ($vat === null || $pricing->regularPrice <= 0) {
            return null;
        }

        $stockLevel = max(0, (int) $product->available_stock);
        $basePrice = round($pricing->regularPrice, 2);
        $stockHash = hash('sha256', (string) $stockLevel);
        $priceHash = hash('sha256', (string) $basePrice);

        if (! $force && $mapping->stock_hash === $stockHash && $mapping->price_hash === $priceHash) {
            return null;
        }

        return [
            'id' => $remoteId,
            'stockLevel' => $stockLevel,
            'basePrice' => $basePrice,
            'vat' => $vat,
            'packageWeightValue' => $weight->resolvedWeightKg,
            'packageWeightUnit' => 'KG',
            'sku' => filled($product->sku) ? (string) $product->sku : ('BNC-'.$product->id),
            'serviceable' => true,
        ];
    }
}
