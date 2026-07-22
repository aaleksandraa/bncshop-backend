<?php

namespace App\Console\Commands;

use App\Jobs\RunApiSyncJob;
use App\Models\ApiSource;
use App\Services\Sync\SyncOrchestrator;
use Illuminate\Console\Command;

class SyncIncrementalCommand extends Command
{
    protected $signature = 'bnc:sync-incremental
                            {source? : API source ID or name}
                            {--sync : Run synchronously instead of queue}';

    protected $description = 'Run an incremental API sync for one or all active sources';

    public function handle(SyncOrchestrator $orchestrator): int
    {
        $sources = $this->resolveSources();

        if ($sources->isEmpty()) {
            $this->error('No active API sources found.');

            return self::FAILURE;
        }

        foreach ($sources as $source) {
            if (! $source->usesIntegrationApiImport()) {
                $this->error($this->unsupportedSourceMessage($source));

                continue;
            }

            if ($source->last_successful_sync_at === null) {
                $this->error("Source #{$source->id} ({$source->name}): last_successful_sync_at is empty. Run full sync first.");

                continue;
            }

            $this->info("Incremental sync for source #{$source->id} ({$source->name})");

            if ($this->option('sync')) {
                $stats = $orchestrator->run($source, fullSync: false);
                $products = $stats['products'] ?? [];
                $this->info(sprintf(
                    'Imported products: %d (created: %d, updated: %d, deactivated: %d)',
                    $products['imported'] ?? 0,
                    $products['created'] ?? 0,
                    $products['updated'] ?? 0,
                    $products['deactivated'] ?? 0,
                ));
            } else {
                RunApiSyncJob::dispatch($source, fullSync: false, skipMetadata: true);
                $this->info('Incremental sync job dispatched.');
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
