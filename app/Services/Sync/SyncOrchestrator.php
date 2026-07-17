<?php

namespace App\Services\Sync;

use App\Models\ApiImportJob;
use App\Models\ApiImportJobItem;
use App\Models\ApiSource;
use App\Models\Product;
use App\Jobs\ReindexProductsJob;
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
        $previousSyncAt = $fullSync ? null : $source->last_successful_sync_at?->toIso8601String();

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
            'products' => ['imported' => 0, 'pages' => 0, 'errors' => []],
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
     * @return array{imported: int, pages: int, errors: array<int, string>}
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
        $imported = 0;
        $errors = [];
        $pageSize = $source->page_size ?? config('bnc.default_page_size', 500);
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
                    $product = Product::withoutSyncingToSearch(function () use ($productPayload, $source): Product {
                        return $this->productImporter->upsertOne($productPayload, $source);
                    });
                    $importedProductIds[] = $product->id;
                    $pageImported++;
                } catch (Throwable $e) {
                    $externalId = $productPayload['productId'] ?? 'unknown';
                    $pageErrors[] = "{$externalId}: {$e->getMessage()}";
                }
            }

            $imported += $pageImported;
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

        return [
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
