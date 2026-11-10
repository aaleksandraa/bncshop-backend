<?php

namespace App\Filament\Pages;

use App\Models\AnanasProductMapping;
use App\Models\AnanasSyncRun;
use App\Services\Ananas\AnanasDailyOrchestrator;
use App\Services\Ananas\AnanasSyncRunService;
use App\Services\Ananas\AnanasSyncSettings;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class AnanasDailyJobsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Ananas';

    protected static ?string $navigationLabel = 'Dnevni poslovi';

    protected static ?string $title = 'Ananas — dnevni poslovi';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.ananas-daily-jobs';

    public ?string $pageLoadError = null;

    public int $importDailyLimit = 2000;

    public bool $dailyJobsMasterEnabled = false;

    /** @var list<array<string, mixed>> */
    public array $dailyJobDefinitions = [];

    /** @var list<array<string, mixed>> */
    public array $dailyRunHistory = [];

    /** @var array<string, int> */
    public array $mappingStatusCounts = [];

    public int $linkedMappingCount = 0;

    /** @var list<array<string, mixed>> */
    public array $linkedMappingSample = [];

    public bool $dailyJobsActive = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && (
            $user->hasRole(['Super Admin', 'Admin'])
            || $user->can('manage_sync')
            || $user->can('api_sources.update')
        );
    }

    public function mount(AnanasSyncSettings $settings): void
    {
        try {
            $this->refreshDailyJobsUi($settings);
        } catch (\Throwable $e) {
            report($e);
            $this->pageLoadError = $e->getMessage();
        }
    }

    public function refreshDailyJobsUi(?AnanasSyncSettings $settings = null): void
    {
        $settings ??= app(AnanasSyncSettings::class);
        $this->importDailyLimit = $settings->importDailyLimit();
        $this->dailyJobsMasterEnabled = $settings->dailyJobsMasterEnabled();

        $this->dailyJobDefinitions = [
            ['key' => AnanasSyncRun::JOB_CATALOG, 'label' => 'Katalog', 'schedule' => '00:10'],
            ['key' => AnanasSyncRun::JOB_PRICE_STOCK, 'label' => 'Cijene i zalihe', 'schedule' => 'lanac / 00:25'],
            ['key' => AnanasSyncRun::JOB_DISCOUNTS, 'label' => 'Akcije (BNC)', 'schedule' => 'lanac / 02:30'],
            ['key' => AnanasSyncRun::JOB_IMPORT, 'label' => 'Novi artikli', 'schedule' => '08:00'],
            ['key' => AnanasSyncRun::JOB_INTRADAY_STOCK, 'label' => 'Promjena zalihe', 'schedule' => '10:00'],
        ];

        $latestByJob = [];

        foreach ($this->dailyJobDefinitions as $def) {
            $latest = AnanasSyncRun::query()
                ->where('job_key', $def['key'])
                ->latest('id')
                ->first();

            $latestByJob[$def['key']] = $latest;
        }

        foreach ($this->dailyJobDefinitions as $index => $def) {
            $run = $latestByJob[$def['key']] ?? null;
            $auto = (bool) ($settings->all()['daily_job_auto'][$def['key']] ?? true);
            $this->dailyJobDefinitions[$index]['auto'] = $auto;
            $this->dailyJobDefinitions[$index]['latest'] = $run ? [
                'id' => $run->id,
                'status' => $run->status,
                'updated' => $run->updated_count,
                'skipped' => $run->skipped_count,
                'failed' => $run->failed_item_count,
                'error' => $run->error_message,
                'meta' => $run->meta ?? [],
                'triggered_by' => $run->triggered_by,
                'finished_at' => $run->finished_at?->toDateTimeString(),
            ] : null;
            $this->dailyJobDefinitions[$index]['locked'] = $run !== null && $run->isActive();
        }

        $this->dailyRunHistory = AnanasSyncRun::query()
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(static fn (AnanasSyncRun $run): array => [
                'id' => $run->id,
                'job_key' => $run->job_key,
                'status' => $run->status,
                'triggered_by' => $run->triggered_by,
                'updated' => $run->updated_count,
                'skipped' => $run->skipped_count,
                'failed' => $run->failed_item_count,
                'failed_ids' => $run->failed_inventory_ids ?? [],
                'error' => $run->error_message,
                'meta' => $run->meta ?? [],
                'finished_at' => $run->finished_at?->toDateTimeString(),
            ])
            ->all();

        $this->mappingStatusCounts = [];

        $statusRows = AnanasProductMapping::query()
            ->selectRaw('local_status, remote_environment, count(*) as aggregate')
            ->groupBy('local_status', 'remote_environment')
            ->get();

        foreach ($statusRows as $row) {
            $label = $row->local_status.' / '.($row->remote_environment ?: 'bez okruženja');
            $this->mappingStatusCounts[$label] = (int) $row->aggregate;
        }

        $this->linkedMappingCount = 0;

        foreach ($this->mappingStatusCounts as $label => $count) {
            if (str_starts_with((string) $label, AnanasProductMapping::LOCAL_LINKED.' /')) {
                $this->linkedMappingCount += $count;
            }
        }

        $this->linkedMappingSample = AnanasProductMapping::query()
            ->with('product:id,name')
            ->where('local_status', AnanasProductMapping::LOCAL_LINKED)
            ->whereNotNull('ananas_product_id')
            ->orderByDesc('last_success_at')
            ->limit(500)
            ->get()
            ->map(static fn (AnanasProductMapping $mapping): array => [
                'product_id' => $mapping->product_id,
                'environment' => $mapping->remote_environment ?: '—',
                'name' => $mapping->product?->name ?: '—',
                'ean' => $mapping->ean,
                'inventory_id' => $mapping->merchant_inventory_id ?: $mapping->ananas_product_id,
                'remote_status' => $mapping->remote_status,
                'last_success_at' => $mapping->last_success_at?->toDateTimeString(),
            ])
            ->all();

        $this->dailyJobsActive = AnanasSyncRun::query()
            ->whereIn('status', [AnanasSyncRun::STATUS_QUEUED, AnanasSyncRun::STATUS_RUNNING])
            ->exists();
    }

    public function toggleDailyMaster(AnanasSyncSettings $settings): void
    {
        $all = $settings->all();
        $all['daily_jobs_master_enabled'] = ! $settings->dailyJobsMasterEnabled();
        $settings->save($all);
        $this->refreshDailyJobsUi($settings);
    }

    public function toggleDailyJobAuto(string $jobKey, AnanasSyncSettings $settings): void
    {
        $all = $settings->all();
        $jobs = is_array($all['daily_job_auto'] ?? null) ? $all['daily_job_auto'] : [];
        $jobs[$jobKey] = ! (bool) ($jobs[$jobKey] ?? true);
        $all['daily_job_auto'] = $jobs;
        $settings->save($all);
        $this->refreshDailyJobsUi($settings);
    }

    public function startDailyJob(string $jobKey, AnanasDailyOrchestrator $orchestrator): void
    {
        $userId = auth()->id();
        $run = $orchestrator->dispatchIfAllowed($jobKey, 'admin', is_int($userId) ? $userId : null);

        if ($run === null) {
            Notification::make()->title('Posao nije pokrenut')->body('Već je u redu ili u toku.')->warning()->send();
            $this->refreshDailyJobsUi();

            return;
        }

        Notification::make()->title('Posao u redu')->body('#'.$run->id.' ('.$jobKey.')')->success()->send();
        $this->refreshDailyJobsUi();
    }

    public function cancelDailyJob(int $runId, AnanasSyncRunService $runs): void
    {
        $run = AnanasSyncRun::query()->find($runId);

        if ($run === null) {
            return;
        }

        if ($run->status === AnanasSyncRun::STATUS_QUEUED) {
            $runs->finishCancelled($run);
        } else {
            $runs->requestCancel($run);
        }

        Notification::make()->title('Zaustavljanje zatraženo')->success()->send();
        $this->refreshDailyJobsUi();
    }

    public function saveDailyImportLimit(AnanasSyncSettings $settings): void
    {
        $all = $settings->all();
        $all['import_daily_limit'] = max(1, min(5000, $this->importDailyLimit));
        $settings->save($all);
        $this->importDailyLimit = $settings->importDailyLimit();
        Notification::make()->title('Dnevni limit importa sačuvan')->success()->send();
    }
}
