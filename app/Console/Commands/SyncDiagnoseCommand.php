<?php

namespace App\Console\Commands;

use App\Models\ApiSource;
use App\Services\Olx\OlxDailyCreateLimiter;
use App\Services\Olx\OlxExportHealthChecker;
use App\Services\Sync\IncrementalSyncScheduler;
use App\Services\Sync\SyncHealthChecker;
use Illuminate\Console\Command;

class SyncDiagnoseCommand extends Command
{
    protected $signature = 'bnc:sync-diagnose
                            {--release-stale : Mark running jobs older than 3h as failed}
                            {--release-stale-minutes= : Mark running jobs older than N minutes as failed}
                            {--heal-olx : Clear stale OLX connection errors from the old A1 import pipeline}';

    protected $description = 'Diagnose API import/export sync scheduler, queue, and source health';

    public function handle(
        SyncHealthChecker $health,
        OlxExportHealthChecker $olxHealth,
        IncrementalSyncScheduler $scheduler,
    ): int {
        if ($this->option('release-stale') || $this->option('release-stale-minutes') !== null) {
            $minutes = $this->option('release-stale-minutes') !== null
                ? max(1, (int) $this->option('release-stale-minutes'))
                : 180;
            $released = $health->releaseStaleRunningJobs($minutes);
            $this->warn("Released {$released} stale running job(s) (older than {$minutes} min).");
        }

        if ($this->option('heal-olx')) {
            $healed = $olxHealth->healStaleConnectionState($olxHealth->report()['source']);
            $this->line($healed
                ? 'OLX connection status očišćen od zastarjele A1 import greške.'
                : 'Nema zastarjele OLX greške za čišćenje.');
        }

        $this->renderInfrastructure($health->infrastructure());
        $this->renderImportSources($health, $scheduler);
        $this->renderOlxExport($olxHealth, app(OlxDailyCreateLimiter::class));

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

    private function renderOlxExport(OlxExportHealthChecker $olxHealth, OlxDailyCreateLimiter $createLimiter): void
    {
        $report = $olxHealth->report();
        $source = $report['source'];
        $limitSnapshot = $createLimiter->snapshot();

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
        $this->line(sprintf(
            'Dnevna kvota kreiranja: %d/%d (preostalo danas: %d)',
            $limitSnapshot['creates_today'],
            $limitSnapshot['daily_limit'],
            $limitSnapshot['remaining_today'],
        ));

        if ($report['stale_import_pipeline_error']) {
            $this->warn('Connection error je zastarjela greška iz A1 import pipeline-a, ne iz OLX exporta.');
        }

        if (($report['wrong_pipeline_job_count'] ?? 0) > 0) {
            $this->warn('Neuspjeli A1 import jobovi na OLX izvoru (prije fixa): '.$report['wrong_pipeline_job_count']);
        }

        if ($report['is_overdue']) {
            $this->error('ZAKASnio od: '.$report['overdue_human'].' (trebao '.$report['overdue_since'].')');
        }

        if ($report['has_running_job']) {
            $job = $report['running_job'];
            $this->line("Running job #{$job['id']} ({$job['type']}) — {$job['running_for']}");

            $stats = is_array($job['stats'] ?? null) ? $job['stats'] : null;

            if ($stats !== null) {
                $actions = $stats['actions'] ?? [];
                $scan = $stats['scan'] ?? [];
                $this->line(sprintf(
                    '  Napredak: created=%d, updated=%d, skipped_quota=%d, errors=%d',
                    $actions['created'] ?? 0,
                    $actions['updated'] ?? 0,
                    $actions['skipped_quota'] ?? 0,
                    is_array($actions['errors'] ?? null) ? count($actions['errors']) : 0,
                ));

                if (($scan['scanned'] ?? 0) > 0) {
                    $this->line(sprintf(
                        '  Scan: %d proizvoda, %d pending create, %d unchanged',
                        $scan['scanned'] ?? 0,
                        $scan['pending_create'] ?? 0,
                        $scan['unchanged'] ?? 0,
                    ));
                } elseif ($job['started_at'] !== null && $job['started_at']->lt(now()->subMinutes(30))) {
                    $this->warn('  Scan još nije završen — detekcija ~3000 proizvoda traje 30–60 min.');
                }
            } elseif ($job['started_at'] !== null && $job['started_at']->lt(now()->subMinutes(30))) {
                $this->warn('  Nema stats u bazi — deploy sa progress flush-om omogućava praćenje tokom run-a.');
            }

            if ($job['started_at'] !== null && $job['started_at']->lt(now()->subHours(2))) {
                $this->warn('  Job radi duže od 2h — normalno za punu kvotu (350), stale cleanup tek nakon 3h.');
            }
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
