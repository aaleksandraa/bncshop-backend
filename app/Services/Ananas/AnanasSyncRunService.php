<?php

namespace App\Services\Ananas;

use App\Models\AnanasSyncRun;
use Illuminate\Support\Facades\Cache;

class AnanasSyncRunService
{
    private const FAILED_ID_CAP = 50;

    private const STALE_MINUTES_CATALOG = 90;

    private const STALE_MINUTES_DEFAULT = 45;

    public function lockKey(string $jobKey): string
    {
        return 'ananas:daily:lock:'.$jobKey;
    }

    public function isJobLocked(string $jobKey): bool
    {
        return AnanasSyncRun::query()
            ->where('job_key', $jobKey)
            ->whereIn('status', [AnanasSyncRun::STATUS_QUEUED, AnanasSyncRun::STATUS_RUNNING])
            ->exists();
    }

    public function queueRun(
        string $jobKey,
        string $triggeredBy = 'cron',
        ?int $userId = null,
    ): ?AnanasSyncRun {
        if ($this->isJobLocked($jobKey)) {
            return null;
        }

        return AnanasSyncRun::query()->create([
            'job_key' => $jobKey,
            'status' => AnanasSyncRun::STATUS_QUEUED,
            'triggered_by' => $triggeredBy,
            'triggered_by_user_id' => $userId,
        ]);
    }

    public function markRunning(AnanasSyncRun $run): void
    {
        $run->update([
            'status' => AnanasSyncRun::STATUS_RUNNING,
            'started_at' => now(),
            'last_progress_at' => now(),
        ]);
    }

    public function heartbeat(AnanasSyncRun $run): void
    {
        $run->update(['last_progress_at' => now()]);
    }

    public function refreshCancel(AnanasSyncRun $run): AnanasSyncRun
    {
        return $run->fresh() ?? $run;
    }

    public function cancelRequested(AnanasSyncRun $run): bool
    {
        return $run->cancel_requested_at !== null;
    }

    public function requestCancel(AnanasSyncRun $run): void
    {
        $run->update(['cancel_requested_at' => now()]);
    }

    public function finishCancelled(AnanasSyncRun $run): void
    {
        $run->update([
            'status' => AnanasSyncRun::STATUS_CANCELLED,
            'finished_at' => now(),
        ]);
    }

    /**
     * @param  list<int|string>  $failedIds
     * @param  array<string, mixed>  $meta
     */
    public function finishSucceeded(
        AnanasSyncRun $run,
        int $updated = 0,
        int $skipped = 0,
        array $failedIds = [],
        ?string $errorMessage = null,
        array $meta = [],
    ): void {
        $failedIds = array_values(array_unique(array_map('strval', $failedIds)));
        $failedCount = count($failedIds);
        $sample = array_slice($failedIds, 0, self::FAILED_ID_CAP);

        $run->update([
            'status' => AnanasSyncRun::STATUS_SUCCEEDED,
            'updated_count' => $updated,
            'skipped_count' => $skipped,
            'failed_item_count' => $failedCount,
            'failed_inventory_ids' => $sample,
            'error_message' => $errorMessage,
            'meta' => array_merge($run->meta ?? [], $meta),
            'finished_at' => now(),
            'last_progress_at' => now(),
        ]);
    }

    public function finishFailed(AnanasSyncRun $run, string $message, array $meta = []): void
    {
        $run->update([
            'status' => AnanasSyncRun::STATUS_FAILED,
            'error_message' => $message,
            'meta' => array_merge($run->meta ?? [], $meta),
            'finished_at' => now(),
            'last_progress_at' => now(),
        ]);
    }

    public function finishSkipped(AnanasSyncRun $run, string $reason, array $meta = []): void
    {
        $run->update([
            'status' => AnanasSyncRun::STATUS_SKIPPED,
            'error_message' => $reason,
            'meta' => array_merge($run->meta ?? [], $meta),
            'finished_at' => now(),
        ]);
    }

    public function todaySucceeded(string $jobKey): bool
    {
        $start = AnanasDailyWindow::now()->startOfDay()->utc();
        $end = AnanasDailyWindow::now()->endOfDay()->utc();

        return AnanasSyncRun::query()
            ->where('job_key', $jobKey)
            ->where('status', AnanasSyncRun::STATUS_SUCCEEDED)
            ->whereBetween('finished_at', [$start, $end])
            ->exists();
    }

    public function todayRunWithStatus(string $jobKey, string $status): ?AnanasSyncRun
    {
        $start = AnanasDailyWindow::now()->startOfDay()->utc();
        $end = AnanasDailyWindow::now()->endOfDay()->utc();

        return AnanasSyncRun::query()
            ->where('job_key', $jobKey)
            ->where('status', $status)
            ->whereBetween('created_at', [$start, $end])
            ->latest('id')
            ->first();
    }

    public function recoverStaleRuns(): int
    {
        $recovered = 0;

        $runs = AnanasSyncRun::query()
            ->whereIn('status', [AnanasSyncRun::STATUS_QUEUED, AnanasSyncRun::STATUS_RUNNING])
            ->get();

        foreach ($runs as $run) {
            if (! $run instanceof AnanasSyncRun) {
                continue;
            }

            $limit = in_array($run->job_key, [AnanasSyncRun::JOB_CATALOG, AnanasSyncRun::JOB_PRICE_STOCK], true)
                ? self::STALE_MINUTES_CATALOG
                : self::STALE_MINUTES_DEFAULT;

            $last = $run->last_progress_at ?? $run->started_at ?? $run->created_at;

            if ($last !== null && $last->diffInMinutes(now()) < $limit) {
                continue;
            }

            $this->finishFailed($run, 'Stale run: no progress heartbeat within '.$limit.' minutes.');
            $recovered++;
        }

        return $recovered;
    }

    public function catalogSnapshotCacheKey(): string
    {
        return 'ananas:daily:catalog:'.AnanasDailyWindow::todayDateString();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function storeCatalogSnapshot(array $snapshot): void
    {
        Cache::put($this->catalogSnapshotCacheKey(), $snapshot, now()->addHours(36));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function catalogSnapshot(): ?array
    {
        $data = Cache::get($this->catalogSnapshotCacheKey());

        return is_array($data) ? $data : null;
    }

    /**
     * @param  list<int|string>  $inventoryIds
     */
    public function setTakedownExcludeIds(array $inventoryIds): void
    {
        $key = 'ananas:takedown:exclude:'.AnanasDailyWindow::todayDateString();
        Cache::put($key, array_values(array_unique(array_map('strval', $inventoryIds))), now()->addHours(36));
    }

    /**
     * @return list<string>
     */
    public function takedownExcludeIds(): array
    {
        $key = 'ananas:takedown:exclude:'.AnanasDailyWindow::todayDateString();
        $ids = Cache::get($key, []);

        return is_array($ids) ? array_values(array_map('strval', $ids)) : [];
    }
}
