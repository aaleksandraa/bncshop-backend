<?php

namespace App\Services\Ops;

use App\Models\ApiImportJob;
use App\Models\ApiSource;
use App\Models\AnalyticsEvent;
use App\Services\Catalog\ProductReadCache;
use App\Services\Sync\SyncHealthChecker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Meilisearch\Client as MeilisearchClient;
use Throwable;

class PerfHealthChecker
{
    public function __construct(
        private readonly SyncHealthChecker $syncHealth,
        private readonly ProductReadCache $productReadCache,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $infra = $this->syncHealth->infrastructure();
        $issues = [];

        $system = $this->systemMetrics();
        $services = $this->serviceChecks();
        $horizon = $this->horizonMetrics();
        $queues = $this->queueMetrics($infra, $horizon);
        $sync = $this->syncSummary();
        $analytics = $this->analyticsMetrics();

        $issues = array_merge(
            $issues,
            $this->issuesFromServices($services),
            $this->issuesFromHorizon($horizon),
            $this->issuesFromQueues($queues, $infra),
            $this->issuesFromSync($sync),
            $this->issuesFromSystem($system),
        );

        $issues = $this->dedupeIssues($issues);

        $status = $this->resolveStatus($issues);

        return [
            'generated_at' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'summary' => [
                'status' => $status,
                'issue_count' => count($issues),
                'fail_count' => count(array_filter($issues, fn (array $issue): bool => $issue['level'] === 'fail')),
                'warn_count' => count(array_filter($issues, fn (array $issue): bool => $issue['level'] === 'warn')),
            ],
            'system' => $system,
            'services' => $services,
            'horizon' => $horizon,
            'queues' => $queues,
            'sync' => $sync,
            'analytics' => $analytics,
            'issues' => $issues,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function systemMetrics(): array
    {
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;
        $storagePath = storage_path();
        $diskFree = is_dir($storagePath) ? @disk_free_space($storagePath) : false;
        $diskTotal = is_dir($storagePath) ? @disk_total_space($storagePath) : false;

        return [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit') ?: null,
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 1),
            'load_average' => is_array($load) ? [
                '1m' => round($load[0], 2),
                '5m' => round($load[1], 2),
                '15m' => round($load[2], 2),
            ] : null,
            'storage_free_gb' => $diskFree !== false ? round($diskFree / 1024 / 1024 / 1024, 2) : null,
            'storage_total_gb' => $diskTotal !== false ? round($diskTotal / 1024 / 1024 / 1024, 2) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceChecks(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'cache' => $this->checkCache(),
            'meilisearch' => $this->checkMeilisearch(),
            'cache_tags' => [
                'ok' => $this->productReadCache->supportsTags(),
                'message' => $this->productReadCache->supportsTags()
                    ? 'enabled'
                    : 'disabled — set CACHE_STORE=redis',
            ],
        ];
    }

    /**
     * @return array{ok: bool, message: string, latency_ms: float|null}
     */
    private function checkDatabase(): array
    {
        $started = microtime(true);

        try {
            DB::connection()->getPdo();
            DB::select('select 1 as ok');

            return [
                'ok' => true,
                'message' => 'connected',
                'latency_ms' => round((microtime(true) - $started) * 1000, 1),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'latency_ms' => null,
            ];
        }
    }

    /**
     * @return array{ok: bool, message: string, latency_ms: float|null}
     */
    private function checkRedis(): array
    {
        $started = microtime(true);

        try {
            Cache::put('bnc:perf-check', 'ok', 10);
            $value = Cache::get('bnc:perf-check');

            return [
                'ok' => $value === 'ok',
                'message' => $value === 'ok' ? 'connected' : 'read/write failed',
                'latency_ms' => round((microtime(true) - $started) * 1000, 1),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'latency_ms' => null,
            ];
        }
    }

    /**
     * @return array{ok: bool, message: string, latency_ms: float|null}
     */
    private function checkCache(): array
    {
        $store = config('cache.default');

        return [
            'ok' => true,
            'message' => $store,
            'latency_ms' => null,
        ];
    }

    /**
     * @return array{ok: bool, message: string, latency_ms: float|null}
     */
    private function checkMeilisearch(): array
    {
        $host = config('scout.meilisearch.host');

        if (! $host || config('scout.driver') !== 'meilisearch') {
            return [
                'ok' => true,
                'message' => 'not configured',
                'latency_ms' => null,
            ];
        }

        $started = microtime(true);

        try {
            $client = new MeilisearchClient($host, config('scout.meilisearch.key'));
            $health = $client->health();

            return [
                'ok' => ($health['status'] ?? '') === 'available',
                'message' => $health['status'] ?? 'unknown',
                'latency_ms' => round((microtime(true) - $started) * 1000, 1),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'latency_ms' => null,
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function horizonMetrics(): array
    {
        try {
            $masters = app(MasterSupervisorRepository::class)->all();
        } catch (Throwable $e) {
            return [
                'available' => false,
                'status' => 'unknown',
                'message' => $e->getMessage(),
                'process_count' => null,
                'jobs_per_minute' => null,
                'recent_failed_jobs' => null,
                'workload' => [],
            ];
        }

        if ($masters === [] || $masters === null) {
            return [
                'available' => true,
                'status' => 'inactive',
                'message' => 'Horizon nije pokrenut (Supervisor/systemd?)',
                'process_count' => 0,
                'jobs_per_minute' => null,
                'recent_failed_jobs' => $this->safeHorizonMetric(fn () => app(JobRepository::class)->countRecentlyFailed()),
                'workload' => [],
            ];
        }

        $status = collect($masters)->every(fn ($master) => $master->status === 'paused') ? 'paused' : 'running';

        if (collect($masters)->contains(fn ($master) => $master->status === 'paused')) {
            $status = 'paused';
        }

        $processCount = collect(app(SupervisorRepository::class)->all())
            ->reduce(fn (int $carry, $supervisor): int => $carry + collect($supervisor->processes)->sum(), 0);

        return [
            'available' => true,
            'status' => $status,
            'message' => match ($status) {
                'running' => 'Horizon radi',
                'paused' => 'Horizon je pauziran',
                default => 'Horizon status nepoznat',
            },
            'process_count' => $processCount,
            'jobs_per_minute' => $this->safeHorizonMetric(fn () => app(MetricsRepository::class)->jobsProcessedPerMinute()),
            'recent_failed_jobs' => $this->safeHorizonMetric(fn () => app(JobRepository::class)->countRecentlyFailed()),
            'workload' => $this->safeHorizonMetric(fn () => app(WorkloadRepository::class)->get(), []),
        ];
    }

    /**
     * @param  array<string, mixed>  $infra
     * @param  array<string, mixed>  $horizon
     * @return array<string, mixed>
     */
    private function queueMetrics(array $infra, array $horizon): array
    {
        $thresholds = config('horizon.waits', []);
        $sizes = [
            'default' => $infra['default_queue_size'],
            'sync' => $infra['sync_queue_size'],
            'analytics' => $infra['analytics_queue_size'],
        ];

        $scoutSize = null;

        if (config('queue.default') === 'redis') {
            try {
                $scoutSize = (int) \Illuminate\Support\Facades\Redis::llen('queues:scout');
            } catch (Throwable) {
                $scoutSize = null;
            }
        }

        $sizes['scout'] = $scoutSize;

        $waitTimes = [];
        foreach ($horizon['workload'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = (string) ($item['name'] ?? '');
            $waitTimes[$name] = [
                'length' => (int) ($item['length'] ?? 0),
                'wait_sec' => (int) ($item['wait'] ?? 0),
                'processes' => (int) ($item['processes'] ?? 0),
            ];

            foreach ($item['split_queues'] ?? [] as $split) {
                if (! is_array($split)) {
                    continue;
                }

                $splitName = (string) ($split['name'] ?? '');
                $waitTimes[$splitName] = [
                    'length' => (int) ($split['length'] ?? 0),
                    'wait_sec' => (int) ($split['wait'] ?? 0),
                    'processes' => (int) ($item['processes'] ?? 0),
                ];
            }
        }

        return [
            'connection' => $infra['queue_connection'],
            'sizes' => $sizes,
            'wait_times' => $waitTimes,
            'thresholds' => $thresholds,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncSummary(): array
    {
        $sources = ApiSource::query()->a1Integration()->get();
        $runningJobs = ApiImportJob::query()
            ->where('status', 'running')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (ApiImportJob $job): array => [
                'id' => $job->id,
                'type' => $job->type,
                'source_id' => $job->api_source_id,
                'started_at' => $job->started_at?->toIso8601String(),
                'running_for' => $job->started_at?->diffForHumans(now(), true),
            ])
            ->all();

        $sourceReports = $sources->map(function (ApiSource $source): array {
            $report = $this->syncHealth->forSource($source);

            return [
                'id' => $source->id,
                'name' => $source->name,
                'is_overdue' => $report['is_overdue'],
                'has_running_job' => $report['has_running_job'],
                'issues' => $report['issues'],
            ];
        })->all();

        return [
            'running_jobs' => $runningJobs,
            'sources' => $sourceReports,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function analyticsMetrics(): array
    {
        try {
            $lastHour = AnalyticsEvent::query()
                ->where('created_at', '>=', now()->subHour())
                ->count();

            $lastDay = AnalyticsEvent::query()
                ->where('created_at', '>=', now()->subDay())
                ->count();
        } catch (Throwable) {
            return [
                'events_last_hour' => null,
                'events_last_24h' => null,
            ];
        }

        return [
            'events_last_hour' => $lastHour,
            'events_last_24h' => $lastDay,
        ];
    }

    /**
     * @param  array<string, mixed>  $services
     * @return list<array{level: string, message: string}>
     */
    private function issuesFromServices(array $services): array
    {
        $issues = [];

        foreach (['database', 'redis', 'meilisearch'] as $service) {
            $check = $services[$service] ?? null;

            if (! is_array($check) || ($check['ok'] ?? false)) {
                continue;
            }

            if ($service === 'meilisearch' && ($check['message'] ?? '') === 'not configured') {
                continue;
            }

            $issues[] = [
                'level' => 'fail',
                'message' => ucfirst($service).' nije dostupan: '.($check['message'] ?? 'unknown'),
            ];
        }

        if (! ($services['cache_tags']['ok'] ?? false) && app()->environment('production')) {
            $issues[] = [
                'level' => 'warn',
                'message' => 'Cache tags su isključeni — product cache se ne invalidira pouzdano.',
            ];
        }

        $dbLatency = $services['database']['latency_ms'] ?? null;
        if (is_numeric($dbLatency) && $dbLatency > 200) {
            $issues[] = [
                'level' => 'warn',
                'message' => "PostgreSQL odgovara sporo ({$dbLatency} ms).",
            ];
        }

        $redisLatency = $services['redis']['latency_ms'] ?? null;
        if (is_numeric($redisLatency) && $redisLatency > 50) {
            $issues[] = [
                'level' => 'warn',
                'message' => "Redis odgovara sporo ({$redisLatency} ms).",
            ];
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $horizon
     * @return list<array{level: string, message: string}>
     */
    private function issuesFromHorizon(array $horizon): array
    {
        $issues = [];

        if (($horizon['status'] ?? '') === 'inactive' && app()->environment('production')) {
            $issues[] = [
                'level' => 'fail',
                'message' => 'Horizon nije aktivan — queue jobovi (sync, mail, analytics) se ne obrađuju.',
            ];
        }

        if (($horizon['status'] ?? '') === 'paused') {
            $issues[] = [
                'level' => 'warn',
                'message' => 'Horizon je pauziran — novi jobovi čekaju.',
            ];
        }

        $failed = $horizon['recent_failed_jobs'] ?? null;
        if (is_int($failed) && $failed > 0) {
            $issues[] = [
                'level' => $failed >= 10 ? 'fail' : 'warn',
                'message' => "Horizon ima {$failed} nedavno neuspjelih job(ova).",
            ];
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $queues
     * @param  array<string, mixed>  $infra
     * @return list<array{level: string, message: string}>
     */
    private function issuesFromQueues(array $queues, array $infra): array
    {
        $issues = [];
        $sizes = $queues['sizes'] ?? [];
        $waitTimes = $queues['wait_times'] ?? [];
        $thresholds = $queues['thresholds'] ?? [];

        $sizeRules = [
            'sync' => ['warn' => 1, 'fail' => 10, 'label' => 'Sync queue'],
            'analytics' => ['warn' => 200, 'fail' => 1000, 'label' => 'Analytics queue'],
            'default' => ['warn' => 100, 'fail' => 500, 'label' => 'Default queue'],
            'scout' => ['warn' => 100, 'fail' => 500, 'label' => 'Scout queue'],
        ];

        foreach ($sizeRules as $queue => $rule) {
            $size = $sizes[$queue] ?? null;

            if (! is_int($size)) {
                continue;
            }

            if ($size >= $rule['fail']) {
                $issues[] = [
                    'level' => 'fail',
                    'message' => "{$rule['label']} ima {$size} job(ova) — veliki backlog.",
                ];
            } elseif ($size >= $rule['warn']) {
                $issues[] = [
                    'level' => 'warn',
                    'message' => "{$rule['label']} ima {$size} job(ova).",
                ];
            }
        }

        foreach ($waitTimes as $queueName => $data) {
            if (! is_array($data)) {
                continue;
            }

            $wait = (int) ($data['wait_sec'] ?? 0);
            $thresholdKey = 'redis:'.$queueName;
            $threshold = (int) ($thresholds[$thresholdKey] ?? 120);

            if ($wait >= $threshold) {
                $issues[] = [
                    'level' => 'warn',
                    'message' => "Queue {$queueName} čeka ~{$wait}s (prag {$threshold}s).",
                ];
            }
        }

        if (($infra['redis_ok'] ?? false) === false) {
            $issues[] = [
                'level' => 'fail',
                'message' => 'Redis ping nije uspio — queue i cache su ugroženi.',
            ];
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $sync
     * @return list<array{level: string, message: string}>
     */
    private function issuesFromSync(array $sync): array
    {
        $issues = [];

        foreach ($sync['sources'] ?? [] as $source) {
            if (! is_array($source)) {
                continue;
            }

            if ($source['is_overdue'] ?? false) {
                $issues[] = [
                    'level' => 'warn',
                    'message' => "Sync izvor {$source['name']} je zakasnio.",
                ];
            }

            foreach ($source['issues'] ?? [] as $issue) {
                if (! is_string($issue) || $issue === '') {
                    continue;
                }

                $issues[] = [
                    'level' => str_contains(strtolower($issue), 'redis') ? 'fail' : 'warn',
                    'message' => $issue,
                ];
            }
        }

        foreach ($sync['running_jobs'] ?? [] as $job) {
            if (! is_array($job)) {
                continue;
            }

            $issues[] = [
                'level' => 'info',
                'message' => "Aktivan sync job #{$job['id']} ({$job['type']}) — {$job['running_for']}.",
            ];
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $system
     * @return list<array{level: string, message: string}>
     */
    private function issuesFromSystem(array $system): array
    {
        $issues = [];
        $load = $system['load_average'] ?? null;

        if (is_array($load)) {
            $cpuCount = $this->cpuCount();
            $oneMinute = (float) ($load['1m'] ?? 0);

            if ($cpuCount > 0 && $oneMinute > ($cpuCount * 1.5)) {
                $issues[] = [
                    'level' => 'warn',
                    'message' => "Visok load average ({$oneMinute}) za {$cpuCount} CPU jezgara.",
                ];
            }
        }

        $freeGb = $system['storage_free_gb'] ?? null;
        if (is_numeric($freeGb) && $freeGb < 2) {
            $issues[] = [
                'level' => 'warn',
                'message' => "Malo slobodnog disk prostora na storage volumenu ({$freeGb} GB).",
            ];
        }

        return $issues;
    }

    /**
     * @param  list<array{level: string, message: string}>  $issues
     * @return list<array{level: string, message: string}>
     */
    private function dedupeIssues(array $issues): array
    {
        $seen = [];
        $deduped = [];

        foreach ($issues as $issue) {
            $key = ($issue['level'] ?? '').'|'.($issue['message'] ?? '');

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduped[] = $issue;
        }

        return $deduped;
    }

    /**
     * @param  list<array{level: string, message: string}>  $issues
     */
    private function resolveStatus(array $issues): string
    {
        if (collect($issues)->contains(fn (array $issue): bool => $issue['level'] === 'fail')) {
            return 'fail';
        }

        if (collect($issues)->contains(fn (array $issue): bool => $issue['level'] === 'warn')) {
            return 'warn';
        }

        return 'ok';
    }

    private function cpuCount(): int
    {
        if (! is_readable('/proc/cpuinfo')) {
            return 0;
        }

        $content = file_get_contents('/proc/cpuinfo');

        if ($content === false) {
            return 0;
        }

        return substr_count($content, 'processor');
    }

    /**
     * @template T
     * @param  callable(): T  $callback
     * @param  T|null  $default
     * @return T|null
     */
    private function safeHorizonMetric(callable $callback, mixed $default = null): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return $default;
        }
    }
}
