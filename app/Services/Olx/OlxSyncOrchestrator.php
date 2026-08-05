<?php

namespace App\Services\Olx;

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
    public function run(bool $fullSync = false, ?int $productId = null, ?int $maxCreatesPerRun = null): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        if (! $this->settings->isEnabled()) {
            throw new \RuntimeException('OLX export is disabled in settings.');
        }

        $source = $this->settings->resolveSource();

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

        $stats = [
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
        ];

        $createQuota = (int) ($stats['limits']['allowed_this_run'] ?? 0);

        try {
            $this->client->authenticate();

            if ($productId !== null) {
                $product = Product::query()->with(['category.parent', 'images', 'attributeValues.attributeDefinition', 'manufacturer'])->findOrFail($productId);
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
            } else {
                $detection = $this->changeDetector->detect($fullSync);
                $stats['scan'] = [
                    'scanned' => $detection['scanned'],
                    'unchanged' => $detection['unchanged'],
                    'pending_create' => $detection['create']->count(),
                    'pending_update' => $detection['update']->count(),
                ];
                $this->flushJobStats($job, $stats);

                $batchSize = max(1, (int) ($this->settings->all()['batch_size'] ?? 20));
                $flushEvery = 5;

                foreach ([
                    'create' => 'created',
                    'update' => 'updated',
                    'hide' => 'hidden',
                    'unhide' => 'unhidden',
                ] as $setKey => $statKey) {
                    /** @var \Illuminate\Support\Collection<int, Product> $items */
                    $items = $detection[$setKey];

                    foreach ($items->chunk($batchSize) as $chunk) {
                        foreach ($chunk as $product) {
                            if ($setKey === 'create' && $createQuota <= 0) {
                                $stats['actions']['skipped_quota']++;

                                continue;
                            }

                            try {
                                $result = $this->listingExporter->export($product, $setKey === 'unhide' ? 'unhide' : ($setKey === 'hide' ? 'hide' : $setKey));

                                if ($result['action'] === 'skipped_legacy') {
                                    $stats['actions']['skipped_legacy']++;
                                } elseif ($setKey === 'create' && $result['action'] === 'create') {
                                    $this->createLimiter->recordCreate();
                                    $createQuota--;
                                    $stats['actions']['created']++;

                                    if ($stats['actions']['created'] % $flushEvery === 0) {
                                        $stats['limits'] = $this->createLimiter->snapshot($maxCreatesPerRun);
                                        $this->flushJobStats($job, $stats);
                                    }
                                } else {
                                    $stats['actions'][$statKey]++;
                                }
                            } catch (Throwable $e) {
                                $message = $e->getMessage();

                                if (OlxDailyCreateLimiter::isDailyLimitError($message)) {
                                    $createQuota = 0;
                                    $stats['actions']['skipped_quota']++;
                                    $stats['limits'] = $this->createLimiter->snapshot($maxCreatesPerRun);

                                    continue;
                                }

                                if (str_contains($message, 'Nedostaju obavezni OLX atributi')) {
                                    $stats['actions']['skipped_validation']++;
                                }

                                $stats['actions']['errors'][] = [
                                    'product_id' => $product->id,
                                    'action' => $setKey,
                                    'message' => $message,
                                ];
                            }
                        }
                    }
                }
            }

            $stats['limits'] = $this->createLimiter->snapshot($maxCreatesPerRun);

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

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function flushJobStats(ApiImportJob $job, array $stats): void
    {
        $job->update(['stats' => $stats]);
    }
}
