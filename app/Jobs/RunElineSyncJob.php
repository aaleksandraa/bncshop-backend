<?php

namespace App\Jobs;

use App\Models\ApiSource;
use App\Services\Eline\ElineSyncOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunElineSyncJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public function __construct(
        public ?ApiSource $apiSource = null,
        public bool $fullSync = false,
        public bool $refreshCategories = false,
    ) {
        $this->onQueue('sync');
    }

    public function handle(ElineSyncOrchestrator $orchestrator): void
    {
        $orchestrator->run(
            $this->apiSource,
            $this->fullSync,
            $this->refreshCategories,
        );
    }
}
