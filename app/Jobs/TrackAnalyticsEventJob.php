<?php

namespace App\Jobs;

use App\Services\Analytics\AnalyticsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TrackAnalyticsEventJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $eventType,
        public array $metadata = [],
        public ?int $userId = null,
        public ?string $sessionId = null,
    ) {
        $this->onQueue('analytics');
    }

    public function handle(AnalyticsService $analyticsService): void
    {
        $analyticsService->track(
            $this->eventType,
            $this->metadata,
            $this->userId,
            $this->sessionId,
        );
    }
}
