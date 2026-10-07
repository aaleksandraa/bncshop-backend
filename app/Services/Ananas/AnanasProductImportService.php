<?php

namespace App\Services\Ananas;

use App\Models\AnanasProductMapping;
use App\Models\Product;
use RuntimeException;

class AnanasProductImportService
{
    public function __construct(
        private readonly AnanasApiClient $apiClient,
        private readonly AnanasCatalogWriteGuard $writeGuard,
        private readonly AnanasExportScope $exportScope,
        private readonly AnanasEligibilityPolicy $eligibilityPolicy,
        private readonly AnanasProductMapper $productMapper,
        private readonly AnanasProductMappingService $mappingService,
        private readonly AnanasMasterEanCatalogService $masterEanCatalog,
    ) {}

    /**
     * @return array{
     *   submitted: int,
     *   skipped: int,
     *   scanned: int,
     *   progress_id: string|null,
     *   product_ids: list<int>,
     *   errors: list<string>,
     *   skip_reasons: array<string, int>
     * }
     */
    public function importBatch(
        int $limit = 10,
        ?int $productId = null,
        bool $allowProduction = false,
        bool $dryRun = false,
    ): array {
        $this->raiseMemoryLimit();
        $this->eligibilityPolicy->warmDuplicateEanIndex();

        $limit = max(1, min($limit, (int) config('bnc.ananas_import_batch_max_size', 25)));
        $maxScan = max($limit, (int) config('bnc.ananas_import_scan_max', 2000));
        $persistSkips = $productId !== null;

        $payloads = [];
        $productIds = [];
        $skipped = 0;
        $scanned = 0;
        $errors = [];
        $skipReasons = [];

        foreach ($this->nextCandidates($productId) as $product) {
            if (! $product instanceof Product) {
                continue;
            }

            if ($scanned >= $maxScan || count($payloads) >= $limit) {
                break;
            }

            $scanned++;
            $this->considerProduct(
                $product,
                $dryRun,
                $productId !== null,
                $persistSkips,
                $payloads,
                $productIds,
                $skipped,
                $skipReasons,
                $errors,
            );
            unset($product);
        }

        if ($payloads === []) {
            return $this->result(0, $skipped, $scanned, null, [], $errors, $skipReasons);
        }

        if ($dryRun) {
            return $this->result(count($payloads), $skipped, $scanned, null, $productIds, $errors, $skipReasons);
        }

        $this->writeGuard->assertAllowed($allowProduction);

        $import = $this->apiClient->importProducts($payloads, $allowProduction);
        $progressId = $import['progress_id'];

        $eanExistence = $this->masterEanCatalog->checkEans(array_map(
            static fn (array $payload): string => (string) ($payload['ean'] ?? ''),
            $payloads,
        ));

        foreach ($productIds as $index => $id) {
            $payload = $payloads[$index] ?? null;

            if (! is_array($payload)) {
                continue;
            }

            $product = new Product;
            $product->id = $id;
            $product->exists = true;

            $ean = trim((string) ($payload['ean'] ?? ''));
            $eanInMaster = $ean !== '' ? ($eanExistence[$ean] ?? null) : null;
            $this->mappingService->recordSubmission($product, $payload, $progressId, $eanInMaster);
            unset($payloads[$index]);
        }

        return $this->result(count($productIds), $skipped, $scanned, $progressId, $productIds, $errors, $skipReasons);
    }

    /**
     * @return iterable<int, Product>
     */
    private function nextCandidates(?int $productId): iterable
    {
        if ($productId !== null) {
            $product = Product::query()
                ->with(['images', 'manufacturer', 'attributeValues.attributeDefinition'])
                ->find($productId);

            if ($product === null) {
                throw new RuntimeException("Product {$productId} not found.");
            }

            yield $product;

            return;
        }

        foreach ($this->exportScope->baseQuery()
            ->with(['images', 'manufacturer', 'attributeValues.attributeDefinition', 'ananasProductMapping'])
            ->whereDoesntHave('ananasProductMapping', function ($query): void {
                $query->whereIn('local_status', AnanasProductMapping::inFlightStatuses());
            })
            ->lazyById(25) as $product) {
            yield $product;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $payloads
     * @param  list<int>  $productIds
     * @param  array<string, int>  $skipReasons
     * @param  list<string>  $errors
     */
    private function considerProduct(
        Product $product,
        bool $dryRun,
        bool $allowReimport,
        bool $persistSkips,
        array &$payloads,
        array &$productIds,
        int &$skipped,
        array &$skipReasons,
        array &$errors,
    ): void {
        if (! $allowReimport && $this->isAlreadyExported($product)) {
            $this->recordSkip(
                $product,
                AnanasEligibilityPolicy::ALREADY_EXPORTED,
                $dryRun,
                $skipped,
                $skipReasons,
                persist: false,
            );

            return;
        }

        $mapping = $this->exportScope->resolveCategoryMapping($product);

        if ($mapping === null) {
            $this->recordSkip($product, AnanasEligibilityPolicy::CATEGORY_UNMAPPED, $dryRun, $skipped, $skipReasons, persist: $persistSkips);

            return;
        }

        $eligibility = $this->eligibilityPolicy->evaluate($product);

        if (! $eligibility->eligible) {
            $this->recordSkip(
                $product,
                (string) ($eligibility->reasonCode ?? 'NOT_ELIGIBLE'),
                $dryRun,
                $skipped,
                $skipReasons,
                persist: $persistSkips,
            );

            return;
        }

        try {
            $payloads[] = $this->productMapper->map($product, $mapping);
            $productIds[] = (int) $product->id;
        } catch (\Throwable $e) {
            $skipped++;
            $skipReasons['MAPPER_ERROR'] = ($skipReasons['MAPPER_ERROR'] ?? 0) + 1;
            $errors[] = sprintf('Product %d: %s', $product->id, $e->getMessage());

            if (! $dryRun) {
                $this->mappingService->markFailed(
                    $this->mappingService->findOrCreate($product),
                    $e->getMessage(),
                );
            }
        }
    }

    /**
     * @param  array<string, int>  $skipReasons
     */
    private function recordSkip(
        Product $product,
        string $reason,
        bool $dryRun,
        int &$skipped,
        array &$skipReasons,
        bool $persist = true,
    ): void {
        $skipped++;
        $skipReasons[$reason] = ($skipReasons[$reason] ?? 0) + 1;

        if (! $dryRun && $persist) {
            $this->mappingService->markNotEligible($product, $reason);
        }
    }

    private function isAlreadyExported(Product $product): bool
    {
        $mapping = $product->relationLoaded('ananasProductMapping')
            ? $product->ananasProductMapping
            : $product->ananasProductMapping()->first();

        if (! $mapping instanceof AnanasProductMapping) {
            return false;
        }

        return in_array((string) $mapping->local_status, AnanasProductMapping::inFlightStatuses(), true);
    }

    /**
     * @param  list<int>  $productIds
     * @param  list<string>  $errors
     * @param  array<string, int>  $skipReasons
     * @return array{
     *   submitted: int,
     *   skipped: int,
     *   scanned: int,
     *   progress_id: string|null,
     *   product_ids: list<int>,
     *   errors: list<string>,
     *   skip_reasons: array<string, int>
     * }
     */
    private function result(
        int $submitted,
        int $skipped,
        int $scanned,
        ?string $progressId,
        array $productIds,
        array $errors,
        array $skipReasons,
    ): array {
        ksort($skipReasons);

        return [
            'submitted' => $submitted,
            'skipped' => $skipped,
            'scanned' => $scanned,
            'progress_id' => $progressId,
            'product_ids' => $productIds,
            'errors' => $errors,
            'skip_reasons' => $skipReasons,
        ];
    }

    private function raiseMemoryLimit(): void
    {
        $current = strtoupper(trim((string) ini_get('memory_limit')));

        if ($current === '-1') {
            return;
        }

        $bytes = 128 * 1024 * 1024;

        if (preg_match('/^(\d+)\s*([KMG])B?$/', $current, $matches) === 1) {
            $bytes = (int) $matches[1] * match ($matches[2]) {
                'K' => 1024,
                'M' => 1024 * 1024,
                'G' => 1024 * 1024 * 1024,
                default => 1,
            };
        }

        if ($bytes < 512 * 1024 * 1024) {
            ini_set('memory_limit', '512M');
        }
    }
}
