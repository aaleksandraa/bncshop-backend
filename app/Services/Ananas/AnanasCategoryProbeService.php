<?php

namespace App\Services\Ananas;

use App\Models\AnanasCategoryMapping;
use App\Models\AnanasCategoryProbe;
use App\Models\Product;
use RuntimeException;

class AnanasCategoryProbeService
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
     * Empirically validate an Ananas category string by importing one eligible product
     * and reconciling the GET /products response categories[] field.
     */
    public function probe(
        AnanasCategoryMapping $mapping,
        ?Product $product = null,
        bool $allowProduction = false,
        bool $dryRun = false,
        ?int $waitSeconds = null,
    ): AnanasCategoryProbeResult {
        $mapping->loadMissing('category');

        $productType = trim((string) $mapping->ananas_product_type);
        $categoryCandidate = trim((string) $mapping->ananas_category);

        if ($productType === '') {
            throw new RuntimeException('Category mapping requires ananas_product_type.');
        }

        if ($categoryCandidate === '') {
            throw new RuntimeException('Category mapping requires ananas_category candidate string to probe.');
        }

        $product ??= $this->resolveProbeProduct($mapping);

        if ($product === null) {
            throw new RuntimeException('No eligible product found in mapped BNC category scope for probe.');
        }

        $eligibility = $this->eligibilityPolicy->evaluateProductData($product);

        if (! $eligibility->eligible) {
            throw new RuntimeException(sprintf(
                'Probe product %d is not eligible: %s',
                $product->id,
                $eligibility->reasonCode ?? 'NOT_ELIGIBLE',
            ));
        }

        $payload = $this->productMapper->mapForProbe($product, $mapping);

        if ($dryRun) {
            return new AnanasCategoryProbeResult(
                probeId: 0,
                mappingId: (int) $mapping->id,
                productId: (int) $product->id,
                status: AnanasCategoryProbe::STATUS_SUBMITTED,
                progressId: null,
                observedCategories: [],
                observedProductType: $productType,
                remoteProductId: null,
                message: 'Dry run — payload prepared but not submitted.',
            );
        }

        $this->writeGuard->assertAllowed($allowProduction);

        $import = $this->apiClient->importProducts([$payload], $allowProduction);
        $progressId = $import['progress_id'];

        $probe = AnanasCategoryProbe::query()->create([
            'category_mapping_id' => $mapping->id,
            'product_id' => $product->id,
            'product_type' => $productType,
            'category_candidate' => $categoryCandidate,
            'progress_id' => $progressId,
            'status' => AnanasCategoryProbe::STATUS_SUBMITTED,
        ]);

        $mapping->update([
            'category_validation_status' => AnanasCategoryMapping::VALIDATION_PENDING,
            'last_probe_product_id' => $product->id,
            'last_probe_progress_id' => $progressId,
            'category_validation_notes' => 'Probe submitted; awaiting GET reconciliation.',
        ]);

        $this->mappingService->recordSubmission($product, $payload, $progressId);

        $remote = $this->pollRemoteProduct((string) $payload['ean'], $waitSeconds);
        $result = $this->finalizeProbe($probe, $mapping, $remote, $productType, $categoryCandidate);

        return new AnanasCategoryProbeResult(
            probeId: (int) $probe->id,
            mappingId: (int) $mapping->id,
            productId: (int) $product->id,
            status: $result['status'],
            progressId: $progressId,
            observedCategories: $result['observed_categories'],
            observedProductType: $result['observed_product_type'],
            remoteProductId: $result['remote_product_id'],
            message: $result['message'],
        );
    }

    /**
     * Re-check a pending probe without re-submitting import.
     */
    public function recheckProbe(AnanasCategoryProbe $probe, ?int $waitSeconds = null): AnanasCategoryProbeResult
    {
        $probe->loadMissing(['categoryMapping', 'product']);
        $mapping = $probe->categoryMapping;

        if ($mapping === null) {
            throw new RuntimeException('Probe is missing category mapping.');
        }

        $product = $probe->product;

        if ($product === null) {
            throw new RuntimeException('Probe is missing product.');
        }

        $ean = trim((string) $product->barcode);

        if ($ean === '') {
            throw new RuntimeException('Probe product has no EAN.');
        }

        $remote = $this->pollRemoteProduct($ean, $waitSeconds ?? 0, attemptsOverride: 1);
        $result = $this->finalizeProbe(
            $probe,
            $mapping,
            $remote,
            (string) $probe->product_type,
            (string) $probe->category_candidate,
        );

        return new AnanasCategoryProbeResult(
            probeId: (int) $probe->id,
            mappingId: (int) $mapping->id,
            productId: (int) $product->id,
            status: $result['status'],
            progressId: $probe->progress_id,
            observedCategories: $result['observed_categories'],
            observedProductType: $result['observed_product_type'],
            remoteProductId: $result['remote_product_id'],
            message: $result['message'],
        );
    }

    private function resolveProbeProduct(AnanasCategoryMapping $mapping): ?Product
    {
        $categoryIds = [(int) $mapping->category_id];

        if ($mapping->include_descendants) {
            $categoryIds = array_merge(
                $categoryIds,
                $this->descendantCategoryIdsForProbe((int) $mapping->category_id),
            );
        }

        $categoryIds = array_values(array_unique(array_filter($categoryIds)));

        $query = Product::query()
            ->where('is_public', true)
            ->where('status', 'active')
            ->whereIn('category_id', $categoryIds === [] ? [-1] : $categoryIds)
            ->with(['images', 'manufacturer', 'attributeValues.attributeDefinition']);

        foreach ($query->limit(50)->get() as $product) {
            if ($product instanceof Product && $this->eligibilityPolicy->evaluateProductData($product)->eligible) {
                return $product;
            }
        }

        return null;
    }

    /**
     * @return array<int, int>
     */
    private function descendantCategoryIdsForProbe(int $categoryId): array
    {
        $parentMap = \App\Models\Category::query()->pluck('parent_id', 'id')->all();
        $childrenByParent = [];

        foreach ($parentMap as $id => $parentId) {
            if ($parentId !== null) {
                $childrenByParent[(int) $parentId][] = (int) $id;
            }
        }

        $ids = [];
        $queue = [$categoryId];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($childrenByParent[$current] ?? [] as $childId) {
                $ids[] = $childId;
                $queue[] = $childId;
            }
        }

        return $ids;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pollRemoteProduct(string $ean, ?int $waitSeconds, ?int $attemptsOverride = null): ?array
    {
        $interval = max(1, (int) config('bnc.ananas_import_poll_interval_seconds', 5));
        $maxAttempts = $attemptsOverride ?? max(1, (int) config('bnc.ananas_import_poll_max_attempts', 12));

        if ($waitSeconds !== null && $waitSeconds > 0) {
            $maxAttempts = max($maxAttempts, (int) ceil($waitSeconds / $interval));
        }

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            if ($attempt > 1) {
                sleep($interval);
            }

            $remote = $this->apiClient->findProductByEan($ean);

            if ($remote !== null) {
                return $remote;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $remote
     * @return array{
     *   status: string,
     *   observed_categories: list<string>,
     *   observed_product_type: string|null,
     *   remote_product_id: string|null,
     *   message: string
     * }
     */
    private function finalizeProbe(
        AnanasCategoryProbe $probe,
        AnanasCategoryMapping $mapping,
        ?array $remote,
        string $expectedProductType,
        string $categoryCandidate,
    ): array {
        if ($remote === null) {
            $message = 'Import submitted but product not yet visible via GET /products. Re-run reconcile or recheck later.';
            $probe->update([
                'status' => AnanasCategoryProbe::STATUS_PENDING,
                'error' => $message,
            ]);
            $mapping->update([
                'category_validation_status' => AnanasCategoryMapping::VALIDATION_PENDING,
                'category_validation_notes' => $message,
            ]);

            return [
                'status' => AnanasCategoryProbe::STATUS_PENDING,
                'observed_categories' => [],
                'observed_product_type' => null,
                'remote_product_id' => null,
                'message' => $message,
            ];
        }

        $observedCategories = $this->extractCategories($remote);
        $observedProductType = isset($remote['productType']) ? (string) $remote['productType'] : null;
        $remoteProductId = isset($remote['id']) ? (string) $remote['id'] : null;

        $categoryMatches = $this->categoryMatches($categoryCandidate, $observedCategories);
        $productTypeMatches = $observedProductType === null
            || mb_strtolower(trim($observedProductType)) === mb_strtolower(trim($expectedProductType));

        if ($categoryMatches && $productTypeMatches) {
            $message = 'Category string validated empirically via GET /products categories[].';
            $status = AnanasCategoryProbe::STATUS_VALIDATED;
            $validationStatus = AnanasCategoryMapping::VALIDATION_VALIDATED;
        } else {
            $message = sprintf(
                'Probe completed but candidate "%s" not found in observed categories: [%s]. productType expected=%s observed=%s',
                $categoryCandidate,
                implode(', ', $observedCategories),
                $expectedProductType,
                $observedProductType ?? 'null',
            );
            $status = AnanasCategoryProbe::STATUS_FAILED;
            $validationStatus = AnanasCategoryMapping::VALIDATION_FAILED;
        }

        $probe->update([
            'status' => $status,
            'observed_categories' => $observedCategories,
            'observed_product_type' => $observedProductType,
            'remote_product_id' => $remoteProductId,
            'error' => $status === AnanasCategoryProbe::STATUS_FAILED ? $message : null,
        ]);

        $mapping->update([
            'category_validation_status' => $validationStatus,
            'observed_categories' => $observedCategories,
            'category_validated_at' => now(),
            'category_validation_notes' => $message,
        ]);

        if ($probe->product !== null) {
            $this->mappingService->applyRemoteProduct(
                $this->mappingService->findOrCreate($probe->product),
                $remote,
            );
        }

        return [
            'status' => $status,
            'observed_categories' => $observedCategories,
            'observed_product_type' => $observedProductType,
            'remote_product_id' => $remoteProductId,
            'message' => $message,
        ];
    }

    /**
     * @param  array<string, mixed>  $remote
     * @return list<string>
     */
    public function extractCategories(array $remote): array
    {
        $categories = $remote['categories'] ?? [];

        if (! is_array($categories)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $value): ?string => is_string($value) && trim($value) !== '' ? trim($value) : null,
            $categories,
        )));
    }

    /**
     * @param  list<string>  $observed
     */
    public function categoryMatches(string $candidate, array $observed): bool
    {
        $candidate = trim($candidate);

        if ($candidate === '') {
            return false;
        }

        foreach ($observed as $category) {
            if ($category === $candidate) {
                return true;
            }

            if (mb_strtolower($category) === mb_strtolower($candidate)) {
                return true;
            }
        }

        return false;
    }
}
