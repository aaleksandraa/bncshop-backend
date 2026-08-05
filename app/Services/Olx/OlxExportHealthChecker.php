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

        $reference = $source?->last_successful_sync_at ?? now();
        $nextScheduledAt = $this->nextScheduledAtAfter(now());
        $isOverdue = $this->isOverdue($source, $runningJob !== null);
        $staleImportPipelineError = $this->isStaleImportPipelineError($source?->last_error);
        $wrongPipelineJobs = $source ? $this->wrongPipelineJobsSince($source, $reference) : collect();
        $olxJobSinceDue = $source && $isOverdue && $nextScheduledAt
            ? $this->olxJobSince($source, $nextScheduledAt)
            : null;

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
            'stale_import_pipeline_error' => $staleImportPipelineError,
            'wrong_pipeline_job_count' => $wrongPipelineJobs->count(),
            'olx_job_since_due' => $olxJobSinceDue,
            'has_running_job' => $runningJob !== null,
            'running_job' => $runningJob ? [
                'id' => $runningJob->id,
                'type' => $runningJob->type,
                'started_at' => $runningJob->started_at,
                'running_for' => $runningJob->started_at?->diffForHumans(now(), true),
                'stats' => $runningJob->stats,
            ] : null,
            'latest_job' => $latestJob ? [
                'id' => $latestJob->id,
                'type' => $latestJob->type,
                'status' => $latestJob->status,
                'started_at' => $latestJob->started_at,
                'completed_at' => $latestJob->completed_at,
                'error_message' => $latestJob->error_message,
            ] : null,
            'issues' => $this->detectIssues(
                $source,
                $isOverdue,
                $runningJob,
                $staleImportPipelineError,
                $wrongPipelineJobs->count(),
                $olxJobSinceDue,
            ),
        ];
    }

    public function healStaleConnectionState(?ApiSource $source): bool
    {
        if ($source === null || ! $this->isStaleImportPipelineError($source->last_error)) {
            return false;
        }

        $latestOlxJob = ApiImportJob::query()
            ->where('api_source_id', $source->id)
            ->whereIn('type', ['olx_incremental', 'olx_full'])
            ->latest()
            ->first();

        if ($latestOlxJob === null || $latestOlxJob->status !== 'completed') {
            return false;
        }

        $source->update([
            'connection_status' => 'connected',
            'last_error' => null,
        ]);

        return true;
    }

    public function isStaleImportPipelineError(?string $lastError): bool
    {
        if ($lastError === null || $lastError === '') {
            return false;
        }

        return str_starts_with($lastError, 'Login failed:')
            || str_contains($lastError, '/api/auth/login');
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
    private function detectIssues(
        ?ApiSource $source,
        bool $isOverdue,
        ?ApiImportJob $runningJob,
        bool $staleImportPipelineError,
        int $wrongPipelineJobCount,
        ?ApiImportJob $olxJobSinceDue,
    ): array {
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

        if ($staleImportPipelineError) {
            $issues[] = 'Connection status drži zastarjelu grešku iz pogrešnog A1 import pipeline-a (RunApiSyncJob /api/auth/login). Pokrenite: php artisan bnc:sync-diagnose --heal-olx';
        } elseif ($source?->connection_status === 'error' && $source->last_error) {
            $issues[] = 'Zadnja greška exporta: '.$source->last_error;
        }

        if ($wrongPipelineJobCount > 0) {
            $issues[] = "Pronađeno {$wrongPipelineJobCount} neuspjelih A1 import job(ova) na OLX izvoru (prije fixa). Ignorirati — OLX koristi RunOlxSyncJob.";
        }

        if ($isOverdue) {
            if ($olxJobSinceDue === null) {
                $issues[] = 'OLX export je zakasnio i nijedan OLX job nije pokrenut nakon planiranog termina — provjerite da li cron pokreće schedule:run i da li Horizon/worker sluša sync red.';
            } elseif ($olxJobSinceDue->status === 'failed') {
                $issues[] = 'Zadnji OLX job nakon planiranog termina nije uspio: #'
                    .$olxJobSinceDue->id.' — '.($olxJobSinceDue->error_message ?? 'nema poruke');
            } else {
                $issues[] = 'OLX export je zakasnio — provjerite da li cron pokreće bnc:sync-olx-scheduled i da li queue worker radi na sync redu.';
            }

            $issues[] = 'Ručno pokretanje: php artisan bnc:sync-olx';
        }

        if ($runningJob !== null && $runningJob->started_at?->lt(now()->subHours(3))) {
            $issues[] = "OLX job #{$runningJob->id} je u statusu running predugo — moguće zaglavljen worker.";
        }

        return $issues;
    }

    /**
     * @return \Illuminate\Support\Collection<int, ApiImportJob>
     */
    private function wrongPipelineJobsSince(ApiSource $source, Carbon $since): \Illuminate\Support\Collection
    {
        return ApiImportJob::query()
            ->where('api_source_id', $source->id)
            ->whereIn('type', ['incremental', 'full'])
            ->where('status', 'failed')
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
    }

    private function olxJobSince(ApiSource $source, Carbon $since): ?ApiImportJob
    {
        return ApiImportJob::query()
            ->where('api_source_id', $source->id)
            ->whereIn('type', ['olx_incremental', 'olx_full'])
            ->where('created_at', '>=', $since)
            ->latest()
            ->first();
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
