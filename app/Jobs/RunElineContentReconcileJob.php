<?php

namespace App\Jobs;

use App\Models\ApiSource;
use App\Services\Eline\ElineSyncOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunElineContentReconcileJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public function __construct(
        public ?ApiSource $apiSource = null,
    ) {
        $this->onQueue('sync');
    }

    public function handle(ElineSyncOrchestrator $orchestrator): void
    {
        $orchestrator->runContentReconcile($this->apiSource);
    }
}
