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
        private readonly AnanasProductMappingService $mappingService,
    ) {}

    /**
     * @return array{updated: int, skipped: int, errors: list<string>, items?: list<array<string, mixed>>}
     */
    public function syncLinkedStockAndPrice(
        int $limit = 25,
        bool $dryRun = false,
        bool $allowProduction = false,
        array $inventoryIds = [],
        bool $force = false,
        ?int $vatRate = null,
        bool $zeroStock = false,
    ): array {
        $limit = max(1, min($limit, 2000));

        if ($zeroStock) {
            return $this->syncMerchantMissingStock(
                limit: $limit,
                dryRun: $dryRun,
                allowProduction: $allowProduction,
                vatRate: $vatRate,
            );
        }

        $skipped = 0;
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

            $item = $this->buildBulkUpdateItem($mapping, $product, $force, $vatRate);

            if ($item === null) {
                $skipped++;

                continue;
            }

            $payloads[] = ['mapping' => $mapping, 'item' => $item];
        }

        return $this->dispatchBulk($payloads, $dryRun, $allowProduction, $skipped);
    }

    /**
     * PUT stock/price for merchant-list SKUs whose stockLevel is missing or 0 (Ananas "edit in bulk").
     *
     * @return array{updated: int, skipped: int, errors: list<string>, items: list<array<string, mixed>>}
     */
    public function syncMerchantMissingStock(
        int $limit = 25,
        bool $dryRun = false,
        bool $allowProduction = false,
        ?int $vatRate = null,
    ): array {
        $limit = max(1, min($limit, 2000));
        $skipped = 0;
        $errors = [];
        $payloads = [];

        foreach ($this->listMerchantProducts() as $remote) {
            if (count($payloads) >= $limit) {
                break;
            }

            if (! is_array($remote) || ! $this->merchantStockIsMissing($remote)) {
                continue;
            }

            $inventoryId = (int) ($remote['id'] ?? 0);
            $ean = trim((string) ($remote['ean'] ?? ''));
            $externalId = trim((string) ($remote['externalId'] ?? ''));

            $product = $this->findBncProduct($ean, $externalId);

            if ($product === null) {
                $skipped++;
                $errors[] = sprintf(
                    'Merchant %d EAN %s: no matching BNC product.',
                    $inventoryId,
                    $ean !== '' ? $ean : '—',
                );

                continue;
            }

            $mapping = $this->mappingService->findOrCreate($product);
            $mapping->fill([
                'ean' => $ean !== '' ? $ean : $mapping->ean,
                'ananas_product_id' => $inventoryId > 0 ? (string) $inventoryId : $mapping->ananas_product_id,
                'merchant_inventory_id' => $inventoryId > 0 ? (string) $inventoryId : $mapping->merchant_inventory_id,
            ]);
            $mapping->save();
            $product->loadMissing(['attributeValues.attributeDefinition']);

            $item = $this->buildBulkUpdateItem($mapping, $product, force: true, vatRate: $vatRate, requireWeight: false);

            if ($item === null) {
                $skipped++;
                $errors[] = sprintf('BNC #%d (merchant %d): cannot build stock PUT (price/VAT).', $product->id, $inventoryId);

                continue;
            }

            if ((int) ($item['stockLevel'] ?? 0) <= 0) {
                $errors[] = sprintf('BNC #%d (merchant %d): BNC available_stock is also 0 — PUT will keep zero.', $product->id, $inventoryId);
            }

            $payloads[] = ['mapping' => $mapping->fresh() ?? $mapping, 'item' => $item];
        }

        return $this->dispatchBulk($payloads, $dryRun, $allowProduction, $skipped, $errors);
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
     * @param  list<array{mapping: AnanasProductMapping, item: array<string, mixed>}>  $payloads
     * @param  list<string>  $errors
     * @return array{updated: int, skipped: int, errors: list<string>, items: list<array<string, mixed>>}
     */
    private function dispatchBulk(
        array $payloads,
        bool $dryRun,
        bool $allowProduction,
        int $skipped,
        array $errors = [],
    ): array {
        $itemsPreview = array_map(static function (array $row): array {
            $item = $row['item'];
            $mapping = $row['mapping'];

            return [
                'id' => $item['id'] ?? null,
                'product_id' => $mapping->product_id,
                'ean' => $mapping->ean,
                'basePrice' => $item['basePrice'] ?? null,
                'stockLevel' => $item['stockLevel'] ?? null,
                'vat' => $item['vat'] ?? null,
            ];
        }, $payloads);

        if ($payloads === []) {
            return [
                'updated' => 0,
                'skipped' => $skipped,
                'errors' => $errors,
                'items' => [],
            ];
        }

        if ($dryRun) {
            return [
                'updated' => count($payloads),
                'skipped' => $skipped,
                'errors' => $errors,
                'items' => $itemsPreview,
            ];
        }

        $items = array_map(static fn (array $row): array => $row['item'], $payloads);

        try {
            $responses = $this->apiClient->updateProductsBulk($items, $allowProduction);
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();

            return [
                'updated' => 0,
                'skipped' => $skipped,
                'errors' => $errors,
                'items' => $itemsPreview,
            ];
        }

        $updated = 0;

        foreach ($payloads as $index => $row) {
            /** @var AnanasProductMapping $mapping */
            $mapping = $row['mapping'];
            $response = $responses[$index] ?? null;
            $status = is_array($response) ? (string) ($response['status'] ?? '') : '';

            if (strtoupper($status) === 'SUCCESS') {
                $updated++;
                $item = $row['item'];
                $mapping->update([
                    'local_status' => AnanasProductMapping::LOCAL_LINKED,
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
     * @return list<array<string, mixed>>
     */
    private function listMerchantProducts(): array
    {
        $all = [];

        for ($page = 0; $page < 20; $page++) {
            $payload = $this->apiClient->getProducts(['page' => $page, 'size' => 50]);
            $items = $this->apiClient->normalizeListPayload(is_array($payload) ? $payload : []);

            if ($items === []) {
                break;
            }

            foreach ($items as $item) {
                if (is_array($item)) {
                    $all[] = $item;
                }
            }

            $total = is_array($payload) && isset($payload['totalElements'])
                ? (int) $payload['totalElements']
                : count($all);

            if (count($all) >= $total) {
                break;
            }
        }

        return $all;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function merchantStockIsMissing(array $row): bool
    {
        foreach (['stockLevel', 'quantity', 'stock'] as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
                continue;
            }

            return (int) $row[$key] <= 0;
        }

        return true;
    }

    private function findBncProduct(string $ean, string $externalId): ?Product
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

        return Product::query()->whereIn('barcode', $candidates)->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildBulkUpdateItem(
        AnanasProductMapping $mapping,
        Product $product,
        bool $force = false,
        ?int $vatRate = null,
        bool $requireWeight = true,
    ): ?array {
        $remoteId = $mapping->inventoryId();

        if ($remoteId <= 0) {
            return null;
        }

        $weight = $this->packageWeightResolver->resolve($product);

        if ($requireWeight && ! $weight->isOk()) {
            return null;
        }

        $pricing = $this->priceCalculator->calculate($product);
        $vat = $vatRate !== null
            ? $this->eligibilityPolicy->normalizeVatRate($vatRate)
            : $this->eligibilityPolicy->resolvedVatRate();

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

        $item = [
            'id' => $remoteId,
            'stockLevel' => $stockLevel,
            'basePrice' => $basePrice,
            'vat' => $vat,
            'sku' => filled($product->sku) ? (string) $product->sku : ('BNC-'.$product->id),
            'serviceable' => true,
        ];

        if ($weight->isOk()) {
            $item['packageWeightValue'] = $weight->resolvedWeightKg;
            $item['packageWeightUnit'] = 'KG';
        }

        return $item;
    }
}
