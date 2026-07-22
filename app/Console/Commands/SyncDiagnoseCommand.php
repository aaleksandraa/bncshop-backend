<?php

namespace App\Console\Commands;

use App\Models\ApiSource;
use App\Services\Olx\OlxExportHealthChecker;
use App\Services\Sync\IncrementalSyncScheduler;
use App\Services\Sync\SyncHealthChecker;
use Illuminate\Console\Command;

class SyncDiagnoseCommand extends Command
{
    protected $signature = 'bnc:sync-diagnose
                            {--release-stale : Mark running jobs older than 3h as failed}';

    protected $description = 'Diagnose API import/export sync scheduler, queue, and source health';

    public function handle(
        SyncHealthChecker $health,
        OlxExportHealthChecker $olxHealth,
        IncrementalSyncScheduler $scheduler,
    ): int {
        if ($this->option('release-stale')) {
            $released = $health->releaseStaleRunningJobs();
            $this->warn("Released {$released} stale running job(s).");
        }

        $this->renderInfrastructure($health->infrastructure());
        $this->renderImportSources($health, $scheduler);
        $this->renderOlxExport($olxHealth);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $infra
     */
    private function renderInfrastructure(array $infra): void
    {
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

        $this->line('Import scheduler: '.$infra['scheduler_command'].' ('.$infra['scheduler_interval'].')');
        $this->line('OLX export scheduler: bnc:sync-olx-scheduled (daily at configured sync times)');
        $this->line('Worker: '.$infra['worker_recommendation']);
        $this->newLine();
    }

    private function renderImportSources(SyncHealthChecker $health, IncrementalSyncScheduler $scheduler): void
    {
        $sources = ApiSource::query()->a1Integration()->get();

        $this->info('=== A1 API import ===');

        if ($sources->isEmpty()) {
            $this->warn('Nema A1 API import izvora.');
            $this->newLine();

            return;
        }

        foreach ($sources as $source) {
            $this->renderImportSource($source, $health, $scheduler);
        }
    }

    private function renderImportSource(
        ApiSource $source,
        SyncHealthChecker $health,
        IncrementalSyncScheduler $scheduler,
    ): void {
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

    private function renderOlxExport(OlxExportHealthChecker $olxHealth): void
    {
        $report = $olxHealth->report();
        $source = $report['source'];

        $this->info('=== OLX / PIK export ===');

        if ($source === null) {
            $this->warn('OLX ApiSource zapis ne postoji.');
            $this->newLine();

            return;
        }

        $this->line("Izvor: {$source->name} (#{$source->id})");
        $this->line('Pipeline: RunOlxSyncJob → OlxApiClient (/auth/login)');
        $this->line('Export enabled: '.($report['export_enabled'] ? 'ON' : 'OFF'));
        $this->line('Auto sync: '.($report['auto_sync_enabled'] ? 'ON' : 'OFF'));
        $this->line('Kredencijali: '.($report['has_credentials'] ? 'OK' : 'NEDOSTAJU'));
        $this->line('Sync times: '.implode(', ', $report['sync_times'] ?? []));
        $this->line('Zadnji uspješni export: '.($source->last_successful_sync_at ?? '—'));
        $this->line('Sljedeći planirani export: '.($report['next_scheduled_at'] ?? '—'));
        $this->line('Connection: '.($source->connection_status ?? 'unknown'));

        if ($report['is_overdue']) {
            $this->error('ZAKASnio od: '.$report['overdue_human'].' (trebao '.$report['overdue_since'].')');
        }

        if ($report['has_running_job']) {
            $job = $report['running_job'];
            $this->line("Running job #{$job['id']} ({$job['type']}) — {$job['running_for']}");
        }

        if ($report['latest_job'] !== null) {
            $job = $report['latest_job'];
            $this->line("Zadnji job #{$job['id']} ({$job['type']}): {$job['status']}");
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
}
