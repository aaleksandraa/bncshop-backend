<?php

namespace App\Services\Sync;

use App\Jobs\ReindexProductsJob;
use App\Models\ApiImportJob;
use App\Models\ApiImportJobItem;
use App\Models\ApiSource;
use App\Models\Product;
use App\Services\Catalog\ProductReadCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SyncOrchestrator
{
    public function __construct(
        private readonly CategoryImporter $categoryImporter,
        private readonly AttributeImporter $attributeImporter,
        private readonly ProductImporter $productImporter,
        private readonly ProductReadCache $productReadCache,
        private readonly ImportJobChangeLogger $changeLogger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(
        ApiSource $source,
        bool $fullSync = false,
        ?int $maxProductPages = null,
        ?int $startProductPage = null,
        bool $skipMetadata = false,
        ?int $importJobId = null,
        ?string $modifiedAfter = null,
        ?int $timeBudgetSeconds = null,
        bool $chunked = false,
    ): array {
        $job = $this->resolveJob($source, $fullSync, $importJobId);
        $syncStartedAt = $job->sync_started_at instanceof Carbon
            ? $job->sync_started_at
            : Carbon::parse($job->sync_started_at);

        $previousSyncAt = $fullSync
            ? null
            : ($modifiedAfter
                ?? data_get($job->stats, 'modified_after')
                ?? IntegrationApiClient::formatModifiedAfter($source->last_successful_sync_at));

        $stats = $this->baseStats($job->stats);
        $stats['modified_after'] = $previousSyncAt;
        $stats['wave_started_at'] = $syncStartedAt->toIso8601String();
        $stats['import_job_id'] = $job->id;
        $importedProductIds = [];
        $mustStopAt = $timeBudgetSeconds !== null && $timeBudgetSeconds > 0
            ? now()->addSeconds($timeBudgetSeconds)
            : null;

        try {
            $client = IntegrationApiClient::forSource($source);
            $client->ensureAuthenticated();

            $importMetadata = $fullSync && ! $skipMetadata && $importJobId === null;

            if ($importMetadata) {
                $stats['categories'] = $this->categoryImporter->upsertMany($client->getCategories());
                $stats['attributes'] = $this->attributeImporter->upsertMany($client->getAttributes());
            }

            $productStats = $this->syncProducts(
                $client,
                $source,
                $job,
                is_string($previousSyncAt) ? $previousSyncAt : null,
                $maxProductPages,
                $startProductPage,
                $importedProductIds,
                $mustStopAt,
                $stats,
            );

            $stats['products'] = $this->mergeProductStats($stats['products'], $productStats);
            $stats['incomplete'] = ($productStats['next_page'] ?? null) !== null;
            $stats['next_page'] = $productStats['next_page'] ?? null;
            $this->changeLogger->flush();

            if ($stats['incomplete'] === true) {
                $job->update([
                    'status' => $chunked ? 'running' : 'completed',
                    'completed_at' => $chunked ? null : now(),
                    'stats' => $stats,
                    'error_message' => null,
                ]);

                if ($importedProductIds !== []) {
                    $this->productReadCache->flushAll();
                    ReindexProductsJob::dispatch(array_values(array_unique($importedProductIds)));
                }

                $stats['import_job_id'] = $job->id;
                $stats['modified_after'] = $previousSyncAt;

                return $stats;
            }

            DB::transaction(function () use ($source, $syncStartedAt, $job, $stats): void {
                $source->update([
                    'last_successful_sync_at' => $syncStartedAt,
                    'connection_status' => 'connected',
                    'last_error' => null,
                ]);

                $job->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'error_message' => null,
                    'stats' => $stats,
                ]);
            });

            if (($stats['products']['imported'] ?? 0) > 0) {
                $this->productReadCache->flushAll();
                ReindexProductsJob::dispatch(
                    array_values(array_unique($importedProductIds)),
                );
            }

            $stats['import_job_id'] = $job->id;
            $stats['modified_after'] = $previousSyncAt;
            $stats['products']['next_page'] = null;

            return $stats;
        } catch (Throwable $e) {
            $this->changeLogger->flush();

            $source->update([
                'connection_status' => 'error',
                'last_error' => $e->getMessage(),
            ]);

            $job->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
                'stats' => $stats,
            ]);

            throw new RuntimeException('Sync failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @param  array<string, mixed>|null  $existing
     * @return array<string, mixed>
     */
    private function baseStats(?array $existing): array
    {
        $defaults = [
            'categories' => ['created' => 0, 'updated' => 0, 'pending_parent' => 0],
            'attributes' => ['created' => 0, 'updated' => 0, 'mappings' => 0],
            'products' => [
                'created' => 0,
                'updated' => 0,
                'deactivated' => 0,
                'imported' => 0,
                'pages' => 0,
                'errors' => [],
                'next_page' => null,
            ],
        ];

        if ($existing === null) {
            return $defaults;
        }

        return array_replace_recursive($defaults, $existing);
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $chunk
     * @return array<string, mixed>
     */
    private function mergeProductStats(array $current, array $chunk): array
    {
        return [
            'created' => (int) ($current['created'] ?? 0) + (int) ($chunk['created'] ?? 0),
            'updated' => (int) ($current['updated'] ?? 0) + (int) ($chunk['updated'] ?? 0),
            'deactivated' => (int) ($current['deactivated'] ?? 0) + (int) ($chunk['deactivated'] ?? 0),
            'imported' => (int) ($current['imported'] ?? 0) + (int) ($chunk['imported'] ?? 0),
            'pages' => (int) ($current['pages'] ?? 0) + (int) ($chunk['pages'] ?? 0),
            'errors' => array_values(array_merge(
                is_array($current['errors'] ?? null) ? $current['errors'] : [],
                is_array($chunk['errors'] ?? null) ? $chunk['errors'] : [],
            )),
            'next_page' => $chunk['next_page'] ?? null,
        ];
    }

    private function resolveJob(ApiSource $source, bool $fullSync, ?int $importJobId): ApiImportJob
    {
        if ($importJobId !== null) {
            $job = ApiImportJob::query()->find($importJobId);

            if ($job === null || $job->api_source_id !== $source->id) {
                throw new RuntimeException("Import job #{$importJobId} does not belong to API source #{$source->id}.");
            }

            if ($job->status !== 'running') {
                $job->update([
                    'status' => 'running',
                    'completed_at' => null,
                    'error_message' => null,
                ]);
            }

            return $job;
        }

        return ApiImportJob::query()->create([
            'api_source_id' => $source->id,
            'type' => $fullSync ? 'full' : 'incremental',
            'status' => 'running',
            'sync_started_at' => now(),
            'started_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array{
     *     created: int,
     *     updated: int,
     *     deactivated: int,
     *     imported: int,
     *     pages: int,
     *     errors: array<int, string>,
     *     next_page: int|null
     * }
     */
    private function syncProducts(
        IntegrationApiClient $client,
        ApiSource $source,
        ApiImportJob $job,
        ?string $dateModifiedAfter,
        ?int $maxProductPages,
        ?int $startProductPage,
        array &$importedProductIds,
        ?Carbon $mustStopAt,
        array &$stats,
    ): array {
        $page = $startProductPage ?? 1;
        $created = 0;
        $updated = 0;
        $deactivated = 0;
        $errors = [];
        $requestPageSize = $dateModifiedAfter !== null
            ? (int) config('bnc.a1_api_incremental_page_size', 25)
            : null;
        $pageSize = $client->resolvedPageSize($requestPageSize);
        $pagesProcessed = 0;
        $pageDelayMs = max(0, (int) config('bnc.a1_api_page_delay_ms', 1000));
        $nextPage = null;

        do {
            $started = microtime(true);
            $response = $client->getProducts($dateModifiedAfter, $page, $requestPageSize);
            $products = $response['data'];
            $pagination = $response['meta'];
            $pageSize = (int) ($response['page_size'] ?? $pageSize);
            $pageImported = 0;
            $pageErrors = [];

            foreach ($products as $productPayload) {
                try {
                    $result = Product::withoutSyncingToSearch(function () use ($productPayload, $source): ProductUpsertResult {
                        return $this->productImporter->upsertOne($productPayload, $source);
                    });

                    $this->changeLogger->log($result, $job);
                    $importedProductIds[] = $result->product->id;
                    $pageImported++;

                    match ($result->action) {
                        'inserted' => $created++,
                        'updated' => $updated++,
                        'deactivated' => $deactivated++,
                        default => null,
                    };
                } catch (Throwable $e) {
                    $externalId = (string) ($productPayload['productId'] ?? 'unknown');
                    $message = $e->getMessage();
                    $pageErrors[] = "{$externalId}: {$message}";
                    $this->changeLogger->logError($externalId, $message, $job);
                }

                $job->touch();
            }

            $errors = array_merge($errors, $pageErrors);
            $pagesProcessed++;

            ApiImportJobItem::query()->create([
                'api_import_job_id' => $job->id,
                'page' => $page,
                'records_count' => $pageImported,
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
                'errors' => $pageErrors !== [] ? $pageErrors : null,
            ]);

            $nextPage = $this->resolveNextPage($pagination, $page, count($products), $pageSize);
            $chunkStats = $this->mergeProductStats($stats['products'], [
                'created' => $created,
                'updated' => $updated,
                'deactivated' => $deactivated,
                'imported' => $created + $updated + $deactivated,
                'pages' => $pagesProcessed,
                'errors' => $errors,
                'next_page' => $nextPage,
            ]);
            $job->update([
                'stats' => array_merge($stats, [
                    'products' => $chunkStats,
                    'next_page' => $nextPage,
                    'incomplete' => $nextPage !== null,
                    'modified_after' => $dateModifiedAfter,
                ]),
            ]);
            $job->touch();

            $hitPageCap = $maxProductPages !== null && $pagesProcessed >= $maxProductPages;
            $hitTimeBudget = $mustStopAt !== null && now()->gte($mustStopAt);

            if ($hitPageCap || $hitTimeBudget || $nextPage === null) {
                break;
            }

            $page = $nextPage;

            if ($pageDelayMs > 0) {
                usleep($pageDelayMs * 1000);
            }
        } while ($page !== null);

        $imported = $created + $updated + $deactivated;

        return [
            'created' => $created,
            'updated' => $updated,
            'deactivated' => $deactivated,
            'imported' => $imported,
            'pages' => $pagesProcessed,
            'errors' => $errors,
            'next_page' => $nextPage,
        ];
    }

    /**
     * @param  array<string, mixed>  $pagination
     */
    private function resolveNextPage(array $pagination, int $currentPage, int $recordsOnPage, int $pageSize): ?int
    {
        if (array_key_exists('nextPage', $pagination)) {
            $next = $pagination['nextPage'];

            if ($next === null || $next === '' || (int) $next <= $currentPage) {
                return null;
            }

            return (int) $next;
        }

        if ($recordsOnPage >= $pageSize) {
            return $currentPage + 1;
        }

        return null;
    }
}
