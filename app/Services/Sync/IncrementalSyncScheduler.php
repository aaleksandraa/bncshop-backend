<?php

namespace App\Services\Sync;

use App\Models\ApiImportJob;
use App\Models\ApiSource;
use Illuminate\Support\Collection;

class IncrementalSyncScheduler
{
    /**
     * @return Collection<int, ApiSource>
     */
    public function dueSources(): Collection
    {
        return ApiSource::query()
            ->a1Integration()
            ->where('is_active', true)
            ->where('auto_sync_enabled', true)
            ->get()
            ->filter(fn (ApiSource $source): bool => $this->isDue($source));
    }

    public function isDue(ApiSource $source): bool
    {
        if (! $source->usesIntegrationApiImport()) {
            return false;
        }

        if (! $source->is_active) {
            return false;
        }

        if (! $source->auto_sync_enabled) {
            return false;
        }

        if ($source->last_successful_sync_at === null) {
            return false;
        }

        if ($this->hasRunningJob($source)) {
            return false;
        }

        if ($this->hasRecentFailure($source)) {
            return false;
        }

        $intervalMinutes = max(1, (int) ($source->sync_interval_minutes ?? 60));

        return $source->last_successful_sync_at
            ->copy()
            ->addMinutes($intervalMinutes)
            ->lte(now());
    }

    public function hasRunningJob(ApiSource $source): bool
    {
        return ApiImportJob::query()
            ->where('api_source_id', $source->id)
            ->where('status', 'running')
            ->exists();
    }

    public function hasRecentFailure(ApiSource $source): bool
    {
        $cooldownMinutes = max(1, (int) config('bnc.a1_sync_failure_cooldown_minutes', 30));

        $latestFailedJob = ApiImportJob::query()
            ->where('api_source_id', $source->id)
            ->where('status', 'failed')
            ->latest('completed_at')
            ->first();

        if ($latestFailedJob === null || $latestFailedJob->completed_at === null) {
            return false;
        }

        $latestSuccessfulJob = ApiImportJob::query()
            ->where('api_source_id', $source->id)
            ->where('status', 'completed')
            ->latest('completed_at')
            ->first();

        if ($latestSuccessfulJob !== null
            && $latestSuccessfulJob->completed_at !== null
            && $latestSuccessfulJob->completed_at->gte($latestFailedJob->completed_at)) {
            return false;
        }

        return $latestFailedJob->completed_at->gt(now()->subMinutes($cooldownMinutes));
    }

    public function nextSyncAt(ApiSource $source): ?\Illuminate\Support\Carbon
    {
        if ($source->last_successful_sync_at === null) {
            return null;
        }

        $intervalMinutes = max(1, (int) ($source->sync_interval_minutes ?? 60));

        return $source->last_successful_sync_at->copy()->addMinutes($intervalMinutes);
    }
}
