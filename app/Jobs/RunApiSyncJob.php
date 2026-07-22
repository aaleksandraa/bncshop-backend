<?php

namespace App\Jobs;

use App\Models\ApiSource;
use App\Services\Sync\SyncOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use InvalidArgumentException;

class RunApiSyncJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

    public function __construct(
        public ApiSource $apiSource,
        public bool $fullSync = false,
        public ?int $maxProductPages = null,
        public ?int $startProductPage = null,
        public bool $skipMetadata = false,
    ) {
        $this->onQueue('sync');
    }

    public function handle(SyncOrchestrator $orchestrator): void
    {
        if (! $this->apiSource->usesIntegrationApiImport()) {
            throw new InvalidArgumentException(sprintf(
                'API source #%d (%s) does not use IntegrationApiClient import. Use the dedicated sync pipeline instead.',
                $this->apiSource->id,
                $this->apiSource->name,
            ));
        }

        $orchestrator->run(
            $this->apiSource,
            $this->fullSync,
            $this->maxProductPages,
            $this->startProductPage,
            $this->skipMetadata,
        );
    }
}
