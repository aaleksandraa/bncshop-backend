<?php

namespace App\Jobs;

use App\Services\Olx\OlxSyncOrchestrator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunOlxSyncJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

    public int $uniqueFor = 3600;

    public function __construct(
        public bool $fullSync = false,
        public ?int $productId = null,
        public ?int $maxCreatesPerRun = null,
    ) {
        $this->onQueue('sync');

        if ($maxCreatesPerRun !== null && $maxCreatesPerRun >= 300) {
            $this->timeout = 14400;
        }
    }

    public function uniqueId(): string
    {
        return 'olx-sync-'.($this->productId ?? 'all').'-'.($this->fullSync ? 'full' : 'incremental');
    }

    public function handle(OlxSyncOrchestrator $orchestrator): void
    {
        $orchestrator->run($this->fullSync, $this->productId, $this->maxCreatesPerRun);
    }
}
