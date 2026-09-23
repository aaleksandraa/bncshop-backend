<?php

namespace App\Services\Olx;

use App\Jobs\RunOlxSyncJob;
use App\Models\ApiImportJob;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Throwable;

class OlxSyncOrchestrator
{
    public function __construct(
        private readonly OlxSyncSettings $settings,
        private readonly OlxApiClient $client,
        private readonly OlxChangeDetector $changeDetector,
        private readonly OlxListingExporter $listingExporter,
        private readonly OlxDailyCreateLimiter $createLimiter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(
        bool $fullSync = false,
        ?int $productId = null,
        ?int $maxCreatesPerRun = null,
        ?int $continueJobId = null,
        bool $stockOnly = false,
    ): array {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $memoryLimit = config('bnc.olx_sync_memory_limit', '512M');
        if ($memoryLimit !== '' && $memoryLimit !== false) {
            @ini_set('memory_limit', (string) $memoryLimit);
        }

        if (! $this->settings->isEnabled()) {
            throw new \RuntimeException('OLX export is disabled in settings.');
        }

        $source = $this->settings->resolveSource();

        if ($continueJobId !== null) {
            return $this->continueExistingJob($continueJobId, $fullSync, $maxCreatesPerRun, $stockOnly);
        }

        if ($productId === null && $this->settings->hasRunningBulkSyncJob($source->id, includeStock: $stockOnly)) {
            return [
                'skipped' => true,
                'reason' => 'concurrent_running',
                'message' => 'Another OLX sync job is already running.',
            ];
        }

        $prefetchedStockDetection = null;

        if ($stockOnly && $productId === null) {
            $prefetchedStockDetection = $this->changeDetector->detectStock();

            if (! $this->stockDetectionHasWork($prefetchedStockDetection)) {
                return [
                    'skipped' => true,
                    'reason' => 'no_stock_changes',
                    'mode' => 'stock',
                    'scan' => [
                        'scanned' => $prefetchedStockDetection['scanned'] ?? 0,
                        'unchanged' => $prefetchedStockDetection['unchanged'] ?? 0,
                        'pending_create' => 0,
                        'pending_update' => 0,
                        'pending_hide' => 0,
                        'pending_unhide' => 0,
                        'pending_delete' => 0,
                        'frozen_invalid_create' => 0,
                    ],
                ];
            }
        }

        $syncStartedAt = now();

        $job = ApiImportJob::query()->create([
            'api_source_id' => $source->id,
            'type' => $stockOnly ? 'olx_stock' : ($fullSync ? 'olx_full' : 'olx_incremental'),
            'status' => 'running',
            'sync_started_at' => $syncStartedAt,
            'started_at' => now(),
        ]);

        $stats = $this->emptyStats($fullSync, $maxCreatesPerRun, $stockOnly);
        $this->registerFatalShutdownHandler($job);

        try {
            $this->client->authenticate();

            if ($productId !== null) {
                return $this->runSingleProduct($job, $source, $syncStartedAt, $stats, $productId, $maxCreatesPerRun);
            }

            $detection = $prefetchedStockDetection ?? (
                $stockOnly
                    ? $this->changeDetector->detectStock(function (int $scanned) use ($job, &$stats): void {
                        $stats['scan']['scanned'] = $scanned;
                        $this->heartbeat($job, $stats, ['phase' => 'detect']);
                    })
                    : $this->changeDetector->detect(
                        $fullSync,
                        function (int $scanned) use ($job, &$stats): void {
                            $stats['scan']['scanned'] = $scanned;
                            $this->heartbeat($job, $stats, ['phase' => 'detect']);
                        },
                    )
            );

            $stats['scan'] = [
                'scanned' => $detection['scanned'],
                'unchanged' => $detection['unchanged'],
                'pending_create' => count($detection['create'] ?? []),
                'pending_update' => count($detection['update'] ?? []),
                'pending_hide' => count($detection['hide'] ?? []),
                'pending_unhide' => count($detection['unhide'] ?? []),
                'pending_delete' => count($detection['delete'] ?? []),
                'frozen_invalid_create' => (int) ($detection['frozen_invalid_create'] ?? 0),
            ];
            $stats['pending'] = [
                'create' => $detection['create'] ?? [],
                'update' => $detection['update'] ?? [],
                'hide' => $detection['hide'] ?? [],
                'unhide' => $detection['unhide'] ?? [],
                'delete' => $detection['delete'] ?? [],
            ];
            $this->heartbeat($job, $stats, ['phase' => 'export']);

            return $this->processPendingWave($job, $source, $syncStartedAt, $stats, $fullSync, $maxCreatesPerRun, $stockOnly);
        } catch (Throwable $e) {
            $this->failJob($source, $job, $stats, $e->getMessage());

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function continueExistingJob(int $jobId, bool $fullSync, ?int $maxCreatesPerRun, bool $stockOnly): array
    {
        $job = ApiImportJob::query()->find($jobId);

        if ($job === null) {
            throw new \RuntimeException("OLX sync job #{$jobId} not found.");
        }

        $staleMessage = (string) ($job->error_message ?? '');
        $reclaimable = $job->status === 'failed'
            && (str_contains($staleMessage, 'exceeded maximum running time')
                || str_contains($staleMessage, 'Wave timed out')
                || str_contains($staleMessage, 'worker timeout'));

        if ($job->status !== 'running' && ! $reclaimable) {
            return [
                'skipped' => true,
                'reason' => 'job_not_running',
                'message' => "OLX sync job #{$jobId} is {$job->status}.",
            ];
        }

        if ($reclaimable) {
            $job->update([
                'status' => 'running',
                'completed_at' => null,
                'error_message' => null,
            ]);
        }

        $stockOnly = $stockOnly || $job->type === 'olx_stock';
        $stats = is_array($job->stats) ? $job->stats : $this->emptyStats($fullSync, $maxCreatesPerRun, $stockOnly);
        $source = $this->settings->resolveSource();
        $syncStartedAt = $job->sync_started_at ?? now();

        $this->client->authenticate();

        if (! isset($stats['pending']) || ! is_array($stats['pending'])) {
            $detection = $stockOnly
                ? $this->changeDetector->detectStock()
                : $this->changeDetector->detect($fullSync);
            $stats['pending'] = [
                'create' => $detection['create'] ?? [],
                'update' => $detection['update'] ?? [],
                'hide' => $detection['hide'] ?? [],
                'unhide' => $detection['unhide'] ?? [],
                'delete' => $detection['delete'] ?? [],
            ];
            $stats['scan'] = [
                'scanned' => $detection['scanned'],
                'unchanged' => $detection['unchanged'],
                'pending_create' => count($detection['create'] ?? []),
                'pending_update' => count($detection['update'] ?? []),
                'pending_hide' => count($detection['hide'] ?? []),
                'pending_unhide' => count($detection['unhide'] ?? []),
                'pending_delete' => count($detection['delete'] ?? []),
                'frozen_invalid_create' => (int) ($detection['frozen_invalid_create'] ?? 0),
            ];
        }

        $stats['pending'] = $this->normalizePending($stats['pending'] ?? []);

        return $this->processPendingWave($job, $source, $syncStartedAt, $stats, $fullSync, $maxCreatesPerRun, $stockOnly);
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function runSingleProduct(
        ApiImportJob $job,
        $source,
        $syncStartedAt,
        array $stats,
        int $productId,
        ?int $maxCreatesPerRun,
    ): array {
        $product = Product::query()
            ->with(['category.parent', 'images', 'attributeValues.attributeDefinition', 'manufacturer'])
            ->findOrFail($productId);
        $action = filled($product->olx_listing_id) ? 'update' : 'create';

        if ($action === 'create' && ! $this->createLimiter->canCreate($maxCreatesPerRun)) {
            throw new \RuntimeException(sprintf(
                'Dnevni limit OLX objava dostignut (%d/%d). Pokušajte sutra ili povećajte max_creates_per_run.',
                $this->createLimiter->createsToday(),
                $this->createLimiter->dailyLimit(),
            ));
        }

        $result = $this->listingExporter->export($product, $action);

        if ($result['action'] === 'create') {
            $this->createLimiter->recordCreate();
            $stats['actions']['created'] = 1;
        } else {
            $stats['actions']['updated'] = 1;
        }

        $this->completeJob($source, $job, $syncStartedAt, $stats);

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function processPendingWave(
        ApiImportJob $job,
        $source,
        $syncStartedAt,
        array $stats,
        bool $fullSync,
        ?int $maxCreatesPerRun,
        bool $stockOnly = false,
    ): array {
        $createQuota = (int) ($stats['limits']['allowed_this_run'] ?? $this->createLimiter->allowedThisRun($maxCreatesPerRun));
        $createQuota -= (int) ($stats['actions']['created'] ?? 0);
        $createQuota = max(0, $createQuota);

        $batchSize = max(1, (int) ($this->settings->all()['batch_size'] ?? 20));
        $waveSize = max(1, (int) config('bnc.olx_sync_wave_size', 40));
        $processedThisWave = 0;
        $productRelations = ['category.parent', 'images', 'attributeValues.attributeDefinition', 'manufacturer'];

        foreach ([
            'hide' => 'hidden',
            'unhide' => 'unhidden',
            'delete' => 'deleted',
            'update' => 'updated',
            'create' => 'created',
        ] as $setKey => $statKey) {
            if ($stockOnly && in_array($setKey, ['create', 'update'], true)) {
                continue;
            }

            if ($setKey === 'create' && $createQuota <= 0) {
                $leftover = count($stats['pending']['create'] ?? []);
                $stats['actions']['skipped_quota'] += $leftover;
                $stats['pending']['create'] = [];
                continue;
            }

            /** @var list<int> $productIds */
            $productIds = array_values(array_map('intval', $stats['pending'][$setKey] ?? []));

            foreach (array_chunk($productIds, $batchSize) as $idChunk) {
                $products = Product::query()
                    ->with($productRelations)
                    ->whereIn('id', $idChunk)
                    ->get()
                    ->keyBy('id');

                foreach ($idChunk as $id) {
                    $product = $products->get($id);
                    $stats['pending'][$setKey] = array_values(array_filter(
                        $stats['pending'][$setKey] ?? [],
                        fn ($pendingId): bool => (int) $pendingId !== (int) $id,
                    ));

                    if ($product === null) {
                        $this->heartbeat($job, $stats);
                        $processedThisWave++;

                        if ($processedThisWave >= $waveSize) {
                            return $this->dispatchContinuation($job, $stats, $fullSync, $maxCreatesPerRun, $stockOnly);
                        }

                        continue;
                    }

                    if ($setKey === 'create' && $createQuota <= 0) {
                        $stats['actions']['skipped_quota']++;
                        $this->heartbeat($job, $stats);
                        $processedThisWave++;

                        if ($processedThisWave >= $waveSize) {
                            return $this->dispatchContinuation($job, $stats, $fullSync, $maxCreatesPerRun, $stockOnly);
                        }

                        continue;
                    }

                    try {
                        $result = $this->listingExporter->export(
                            $product,
                            $setKey,
                        );

                        if ($result['action'] === 'skipped_legacy') {
                            $stats['actions']['skipped_legacy']++;
                        } elseif ($setKey === 'create' && $result['action'] === 'create') {
                            $this->createLimiter->recordCreate();
                            $createQuota--;
                            $stats['actions']['created']++;
                        } elseif ($setKey === 'delete' && $result['action'] === 'delete') {
                            $stats['actions']['deleted']++;
                        } else {
                            $stats['actions'][$statKey]++;
                        }
                    } catch (Throwable $e) {
                        $message = $e->getMessage();

                        if (OlxDailyCreateLimiter::isDailyLimitError($message)) {
                            $createQuota = 0;
                            $stats['actions']['skipped_quota']++;
                            $stats['limits'] = $this->createLimiter->snapshot($maxCreatesPerRun);
                        } elseif ($this->isMissingRequiredAttributeError($message)
                            || str_contains($message, 'validation_failed')) {
                            $stats['actions']['skipped_validation']++;
                            $this->tallyValidationSkip($stats, $message);
                        } elseif ($this->isTransientOlxNetworkError($message)) {
                            $attempts = (int) ($stats['network_retries'][$product->id] ?? 0);
                            if ($attempts < 2) {
                                $stats['network_retries'][$product->id] = $attempts + 1;
                                $stats['actions']['retried_network'] = (int) ($stats['actions']['retried_network'] ?? 0) + 1;
                                $stats['pending'][$setKey][] = $product->id;
                            } else {
                                $stats['actions']['errors'][] = [
                                    'product_id' => $product->id,
                                    'action' => $setKey,
                                    'message' => $message,
                                ];
                            }
                        } else {
                            $stats['actions']['errors'][] = [
                                'product_id' => $product->id,
                                'action' => $setKey,
                                'message' => $message,
                            ];
                        }
                    }

                    $stats['limits'] = $this->createLimiter->snapshot($maxCreatesPerRun);
                    $this->heartbeat($job, $stats);
                    $processedThisWave++;

                    if ($processedThisWave >= $waveSize) {
                        return $this->dispatchContinuation($job, $stats, $fullSync, $maxCreatesPerRun, $stockOnly);
                    }
                }

                unset($products);
            }
        }

        $stats['pending'] = $this->normalizePending($stats['pending'] ?? []);
        $stats['limits'] = $this->createLimiter->snapshot($maxCreatesPerRun);

        if ($this->hasPendingWork($stats, $createQuota, $stockOnly)) {
            return $this->dispatchContinuation($job, $stats, $fullSync, $maxCreatesPerRun, $stockOnly);
        }

        $this->completeJob($source, $job, $syncStartedAt, $stats, ! $stockOnly);

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function dispatchContinuation(
        ApiImportJob $job,
        array $stats,
        bool $fullSync,
        ?int $maxCreatesPerRun,
        bool $stockOnly = false,
    ): array {
        $stats['waves'] = ((int) ($stats['waves'] ?? 0)) + 1;
        $stats['continued'] = true;
        $this->heartbeat($job, $stats, ['phase' => 'continue']);

        RunOlxSyncJob::dispatch($fullSync, null, $maxCreatesPerRun, $job->id, $stockOnly);

        return $stats;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyStats(bool $fullSync, ?int $maxCreatesPerRun, bool $stockOnly = false): array
    {
        return [
            'mode' => $stockOnly ? 'stock' : ($fullSync ? 'full' : 'incremental'),
            'scan' => ['scanned' => 0, 'unchanged' => 0],
            'actions' => [
                'created' => 0,
                'updated' => 0,
                'hidden' => 0,
                'unhidden' => 0,
                'deleted' => 0,
                'skipped_legacy' => 0,
                'skipped_validation' => 0,
                'skipped_quota' => 0,
                'retried_network' => 0,
                'errors' => [],
            ],
            'limits' => $this->createLimiter->snapshot($maxCreatesPerRun),
            'pending' => [
                'create' => [],
                'update' => [],
                'hide' => [],
                'unhide' => [],
                'delete' => [],
            ],
            'skipped_validation_reasons' => [],
            'network_retries' => [],
            'waves' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $pending
     * @return array{create: list<int>, update: list<int>, hide: list<int>, unhide: list<int>, delete: list<int>}
     */
    private function normalizePending(array $pending): array
    {
        $normalized = [
            'create' => [],
            'update' => [],
            'hide' => [],
            'unhide' => [],
            'delete' => [],
        ];

        foreach ($normalized as $key => $_) {
            $normalized[$key] = array_values(array_unique(array_map('intval', $pending[$key] ?? [])));
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function hasPendingWork(array $stats, int $createQuota = 1, bool $stockOnly = false): bool
    {
        foreach ($stats['pending'] ?? [] as $key => $ids) {
            if (! is_array($ids) || $ids === []) {
                continue;
            }

            if ($stockOnly && in_array($key, ['create', 'update'], true)) {
                continue;
            }

            if ($key === 'create' && $createQuota <= 0) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $detection
     */
    private function stockDetectionHasWork(array $detection): bool
    {
        foreach (['hide', 'unhide', 'delete'] as $key) {
            if (($detection[$key] ?? []) !== []) {
                return true;
            }
        }

        return false;
    }

    private function isMissingRequiredAttributeError(string $message): bool
    {
        return str_contains($message, 'obavezni OLX atributi')
            || str_contains($message, 'Nedostaju obavezni');
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function tallyValidationSkip(array &$stats, string $message): void
    {
        $stats['skipped_validation_reasons'] ??= [];
        $payload = $message;

        if (preg_match('/obavezni OLX atributi:\s*(.+)$/u', $message, $matches) === 1) {
            $payload = $matches[1];
        }

        foreach (array_map('trim', explode(',', $payload)) as $reason) {
            if ($reason === '') {
                continue;
            }

            $stats['skipped_validation_reasons'][$reason] = (int) ($stats['skipped_validation_reasons'][$reason] ?? 0) + 1;
        }
    }

    private function isTransientOlxNetworkError(string $message): bool
    {
        $haystack = strtolower($message);

        return str_contains($haystack, 'curl error 28')
            || str_contains($haystack, 'curl error 56')
            || str_contains($haystack, 'connection reset')
            || str_contains($haystack, 'operation timed out')
            || str_contains($haystack, 'connection timed out')
            || str_contains($haystack, 'ssl_read');
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  array<string, mixed>  $extra
     */
    private function heartbeat(ApiImportJob $job, array $stats, array $extra = []): void
    {
        $job->update([
            'stats' => array_merge($stats, $extra),
            'error_message' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function completeJob($source, ApiImportJob $job, $syncStartedAt, array $stats, bool $touchLastSuccessfulSync = true): void
    {
        DB::transaction(function () use ($source, $syncStartedAt, $job, $stats, $touchLastSuccessfulSync): void {
            $sourceUpdate = [
                'connection_status' => 'connected',
                'last_error' => null,
            ];

            if ($touchLastSuccessfulSync) {
                $sourceUpdate['last_successful_sync_at'] = $syncStartedAt;
            }

            $source->update($sourceUpdate);

            $job->update([
                'status' => 'completed',
                'completed_at' => now(),
                'error_message' => null,
                'stats' => $stats,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function failJob($source, ApiImportJob $job, array $stats, string $message): void
    {
        $source->update([
            'connection_status' => 'error',
            'last_error' => $message,
        ]);

        $job->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $message,
            'stats' => $stats,
        ]);
    }

    private function registerFatalShutdownHandler(ApiImportJob $job): void
    {
        $jobId = $job->id;

        register_shutdown_function(static function () use ($jobId): void {
            $error = error_get_last();

            if ($error === null || ! in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            if (! str_contains($error['message'], 'Allowed memory size')) {
                return;
            }

            ApiImportJob::query()
                ->whereKey($jobId)
                ->where('status', 'running')
                ->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'error_message' => 'PHP fatal: '.$error['message'],
                ]);
        });
    }
}
