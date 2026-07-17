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
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(bool $fullSync = false, ?int $productId = null): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        if (! $this->settings->isEnabled()) {
            throw new \RuntimeException('OLX export is disabled in settings.');
        }

        $source = $this->settings->resolveSource();
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
                'errors' => [],
            ],
        ];

        try {
            $this->client->authenticate();

            if ($productId !== null) {
                $product = Product::query()->with(['category.parent', 'images', 'attributeValues.attributeDefinition', 'manufacturer'])->findOrFail($productId);
                $action = filled($product->olx_listing_id) ? 'update' : 'create';
                $result = $this->listingExporter->export($product, $action);
                $stats['actions'][$result['action'] === 'create' ? 'created' : 'updated'] = 1;
            } else {
                $detection = $this->changeDetector->detect($fullSync);
                $stats['scan'] = [
                    'scanned' => $detection['scanned'],
                    'unchanged' => $detection['unchanged'],
                ];

                $batchSize = max(1, (int) ($this->settings->all()['batch_size'] ?? 20));

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
                            try {
                                $result = $this->listingExporter->export($product, $setKey === 'unhide' ? 'unhide' : ($setKey === 'hide' ? 'hide' : $setKey));

                                if ($result['action'] === 'skipped_legacy') {
                                    $stats['actions']['skipped_legacy']++;
                                } else {
                                    $stats['actions'][$statKey]++;
                                }
                            } catch (Throwable $e) {
                                $message = $e->getMessage();

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
}
