<?php

namespace App\Services\Eline;

use App\Jobs\ReindexProductsJob;
use App\Models\ApiImportJob;
use App\Models\ApiSource;
use App\Models\Product;
use App\Services\Catalog\ProductReadCache;
use App\Services\Sync\ImportJobChangeLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class ElineSyncOrchestrator
{
    public function __construct(
        private readonly ElineApiClient $client,
        private readonly ElineCategoryDiscoveryService $discoveryService,
        private readonly ElineChangeDetector $changeDetector,
        private readonly ElineProductImporter $productImporter,
        private readonly ProductReadCache $productReadCache,
        private readonly ImportJobChangeLogger $changeLogger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(
        ?ApiSource $source = null,
        bool $fullSync = false,
        bool $refreshCategories = false,
    ): array {
        $source ??= $this->resolveSource();
        $syncStartedAt = now();

        $job = ApiImportJob::query()->create([
            'api_source_id' => $source->id,
            'type' => $fullSync ? 'eline_full' : 'eline_incremental',
            'status' => 'running',
            'sync_started_at' => $syncStartedAt,
            'started_at' => now(),
        ]);

        $stats = [
            'mode' => $fullSync ? 'full' : 'incremental',
            'discovery' => ['categories' => 0, 'mappings_created' => 0],
            'scan' => ['scanned' => 0, 'unchanged' => 0, 'new_items' => 0, 'modified_items' => 0],
            'products' => ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []],
        ];

        try {
            if ($refreshCategories || $fullSync) {
                $stats['discovery'] = $this->discoveryService->discover();
            }

            $artikli = $this->client->fetchArtikli();
            $cjenovnici = $this->client->fetchCjenovnici();
            $priceMap = $this->client->buildPriceMap($cjenovnici);
            unset($cjenovnici);

            $mappings = $this->discoveryService->enabledMappingsByCategoryName();

            if ($fullSync) {
                $itemsToImport = collect();
                $allFeedSifre = [];

                foreach ($this->client->mergeProductDataInChunks($artikli, $priceMap) as $chunk) {
                    $itemsToImport = $itemsToImport->concat($chunk);
                    $allFeedSifre = array_merge(
                        $allFeedSifre,
                        $this->collectMappedFeedSifre($chunk, $mappings),
                    );
                }

                unset($artikli, $priceMap);

                $stats['scan'] = [
                    'scanned' => $itemsToImport->count(),
                    'unchanged' => 0,
                    'new_items' => 0,
                    'modified_items' => 0,
                ];
            } else {
                $changed = collect();
                $allFeedSifre = [];
                $scanned = 0;
                $unchanged = 0;
                $newItems = 0;
                $modifiedItems = 0;

                foreach ($this->client->mergeProductDataInChunks($artikli, $priceMap) as $chunk) {
                    $detection = $this->changeDetector->detect($chunk, $mappings);
                    $changed = $changed->concat($detection['changed']);
                    $allFeedSifre = array_merge($allFeedSifre, $detection['all_feed_sifre']);
                    $scanned += $detection['scanned'];
                    $unchanged += $detection['unchanged'];
                    $newItems += $detection['new_items'];
                    $modifiedItems += $detection['modified_items'];
                }

                unset($artikli, $priceMap);

                $itemsToImport = $changed;
                $stats['scan'] = [
                    'scanned' => $scanned,
                    'unchanged' => $unchanged,
                    'new_items' => $newItems,
                    'modified_items' => $modifiedItems,
                ];
            }

            $stats['products'] = $this->productImporter->importMany(
                $itemsToImport,
                $mappings,
                $source,
                $allFeedSifre,
                $job,
                $this->changeLogger,
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

            $this->productReadCache->flushAll();

            $reindexIds = Product::query()
                ->fromEline()
                ->when(! $fullSync, fn ($query) => $query->whereIn(
                    'eline_sifra',
                    $itemsToImport->pluck('sifra')->filter()->all(),
                ))
                ->pluck('id')
                ->all();

            if ($reindexIds !== []) {
                ReindexProductsJob::dispatch($reindexIds);
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
                'stats' => array_merge($stats, ['error' => $e->getMessage()]),
            ]);

            throw $e;
        }
    }

    public function testConnection(): bool
    {
        $this->client->fetchArtikli();

        return true;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  Collection<string, ElineCategoryMapping>  $mappings
     * @return array<int, string>
     */
    private function collectMappedFeedSifre(Collection $items, Collection $mappings): array
    {
        return $items
            ->filter(function (array $item) use ($mappings): bool {
                $category = trim((string) ($item['eline_category'] ?? ''));

                if ($category === '') {
                    return false;
                }

                $mapping = $mappings->get($category);

                return $mapping !== null
                    && $mapping->is_enabled
                    && $mapping->category_id !== null;
            })
            ->pluck('sifra')
            ->map(fn ($sifra): string => (string) $sifra)
            ->all();
    }

    private function resolveSource(): ApiSource
    {
        $source = ApiSource::query()
            ->where('target_system_code', 'eline')
            ->where('is_active', true)
            ->first();

        if ($source === null) {
            throw new \RuntimeException('Active eLine API source is not configured.');
        }

        return $source;
    }
}
