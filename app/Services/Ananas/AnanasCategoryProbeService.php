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
        private readonly AnanasProbeProductFinder $probeProductFinder,
        private readonly AnanasMasterEanCatalogService $masterEanCatalog,
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
        bool $useAnyEligibleProduct = false,
        bool $requireMasterEan = false,
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

        if ($product === null) {
            if ($requireMasterEan) {
                $product = $this->masterEanCatalog->findFirstEligibleProductWithMasterEan();
            } elseif ($useAnyEligibleProduct) {
                $product = $this->probeProductFinder->findFirstEligibleGlobally();
            } else {
                $product = $this->resolveProbeProduct($mapping);
            }
        }

        if ($product === null) {
            throw new RuntimeException($useAnyEligibleProduct
                ? 'No eligible product found anywhere in catalog for probe.'
                : 'No eligible product found in mapped BNC category scope for probe.');
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
        $ean = trim((string) ($payload['ean'] ?? $product->barcode));

        if ($requireMasterEan && $ean !== '' && ! $this->masterEanCatalog->isInMasterCatalog($ean)) {
            throw new RuntimeException(sprintf(
                'Probe product %d EAN %s is not in Ananas master catalog. Use bnc:ananas-find-master-ean-product or omit --master-ean-only.',
                $product->id,
                $ean,
            ));
        }

        if ($dryRun) {
            $eanInMaster = $ean !== '' ? $this->masterEanCatalog->isInMasterCatalog($ean) : null;
            $message = 'Dry run — payload prepared but not submitted.';

            if ($eanInMaster === true) {
                $message .= ' EAN is in Ananas master catalog; after import, GET /products should show the item for category validation.';
            } elseif ($eanInMaster === false) {
                $message .= ' EAN is not in master catalog — Ananas loads new items manually; email '
                    .config('bnc.ananas_onboarding_email', 'onboarding@ananas.rs')
                    .' with the import Progress UUID.';
            }

            return new AnanasCategoryProbeResult(
                probeId: 0,
                mappingId: (int) $mapping->id,
                productId: (int) $product->id,
                status: AnanasCategoryProbe::STATUS_SUBMITTED,
                progressId: null,
                observedCategories: [],
                observedProductType: $productType,
                remoteProductId: null,
                message: $message,
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

        $eanInMaster = $ean !== '' ? $this->masterEanCatalog->isInMasterCatalog($ean) : null;

        $this->mappingService->recordSubmission($product, $payload, $progressId, $eanInMaster);

        $remote = $this->pollRemoteProduct($ean, $waitSeconds);
        $result = $this->finalizeProbe(
            $probe,
            $mapping,
            $remote,
            $productType,
            $categoryCandidate,
            $eanInMaster,
        );

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
        $eanInMaster = $this->masterEanCatalog->isInMasterCatalog($ean);
        $result = $this->finalizeProbe(
            $probe,
            $mapping,
            $remote,
            (string) $probe->product_type,
            (string) $probe->category_candidate,
            $eanInMaster,
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

    /**
     * @return array{
     *   category_ids: list<int>,
     *   total_in_scope: int,
     *   active_public: int,
     *   eligible: int,
     *   reasons: array<string, int>,
     *   first_eligible_product_id: int|null
     * }
     */
    public function diagnoseProbeCandidates(AnanasCategoryMapping $mapping, int $scanLimit = 500): array
    {
        return $this->probeProductFinder->diagnose($mapping, $scanLimit);
    }

    private function resolveProbeProduct(AnanasCategoryMapping $mapping): ?Product
    {
        return $this->probeProductFinder->findFirstEligible($mapping);
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
        ?bool $eanInMasterCatalog = null,
    ): array {
        if ($remote === null) {
            if ($eanInMasterCatalog === false) {
                $onboardingEmail = (string) config('bnc.ananas_onboarding_email', 'onboarding@ananas.rs');
                $message = sprintf(
                    'Import accepted but EAN is not in Ananas master catalog. Ananas onboarding loads new items manually — email %s with Progress UUID %s, then recheck this probe.',
                    $onboardingEmail,
                    $probe->progress_id ?? '(unknown)',
                );
                $status = AnanasCategoryProbe::STATUS_AWAITING_ONBOARDING;
            } else {
                $message = 'Import submitted but product not yet visible via GET /products. Re-run reconcile or recheck later.';
                $status = AnanasCategoryProbe::STATUS_PENDING;
            }

            $probe->update([
                'status' => $status,
                'error' => $message,
            ]);
            $mapping->update([
                'category_validation_status' => AnanasCategoryMapping::VALIDATION_PENDING,
                'category_validation_notes' => $message,
            ]);

            return [
                'status' => $status,
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
