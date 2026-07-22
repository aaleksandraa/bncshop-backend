<?php

namespace App\Services\Olx;

use App\Models\ApiImportJob;
use App\Models\ApiSource;
use Illuminate\Support\Carbon;

class OlxExportHealthChecker
{
    public function __construct(
        private readonly OlxSyncSettings $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $source = $this->settings->apiSource();
        $latestJob = $source
            ? ApiImportJob::query()
                ->where('api_source_id', $source->id)
                ->whereIn('type', ['olx_incremental', 'olx_full'])
                ->latest()
                ->first()
            : null;

        $runningJob = $source
            ? ApiImportJob::query()
                ->where('api_source_id', $source->id)
                ->whereIn('type', ['olx_incremental', 'olx_full'])
                ->where('status', 'running')
                ->latest()
                ->first()
            : null;

        $nextScheduledAt = $this->nextScheduledAtAfter($source?->last_successful_sync_at ?? now());
        $isOverdue = $this->isOverdue($source, $runningJob !== null);

        return [
            'source' => $source,
            'export_enabled' => $this->settings->isEnabled(),
            'auto_sync_enabled' => $this->settings->autoSyncEnabled(),
            'has_credentials' => $this->settings->hasCredentials(),
            'sync_times' => $this->settings->all()['sync_times'] ?? [],
            'next_scheduled_at' => $nextScheduledAt,
            'is_overdue' => $isOverdue,
            'overdue_since' => $isOverdue ? $nextScheduledAt : null,
            'overdue_human' => $isOverdue && $nextScheduledAt
                ? $nextScheduledAt->diffForHumans(now(), true)
                : null,
            'has_running_job' => $runningJob !== null,
            'running_job' => $runningJob ? [
                'id' => $runningJob->id,
                'type' => $runningJob->type,
                'started_at' => $runningJob->started_at,
                'running_for' => $runningJob->started_at?->diffForHumans(now(), true),
            ] : null,
            'latest_job' => $latestJob ? [
                'id' => $latestJob->id,
                'type' => $latestJob->type,
                'status' => $latestJob->status,
                'started_at' => $latestJob->started_at,
                'completed_at' => $latestJob->completed_at,
                'error_message' => $latestJob->error_message,
            ] : null,
            'issues' => $this->detectIssues($source, $isOverdue, $runningJob),
        ];
    }

    public function nextScheduledAtAfter(?Carbon $after = null): ?Carbon
    {
        $after = $after ?? now();
        $times = $this->normalizedSyncTimes();

        if ($times === []) {
            return null;
        }

        foreach ($times as $time) {
            $slot = $this->slotOnDate($after->copy()->startOfDay(), $time);

            if ($slot->gt($after)) {
                return $slot;
            }
        }

        return $this->slotOnDate($after->copy()->addDay()->startOfDay(), $times[0]);
    }

    private function isOverdue(?ApiSource $source, bool $hasRunningJob): bool
    {
        if (! $this->settings->isEnabled() || ! $this->settings->autoSyncEnabled()) {
            return false;
        }

        if ($source === null || ! $source->is_active || $hasRunningJob) {
            return false;
        }

        $reference = $source->last_successful_sync_at ?? now()->startOfDay()->subSecond();
        $dueAt = $this->nextScheduledAtAfter($reference);

        return $dueAt !== null && now()->gt($dueAt);
    }

    /**
     * @return list<string>
     */
    private function detectIssues(?ApiSource $source, bool $isOverdue, ?ApiImportJob $runningJob): array
    {
        $issues = [];

        if (! $this->settings->isEnabled()) {
            $issues[] = 'OLX export je isključen u postavkama.';
        }

        if (! $this->settings->autoSyncEnabled()) {
            $issues[] = 'OLX automatski sync je isključen u postavkama.';
        }

        if (! $this->settings->hasCredentials()) {
            $issues[] = 'OLX kredencijali nisu konfigurirani.';
        }

        if ($source === null) {
            $issues[] = 'OLX ApiSource zapis ne postoji.';
        } elseif (! $source->is_active) {
            $issues[] = 'OLX ApiSource nije aktivan.';
        }

        if ($isOverdue) {
            $issues[] = 'OLX export je zakasnio — provjerite da li cron pokreće bnc:sync-olx-scheduled i da li queue worker radi na sync redu.';
        }

        if ($runningJob !== null && $runningJob->started_at?->lt(now()->subHours(3))) {
            $issues[] = "OLX job #{$runningJob->id} je u statusu running predugo — moguće zaglavljen worker.";
        }

        if ($source?->connection_status === 'error' && $source->last_error) {
            $issues[] = 'Zadnja greška exporta: '.$source->last_error;
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function normalizedSyncTimes(): array
    {
        $times = $this->settings->all()['sync_times'] ?? [];

        return collect($times)
            ->filter(fn ($time): bool => is_string($time) && preg_match('/^\d{2}:\d{2}$/', $time) === 1)
            ->sort()
            ->values()
            ->all();
    }

    private function slotOnDate(Carbon $date, string $time): Carbon
    {
        [$hour, $minute] = array_map(intval(...), explode(':', $time, 2));

        return $date->copy()->setTime($hour, $minute, 0);
    }
}
