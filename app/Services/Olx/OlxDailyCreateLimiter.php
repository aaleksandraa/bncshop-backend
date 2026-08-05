<?php

namespace App\Services\Olx;

use App\Models\ApiImportJob;
use Illuminate\Support\Facades\Cache;

class OlxDailyCreateLimiter
{
    private const CACHE_PREFIX = 'olx:daily_creates:';

    public function __construct(
        private readonly OlxSyncSettings $settings,
    ) {}

    public function dailyLimit(): int
    {
        return max(1, (int) ($this->settings->all()['daily_create_limit'] ?? config('bnc.olx_daily_create_limit', 350)));
    }

    public function maxPerRun(): int
    {
        return max(1, (int) ($this->settings->all()['max_creates_per_run'] ?? config('bnc.olx_max_creates_per_run', 175)));
    }

    public function createsToday(): int
    {
        $key = $this->cacheKey();
        $cached = Cache::get($key);

        if (is_int($cached)) {
            return $cached;
        }

        $fromJobs = $this->countCreatesFromJobsToday();
        Cache::put($key, $fromJobs, now()->endOfDay());

        return $fromJobs;
    }

    public function remainingToday(): int
    {
        return max(0, $this->dailyLimit() - $this->createsToday());
    }

    public function allowedThisRun(?int $maxPerRunOverride = null): int
    {
        $cap = max(1, $maxPerRunOverride ?? $this->maxPerRun());

        return min($this->remainingToday(), $cap);
    }

    public function canCreate(?int $maxPerRunOverride = null): bool
    {
        return $this->allowedThisRun($maxPerRunOverride) > 0;
    }

    public function recordCreate(): void
    {
        $key = $this->cacheKey();
        $current = Cache::get($key);

        if (! is_int($current)) {
            $current = $this->countCreatesFromJobsToday();
        }

        Cache::put($key, $current + 1, now()->endOfDay());
    }

    /**
     * @return array{daily_limit: int, creates_today: int, remaining_today: int, max_per_run: int, allowed_this_run: int}
     */
    public function snapshot(?int $maxPerRunOverride = null): array
    {
        $createsToday = $this->createsToday();
        $dailyLimit = $this->dailyLimit();
        $maxPerRun = max(1, $maxPerRunOverride ?? $this->maxPerRun());

        return [
            'daily_limit' => $dailyLimit,
            'creates_today' => $createsToday,
            'remaining_today' => max(0, $dailyLimit - $createsToday),
            'max_per_run' => $maxPerRun,
            'allowed_this_run' => $this->allowedThisRun($maxPerRunOverride),
        ];
    }

    public static function isDailyLimitError(string $message): bool
    {
        return str_contains($message, 'limit objave oglasa');
    }

    private function cacheKey(): string
    {
        return self::CACHE_PREFIX.now()->toDateString();
    }

    private function countCreatesFromJobsToday(): int
    {
        return (int) ApiImportJob::query()
            ->whereIn('type', ['olx_incremental', 'olx_full'])
            ->where('sync_started_at', '>=', now()->startOfDay())
            ->get()
            ->sum(fn (ApiImportJob $job): int => (int) (($job->stats['actions']['created'] ?? 0)));
    }
}
