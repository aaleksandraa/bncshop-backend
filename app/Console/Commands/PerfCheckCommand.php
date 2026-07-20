<?php

namespace App\Console\Commands;

use App\Services\Ops\PerfHealthChecker;
use Illuminate\Console\Command;

class PerfCheckCommand extends Command
{
    protected $signature = 'bnc:perf-check
                            {--json : Ispiši pun izvještaj kao JSON}
                            {--clear-failed-history : Obriši Horizon recent_failed_jobs historiju iz Redis-a}';

    protected $description = 'Jedinstveni izvještaj o performansama servera, queue redova, Horizon-a i sync-a';

    public function handle(PerfHealthChecker $checker): int
    {
        if ($this->option('clear-failed-history')) {
            $cleared = $checker->clearFailedHistory();

            if ($cleared) {
                $this->info('Horizon recent_failed_jobs historija je obrisana.');
            } else {
                $this->warn('recent_failed_jobs nije pronađen ili Redis nije dostupan.');
            }
        }

        $report = $checker->report();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $this->exitCodeForSummary($report['summary'] ?? []);
        }

        $this->renderHumanReport($report);

        return $this->exitCodeForSummary($report['summary'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderHumanReport(array $report): void
    {
        $summary = $report['summary'] ?? [];
        $status = (string) ($summary['status'] ?? 'ok');

        $this->info('BNC Shop performance check');
        $this->line('Generisano: '.($report['generated_at'] ?? now()->toIso8601String()));
        $this->line('Okruženje: '.($report['environment'] ?? app()->environment()));
        $this->renderStatusLine('Ukupni status', $status, strtoupper($status));
        $this->newLine();

        $this->section('Sistem');
        $system = $report['system'] ?? [];
        $this->line('PHP: '.($system['php_version'] ?? '?').' | memory limit: '.($system['memory_limit'] ?? '?'));
        $this->line('PHP memory usage: '.($system['memory_usage_mb'] ?? '?').' MB');

        if (is_array($system['load_average'] ?? null)) {
            $load = $system['load_average'];
            $this->line(sprintf(
                'Load average: 1m=%s, 5m=%s, 15m=%s',
                $load['1m'] ?? '?',
                $load['5m'] ?? '?',
                $load['15m'] ?? '?',
            ));
        } else {
            $this->line('Load average: n/a (Windows ili /proc nedostupan)');
        }

        if (($system['storage_free_gb'] ?? null) !== null) {
            $this->line(sprintf(
                'Storage: %.2f GB free / %.2f GB total',
                $system['storage_free_gb'],
                $system['storage_total_gb'] ?? 0,
            ));
        }

        $this->newLine();
        $this->section('Servisi');
        foreach ($report['services'] ?? [] as $name => $check) {
            if (! is_array($check)) {
                continue;
            }

            $label = ucfirst(str_replace('_', ' ', (string) $name));
            $message = (string) ($check['message'] ?? '');
            $latency = isset($check['latency_ms']) ? " ({$check['latency_ms']} ms)" : '';
            $level = ($check['ok'] ?? false) ? 'ok' : 'fail';

            $this->renderStatusLine($label, $level, $message.$latency);
        }

        $this->newLine();
        $this->section('Horizon / queue workeri');
        $horizon = $report['horizon'] ?? [];
        $horizonLevel = match ($horizon['status'] ?? 'unknown') {
            'running' => 'ok',
            'paused' => 'warn',
            'inactive' => app()->environment('production') ? 'fail' : 'warn',
            default => 'warn',
        };
        $this->renderStatusLine('Horizon', $horizonLevel, (string) ($horizon['message'] ?? ''));
        $this->line('Worker procesi: '.($horizon['process_count'] ?? '?'));
        $this->line('Jobs/min: '.($horizon['jobs_per_minute'] ?? 'n/a'));
        $this->line('Failed queue: '.($horizon['total_failed_jobs'] ?? 'n/a'));
        $this->line('Failed historija (7d): '.($horizon['recent_failed_jobs'] ?? 'n/a'));

        $this->newLine();
        $this->section('Queue backlog');
        $queues = $report['queues'] ?? [];
        $this->line('Queue driver: '.($queues['connection'] ?? '?'));

        foreach ($queues['sizes'] ?? [] as $queue => $size) {
            $sizeLabel = is_int($size) ? (string) $size : 'n/a';
            $wait = $queues['wait_times'][$queue]['wait_sec'] ?? null;
            $waitLabel = is_numeric($wait) ? " | wait ~{$wait}s" : '';
            $this->line("  {$queue}: {$sizeLabel} job(ova){$waitLabel}");
        }

        $this->newLine();
        $this->section('Analytics');
        $analytics = $report['analytics'] ?? [];
        $this->line('Eventi (1h): '.($analytics['events_last_hour'] ?? 'n/a'));
        $this->line('Eventi (24h): '.($analytics['events_last_24h'] ?? 'n/a'));

        $this->newLine();
        $this->section('Sync');
        $sync = $report['sync'] ?? [];
        $runningJobs = $sync['running_jobs'] ?? [];

        if ($runningJobs === []) {
            $this->line('Nema aktivnih sync jobova.');
        } else {
            foreach ($runningJobs as $job) {
                if (! is_array($job)) {
                    continue;
                }

                $this->line(sprintf(
                    '  • Job #%s (%s) — traje %s',
                    $job['id'] ?? '?',
                    $job['type'] ?? '?',
                    $job['running_for'] ?? '?',
                ));
            }
        }

        foreach ($sync['sources'] ?? [] as $source) {
            if (! is_array($source)) {
                continue;
            }

            $suffix = ($source['is_overdue'] ?? false) ? ' [ZAKASnio]' : '';
            $this->line("  {$source['name']} (#{$source['id']}){$suffix}");
        }

        $issues = $report['issues'] ?? [];

        if ($issues === []) {
            $this->newLine();
            $this->info('Nema detektovanih problema.');

            return;
        }

        $this->newLine();
        $this->section('Detektovani problemi / preporuke');

        foreach ($issues as $issue) {
            if (! is_array($issue)) {
                continue;
            }

            $this->renderStatusLine(
                strtoupper((string) ($issue['level'] ?? 'info')),
                (string) ($issue['level'] ?? 'info'),
                (string) ($issue['message'] ?? ''),
            );
        }

        $this->newLine();
        $this->comment('Detaljniji sync izvještaj: php artisan bnc:sync-diagnose');
        $this->comment('Horizon dashboard: /horizon');
    }

    private function section(string $title): void
    {
        $this->info("=== {$title} ===");
    }

    private function renderStatusLine(string $label, string $level, string $message): void
    {
        $tag = match ($level) {
            'ok' => '[OK]',
            'fail' => '[FAIL]',
            'warn' => '[WARN]',
            default => '[INFO]',
        };

        $this->line("{$tag} {$label}: {$message}");
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function exitCodeForSummary(array $summary): int
    {
        return match ($summary['status'] ?? 'ok') {
            'fail' => 2,
            'warn' => 1,
            default => self::SUCCESS,
        };
    }
}
