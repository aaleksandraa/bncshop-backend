<?php

namespace App\Jobs;

use App\Models\ApiSource;
use App\Services\Sync\SyncOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
        $orchestrator->run(
            $this->apiSource,
            $this->fullSync,
            $this->maxProductPages,
            $this->startProductPage,
            $this->skipMetadata,
        );
    }
}
