<?php

namespace App\Services\Sync;

use App\Models\ApiImportJob;
use App\Models\ApiSource;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class A1SyncSettings
{
    /** @var list<int> */
    public const PRESET_INTERVALS = [15, 30, 60, 120, 240, 480];

    public function resolveSource(): ?ApiSource
    {
        return ApiSource::query()
            ->where('target_system_code', config('bnc.a1_api_target_system_code', 'bnc-shop'))
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $source = $this->resolveSource();

        if ($source === null) {
            return [
                'source_exists' => false,
                'auto_sync_enabled' => true,
                'interval_preset' => '60',
                'sync_interval_minutes' => 60,
            ];
        }

        $intervalMinutes = max(1, (int) ($source->sync_interval_minutes ?? 60));

        return [
            'source_exists' => true,
            'auto_sync_enabled' => (bool) $source->auto_sync_enabled,
            'interval_preset' => $this->resolvePreset($intervalMinutes),
            'sync_interval_minutes' => $intervalMinutes,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): ApiSource
    {
        $source = $this->resolveSource();

        if ($source === null) {
            throw new ModelNotFoundException('A1 Technoshop API izvor nije pronađen.');
        }

        $intervalMinutes = $this->resolveIntervalMinutes($data);

        $source->update([
            'auto_sync_enabled' => (bool) ($data['auto_sync_enabled'] ?? true),
            'sync_interval_minutes' => $intervalMinutes,
        ]);

        return $source->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $source = $this->resolveSource();

        if ($source === null) {
            return [
                'source_exists' => false,
            ];
        }

        $latestJob = ApiImportJob::query()
            ->where('api_source_id', $source->id)
            ->latest()
            ->first();

        $health = app(SyncHealthChecker::class)->forSource($source);

        return [
            'source_exists' => true,
            'source_id' => $source->id,
            'name' => $source->name,
            'connection_status' => $source->connection_status,
            'last_error' => $source->last_error,
            'last_successful_sync_at' => $source->last_successful_sync_at,
            'next_sync_at' => $health['next_sync_at'],
            'is_overdue' => $health['is_overdue'],
            'overdue_human' => $health['overdue_human'],
            'auto_sync_enabled' => (bool) $source->auto_sync_enabled,
            'sync_interval_minutes' => $source->sync_interval_minutes,
            'is_due' => $health['is_due'],
            'has_running_job' => $health['has_running_job'],
            'running_job' => $health['running_job'],
            'issues' => $health['issues'],
            'latest_job' => $latestJob ? [
                'id' => $latestJob->id,
                'type' => $latestJob->type,
                'status' => $latestJob->status,
                'started_at' => $latestJob->started_at,
                'completed_at' => $latestJob->completed_at,
                'stats' => $latestJob->stats,
            ] : null,
        ];
    }

    public function resolvePreset(int $minutes): string
    {
        return in_array($minutes, self::PRESET_INTERVALS, true)
            ? (string) $minutes
            : 'custom';
    }

    /**
     * @return array<string, string>
     */
    public function presetOptions(): array
    {
        $options = [];

        foreach (self::PRESET_INTERVALS as $minutes) {
            $options[(string) $minutes] = $this->formatIntervalLabel($minutes);
        }

        $options['custom'] = 'Prilagođeno';

        return $options;
    }

    public function formatIntervalLabel(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} min";
        }

        if ($minutes % 60 === 0) {
            $hours = (int) ($minutes / 60);

            return $hours === 1 ? '1 sat' : "{$hours} sata";
        }

        return "{$minutes} min";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveIntervalMinutes(array $data): int
    {
        $preset = (string) ($data['interval_preset'] ?? '60');

        if ($preset !== 'custom') {
            return max(1, min(1440, (int) $preset));
        }

        return max(1, min(1440, (int) ($data['sync_interval_minutes'] ?? 60)));
    }
}
