<?php

namespace App\Console\Commands;

use App\Models\ApiSource;
use App\Services\Sync\IncrementalSyncScheduler;
use App\Services\Sync\SyncHealthChecker;
use Illuminate\Console\Command;

class SyncDiagnoseCommand extends Command
{
    protected $signature = 'bnc:sync-diagnose
                            {--release-stale : Mark running jobs older than 3h as failed}';

    protected $description = 'Diagnose A1 sync scheduler, queue, and source health';

    public function handle(SyncHealthChecker $health, IncrementalSyncScheduler $scheduler): int
    {
        if ($this->option('release-stale')) {
            $released = $health->releaseStaleRunningJobs();
            $this->warn("Released {$released} stale running job(s).");
        }

        $infra = $health->infrastructure();
        $this->info('=== Infrastruktura ===');
        $this->line('Queue: '.$infra['queue_connection']);
        $this->line('Redis: '.($infra['redis_ok'] ? 'OK' : 'FAIL'));
        if ($infra['default_queue_size'] !== null) {
            $this->line("Redis default queue: {$infra['default_queue_size']} job(ova)");
        }
        if ($infra['sync_queue_size'] !== null) {
            $this->line("Redis sync queue: {$infra['sync_queue_size']} job(ova)");
        }
        if ($infra['analytics_queue_size'] !== null) {
            $this->line("Redis analytics queue: {$infra['analytics_queue_size']} job(ova)");
        }
        $this->line('Scheduler: '.$infra['scheduler_command'].' ('.$infra['scheduler_interval'].')');
        $this->line('Worker: '.$infra['worker_recommendation']);
        $this->newLine();

        $sources = ApiSource::query()->a1Integration()->get();

        if ($sources->isEmpty()) {
            $this->error('Nema A1 API izvora.');

            return self::FAILURE;
        }

        foreach ($sources as $source) {
            $this->info("=== {$source->name} (#{$source->id}) ===");
            $this->line('Zadnji uspješni sync: '.($source->last_successful_sync_at ?? '—'));
            $this->line('Sljedeći sync: '.($source->nextSyncAt() ?? '—'));
            $this->line('Interval: '.$source->sync_interval_minutes.' min');
            $this->line('Auto sync: '.($source->auto_sync_enabled ? 'ON' : 'OFF'));
            $this->line('Is due: '.($scheduler->isDue($source) ? 'YES' : 'no'));

            $report = $health->forSource($source);

            if ($report['is_overdue']) {
                $this->error('ZAKASnio od: '.$report['overdue_human'].' (trebao '.$report['overdue_since'].')');
            }

            if ($report['has_running_job']) {
                $job = $report['running_job'];
                $this->line("Running job #{$job['id']} ({$job['type']}) — {$job['running_for']}");
            }

            if ($report['issues'] !== []) {
                $this->newLine();
                $this->warn('Problemi:');
                foreach ($report['issues'] as $issue) {
                    $this->line('  • '.$issue);
                }
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
