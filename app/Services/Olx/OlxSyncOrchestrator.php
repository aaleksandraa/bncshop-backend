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
            return $this->continueExistingJob($continueJobId, $fullSync, $maxCreatesPerRun);
        }

        if ($productId === null && $this->settings->hasRunningBulkSyncJob($source->id)) {
            return [
                'skipped' => true,
                'reason' => 'concurrent_running',
                'message' => 'Another OLX sync job is already running.',
            ];
        }

        $syncStartedAt = now();

        $job = ApiImportJob::query()->create([
            'api_source_id' => $source->id,
            'type' => $fullSync ? 'olx_full' : 'olx_incremental',
            'status' => 'running',
            'sync_started_at' => $syncStartedAt,
            'started_at' => now(),
        ]);

        $stats = $this->emptyStats($fullSync, $maxCreatesPerRun);
        $this->registerFatalShutdownHandler($job);

        try {
            $this->client->authenticate();

            if ($productId !== null) {
                return $this->runSingleProduct($job, $source, $syncStartedAt, $stats, $productId, $maxCreatesPerRun);
            }

            $detection = $this->changeDetector->detect(
                $fullSync,
                function (int $scanned) use ($job, &$stats): void {
                    $stats['scan']['scanned'] = $scanned;
                    $this->heartbeat($job, $stats, ['phase' => 'detect']);
                },
            );

            $stats['scan'] = [
                'scanned' => $detection['scanned'],
                'unchanged' => $detection['unchanged'],
                'pending_create' => count($detection['create']),
                'pending_update' => count($detection['update']),
                'pending_hide' => count($detection['hide']),
                'pending_unhide' => count($detection['unhide']),
            ];
            $stats['pending'] = [
                'create' => $detection['create'],
                'update' => $detection['update'],
                'hide' => $detection['hide'],
                'unhide' => $detection['unhide'],
            ];
            $this->heartbeat($job, $stats, ['phase' => 'export']);

            return $this->processPendingWave($job, $source, $syncStartedAt, $stats, $fullSync, $maxCreatesPerRun);
        } catch (Throwable $e) {
            $this->failJob($source, $job, $stats, $e->getMessage());

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function continueExistingJob(int $jobId, bool $fullSync, ?int $maxCreatesPerRun): array
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

        $stats = is_array($job->stats) ? $job->stats : $this->emptyStats($fullSync, $maxCreatesPerRun);
        $source = $this->settings->resolveSource();
        $syncStartedAt = $job->sync_started_at ?? now();

        $this->client->authenticate();

        if (! isset($stats['pending']) || ! is_array($stats['pending'])) {
            $detection = $this->changeDetector->detect($fullSync);
            $stats['pending'] = [
                'create' => $detection['create'],
                'update' => $detection['update'],
                'hide' => $detection['hide'],
                'unhide' => $detection['unhide'],
            ];
            $stats['scan'] = [
                'scanned' => $detection['scanned'],
                'unchanged' => $detection['unchanged'],
                'pending_create' => count($detection['create']),
                'pending_update' => count($detection['update']),
            ];
        }

        return $this->processPendingWave($job, $source, $syncStartedAt, $stats, $fullSync, $maxCreatesPerRun);
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
    ): array {
        $createQuota = (int) ($stats['limits']['allowed_this_run'] ?? $this->createLimiter->allowedThisRun($maxCreatesPerRun));
        $createQuota -= (int) ($stats['actions']['created'] ?? 0);
        $createQuota = max(0, $createQuota);

        $batchSize = max(1, (int) ($this->settings->all()['batch_size'] ?? 20));
        $waveSize = max(1, (int) config('bnc.olx_sync_wave_size', 40));
        $processedThisWave = 0;
        $productRelations = ['category.parent', 'images', 'attributeValues.attributeDefinition', 'manufacturer'];

        foreach ([
            'create' => 'created',
            'update' => 'updated',
            'hide' => 'hidden',
            'unhide' => 'unhidden',
        ] as $setKey => $statKey) {
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
                            return $this->dispatchContinuation($job, $stats, $fullSync, $maxCreatesPerRun);
                        }

                        continue;
                    }

                    if ($setKey === 'create' && $createQuota <= 0) {
                        $stats['actions']['skipped_quota']++;
                        $this->heartbeat($job, $stats);
                        $processedThisWave++;

                        if ($processedThisWave >= $waveSize) {
                            return $this->dispatchContinuation($job, $stats, $fullSync, $maxCreatesPerRun);
                        }

                        continue;
                    }

                    try {
                        $result = $this->listingExporter->export(
                            $product,
                            $setKey === 'unhide' ? 'unhide' : ($setKey === 'hide' ? 'hide' : $setKey),
                        );

                        if ($result['action'] === 'skipped_legacy') {
                            $stats['actions']['skipped_legacy']++;
                        } elseif ($setKey === 'create' && $result['action'] === 'create') {
                            $this->createLimiter->recordCreate();
                            $createQuota--;
                            $stats['actions']['created']++;
                        } else {
                            $stats['actions'][$statKey]++;
                        }
                    } catch (Throwable $e) {
                        $message = $e->getMessage();

                        if (OlxDailyCreateLimiter::isDailyLimitError($message)) {
                            $createQuota = 0;
                            $stats['actions']['skipped_quota']++;
                            $stats['limits'] = $this->createLimiter->snapshot($maxCreatesPerRun);
                        } elseif (str_contains($message, 'Nedostaju obavezni OLX atributi')
                            || str_contains($message, 'validation_failed')) {
                            $stats['actions']['skipped_validation']++;
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
                        return $this->dispatchContinuation($job, $stats, $fullSync, $maxCreatesPerRun);
                    }
                }

                unset($products);
            }
        }

        $stats['pending'] = [
            'create' => [],
            'update' => [],
            'hide' => [],
            'unhide' => [],
        ];
        $stats['limits'] = $this->createLimiter->snapshot($maxCreatesPerRun);
        $this->completeJob($source, $job, $syncStartedAt, $stats);

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
    ): array {
        $stats['waves'] = ((int) ($stats['waves'] ?? 0)) + 1;
        $stats['continued'] = true;
        $this->heartbeat($job, $stats, ['phase' => 'continue']);

        RunOlxSyncJob::dispatch($fullSync, null, $maxCreatesPerRun, $job->id);

        return $stats;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyStats(bool $fullSync, ?int $maxCreatesPerRun): array
    {
        return [
            'mode' => $fullSync ? 'full' : 'incremental',
            'scan' => ['scanned' => 0, 'unchanged' => 0],
            'actions' => [
                'created' => 0,
                'updated' => 0,
                'hidden' => 0,
                'unhidden' => 0,
                'skipped_legacy' => 0,
                'skipped_validation' => 0,
                'skipped_quota' => 0,
                'errors' => [],
            ],
            'limits' => $this->createLimiter->snapshot($maxCreatesPerRun),
            'pending' => [
                'create' => [],
                'update' => [],
                'hide' => [],
                'unhide' => [],
            ],
            'waves' => 0,
        ];
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
    private function completeJob($source, ApiImportJob $job, $syncStartedAt, array $stats): void
    {
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
