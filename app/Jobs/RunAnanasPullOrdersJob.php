<?php

namespace App\Jobs;

use App\Services\Ananas\AnanasOrderPullService;
use App\Services\Ananas\AnanasSyncSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunAnanasPullOrdersJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public int $tries = 1;

    public function __construct(
        public bool $backfill = false,
        public ?string $sinceIso = null,
    ) {
        $this->onQueue('sync');
    }

    public function handle(AnanasOrderPullService $pullService, AnanasSyncSettings $settings): void
    {
        if (! $settings->isOrdersPullEnabled()) {
            return;
        }

        $since = $this->sinceIso !== null ? \Carbon\Carbon::parse($this->sinceIso) : null;
        $pullService->pull($this->backfill, $since);
    }
}
