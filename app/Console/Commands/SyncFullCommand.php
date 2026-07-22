<?php

namespace App\Console\Commands;

use App\Jobs\RunApiSyncJob;
use App\Models\ApiSource;
use App\Services\Sync\SyncOrchestrator;
use Illuminate\Console\Command;

class SyncFullCommand extends Command
{
    protected $signature = 'bnc:sync-full
                            {source? : API source ID or name}
                            {--sync : Run synchronously instead of queue}
                            {--max-pages= : Limit product pages (pilot import)}
                            {--start-page= : Resume product sync from this page}
                            {--skip-metadata : Skip categories and attributes fetch}';

    protected $description = 'Run a full API sync for one or all active sources';

    public function handle(SyncOrchestrator $orchestrator): int
    {
        $sources = $this->resolveSources();

        if ($sources->isEmpty()) {
            $this->error('No active API sources found.');

            return self::FAILURE;
        }

        $maxPages = $this->option('max-pages') !== null ? (int) $this->option('max-pages') : null;
        $startPage = $this->option('start-page') !== null ? (int) $this->option('start-page') : null;
        $skipMetadata = (bool) $this->option('skip-metadata');

        foreach ($sources as $source) {
            if (! $source->usesIntegrationApiImport()) {
                $this->error($this->unsupportedSourceMessage($source));

                continue;
            }

            $this->info("Full sync for source #{$source->id} ({$source->name})");

            if ($this->option('sync')) {
                $stats = $orchestrator->run(
                    $source,
                    fullSync: true,
                    maxProductPages: $maxPages,
                    startProductPage: $startPage,
                    skipMetadata: $skipMetadata,
                );
                $this->table(['Metric', 'Value'], collect($stats)->flatMap(
                    fn ($value, $key) => is_array($value)
                        ? collect($value)->map(fn ($v, $k) => ["{$key}.{$k}", is_array($v) ? count($v).' items' : $v])
                        : collect([[$key, $value]])
                )->all());
            } else {
                RunApiSyncJob::dispatch(
                    $source,
                    fullSync: true,
                    maxProductPages: $maxPages,
                    startProductPage: $startPage,
                    skipMetadata: $skipMetadata,
                );
                $this->info('Full sync job dispatched.');
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, ApiSource>
     */
    private function resolveSources()
    {
        $sourceArg = $this->argument('source');

        if ($sourceArg === null) {
            return ApiSource::query()->a1Integration()->where('is_active', true)->get();
        }

        $source = ApiSource::query()
            ->where('is_active', true)
            ->where(function ($query) use ($sourceArg): void {
                $query->where('id', $sourceArg)->orWhere('name', $sourceArg);
            })
            ->get();

        if ($source->isEmpty()) {
            $this->error("Active API source '{$sourceArg}' not found.");
        }

        return $source;
    }

    private function unsupportedSourceMessage(ApiSource $source): string
    {
        return match ($source->target_system_code) {
            'olx' => "Source #{$source->id} ({$source->name}) koristi OLX export pipeline. Pokrenite: php artisan bnc:sync-olx",
            'eline' => "Source #{$source->id} ({$source->name}) koristi eLine pipeline. Pokrenite: php artisan bnc:sync-eline",
            default => "Source #{$source->id} ({$source->name}) nije podržan za IntegrationApiClient import.",
        };
    }
}
