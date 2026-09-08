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
    ) {}

    /**
     * @return array{
     *   submitted: int,
     *   skipped: int,
     *   progress_id: string|null,
     *   product_ids: list<int>,
     *   errors: list<string>
     * }
     */
    public function importBatch(
        int $limit = 10,
        ?int $productId = null,
        bool $allowProduction = false,
        bool $dryRun = false,
    ): array {
        $limit = max(1, min($limit, (int) config('bnc.ananas_import_batch_max_size', 25)));

        $products = $this->resolveProducts($limit, $productId);

        $payloads = [];
        $productIds = [];
        $skipped = 0;
        $errors = [];

        foreach ($products as $product) {
            $mapping = $this->exportScope->resolveCategoryMapping($product);

            if ($mapping === null) {
                $skipped++;
                $this->mappingService->markNotEligible($product, AnanasEligibilityPolicy::CATEGORY_UNMAPPED);
                continue;
            }

            $eligibility = $this->eligibilityPolicy->evaluate($product);

            if (! $eligibility->eligible) {
                $skipped++;
                $this->mappingService->markNotEligible($product, (string) $eligibility->reasonCode);
                continue;
            }

            try {
                $payloads[] = $this->productMapper->map($product, $mapping);
                $productIds[] = (int) $product->id;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = sprintf('Product %d: %s', $product->id, $e->getMessage());
                $this->mappingService->markFailed(
                    $this->mappingService->findOrCreate($product),
                    $e->getMessage(),
                );
            }
        }

        if ($payloads === []) {
            return [
                'submitted' => 0,
                'skipped' => $skipped,
                'progress_id' => null,
                'product_ids' => [],
                'errors' => $errors,
            ];
        }

        if ($dryRun) {
            return [
                'submitted' => count($payloads),
                'skipped' => $skipped,
                'progress_id' => null,
                'product_ids' => $productIds,
                'errors' => $errors,
            ];
        }

        $this->writeGuard->assertAllowed($allowProduction);

        $import = $this->apiClient->importProducts($payloads, $allowProduction);
        $progressId = $import['progress_id'];

        foreach ($products->whereIn('id', $productIds) as $product) {
            if (! $product instanceof Product) {
                continue;
            }

            $categoryMapping = $this->exportScope->resolveCategoryMapping($product);

            if ($categoryMapping === null) {
                continue;
            }

            $payload = $this->productMapper->map($product, $categoryMapping);
            $this->mappingService->recordSubmission($product, $payload, $progressId);
        }

        return [
            'submitted' => count($payloads),
            'skipped' => $skipped,
            'progress_id' => $progressId,
            'product_ids' => $productIds,
            'errors' => $errors,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Product>
     */
    private function resolveProducts(int $limit, ?int $productId)
    {
        if ($productId !== null) {
            $product = Product::query()
                ->with(['images', 'manufacturer', 'attributeValues.attributeDefinition'])
                ->find($productId);

            if ($product === null) {
                throw new RuntimeException("Product {$productId} not found.");
            }

            return collect([$product]);
        }

        return $this->exportScope->baseQuery()
            ->with(['images', 'manufacturer', 'attributeValues.attributeDefinition'])
            ->limit($limit)
            ->get();
    }
}
