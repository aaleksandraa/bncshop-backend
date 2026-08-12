<?php

namespace App\Services\Sync;

use App\Jobs\ReindexProductsJob;
use App\Models\ApiImportJob;
use App\Models\ApiImportJobItem;
use App\Models\ApiSource;
use App\Models\Product;
use App\Services\Catalog\ProductReadCache;
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
    ): array {
        $syncStartedAt = now();
        $previousSyncAt = $fullSync
            ? null
            : IntegrationApiClient::formatModifiedAfter($source->last_successful_sync_at);

        $job = ApiImportJob::query()->create([
            'api_source_id' => $source->id,
            'type' => $fullSync ? 'full' : 'incremental',
            'status' => 'running',
            'sync_started_at' => $syncStartedAt,
            'started_at' => now(),
        ]);

        $stats = [
            'categories' => ['created' => 0, 'updated' => 0, 'pending_parent' => 0],
            'attributes' => ['created' => 0, 'updated' => 0, 'mappings' => 0],
            'products' => [
                'created' => 0,
                'updated' => 0,
                'deactivated' => 0,
                'imported' => 0,
                'pages' => 0,
                'errors' => [],
            ],
        ];

        $importedProductIds = [];

        try {
            $client = IntegrationApiClient::forSource($source);
            $client->ensureAuthenticated();

            $importMetadata = $fullSync && ! $skipMetadata;

            if ($importMetadata) {
                $stats['categories'] = $this->categoryImporter->upsertMany($client->getCategories());
                $stats['attributes'] = $this->attributeImporter->upsertMany($client->getAttributes());
            }

            $stats['products'] = $this->syncProducts(
                $client,
                $source,
                $job,
                $previousSyncAt,
                $maxProductPages,
                $startProductPage,
                $importedProductIds,
            );

            $this->changeLogger->flush();

            DB::transaction(function () use ($source, $syncStartedAt, $job, $stats): void {
                $source->update([
                    'last_successful_sync_at' => $syncStartedAt,
                    'connection_status' => 'connected',
                    'last_error' => null,
                ]);

                $job->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'stats' => $stats,
                ]);
            });

            if ($stats['products']['imported'] > 0) {
                $this->productReadCache->flushAll();
                ReindexProductsJob::dispatch(
                    array_values(array_unique($importedProductIds)),
                );
            }

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
     * @return array{
     *     created: int,
     *     updated: int,
     *     deactivated: int,
     *     imported: int,
     *     pages: int,
     *     errors: array<int, string>
     * }
     */
    private function syncProducts(
        IntegrationApiClient $client,
        ApiSource $source,
        ApiImportJob $job,
        ?string $dateModifiedAfter,
        ?int $maxProductPages = null,
        ?int $startProductPage = null,
        array &$importedProductIds = [],
    ): array {
        $page = $startProductPage ?? 1;
        $created = 0;
        $updated = 0;
        $deactivated = 0;
        $errors = [];
        $pageSize = $client->resolvedPageSize();
        $pagesProcessed = 0;

        do {
            $started = microtime(true);
            $response = $client->getProducts($dateModifiedAfter, $page);
            $products = $response['data'];
            $pagination = $response['meta'];
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

            if ($maxProductPages !== null && $pagesProcessed >= $maxProductPages) {
                break;
            }

            $page = $this->resolveNextPage($pagination, $page, count($products), $pageSize);
        } while ($page !== null);

        $imported = $created + $updated + $deactivated;

        return [
            'created' => $created,
            'updated' => $updated,
            'deactivated' => $deactivated,
            'imported' => $imported,
            'pages' => $pagesProcessed,
            'errors' => $errors,
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
