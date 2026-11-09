<?php

namespace App\Services\Ananas;

use App\Jobs\RunAnanasScheduledJob;
use App\Models\AnanasSyncRun;
use App\Models\AnanasSyncRun as RunModel;

class AnanasDailyOrchestrator
{
    public function __construct(
        private readonly AnanasSyncSettings $settings,
        private readonly AnanasSyncRunService $runService,
        private readonly AnanasCatalogPullService $catalogPull,
        private readonly AnanasTakedownService $takedown,
        private readonly AnanasLinkedProductSyncService $linkedSync,
        private readonly AnanasDailyDiscountSyncService $dailyDiscounts,
        private readonly AnanasProductImportService $importService,
        private readonly AnanasCatalogWriteGuard $writeGuard,
    ) {}

    public function dispatchIfAllowed(string $jobKey, string $triggeredBy = 'cron', ?int $userId = null): ?AnanasSyncRun
    {
        $this->runService->recoverStaleRuns();

        if ($this->runService->isJobLocked($jobKey)) {
            return null;
        }

        $manual = $triggeredBy !== 'cron';
        $gate = $this->settings->cronGateReason($jobKey, $manual);

        if ($gate !== null && ! $manual) {
            return null;
        }

        $run = $this->runService->queueRun($jobKey, $triggeredBy, $userId);

        if ($run === null) {
            return null;
        }

        RunAnanasScheduledJob::dispatch($run->id);

        return $run;
    }

    public function execute(int $runId): void
    {
        $run = AnanasSyncRun::query()->find($runId);

        if ($run === null) {
            return;
        }

        if ($run->status !== AnanasSyncRun::STATUS_QUEUED) {
            return;
        }

        $manual = $run->triggered_by !== 'cron';
        $gate = $this->settings->cronGateReason($run->job_key, $manual);

        if ($gate !== null && ! $manual) {
            $this->runService->finishSkipped($run, $gate);

            return;
        }

        $this->runService->markRunning($run);

        try {
            match ($run->job_key) {
                RunModel::JOB_CATALOG => $this->runCatalogChain($run, $manual),
                RunModel::JOB_TAKEDOWN => $this->runTakedownJob($run, $manual),
                RunModel::JOB_PRICE_STOCK => $this->runPriceStock($run, $manual, fallback: $run->triggered_by === 'cron'),
                RunModel::JOB_DISCOUNTS => $this->runDiscounts($run, $manual, fallback: $run->triggered_by === 'cron'),
                RunModel::JOB_IMPORT => $this->runImport($run, $manual),
                RunModel::JOB_INTRADAY_STOCK => $this->runIntradayStock($run, $manual),
                default => $this->runService->finishFailed($run, 'Unknown job key: '.$run->job_key),
            };
        } catch (\Throwable $e) {
            report($e);
            $this->runService->finishFailed($run, $e->getMessage());
        }
    }

    private function allowProduction(bool $manual): bool
    {
        if (! $this->settings->isProduction()) {
            return false;
        }

        return env('ANANAS_ENV') === AnanasSyncSettings::ENV_PRODUCTION;
    }

    private function runCatalogChain(AnanasSyncRun $run, bool $manual): void
    {
        $pull = $this->catalogPull->pullFullCatalog($run, $this->runService);

        if ($this->runService->cancelRequested($this->runService->refreshCancel($run))) {
            $this->runService->finishCancelled($run);

            return;
        }

        if (! ($pull['ok'] ?? false)) {
            $this->runService->finishFailed($run, implode('; ', $pull['errors'] ?? ['Catalog pull incomplete.']), [
                'pull' => $pull,
            ]);

            return;
        }

        $snapshot = [
            'date' => AnanasDailyWindow::todayDateString(),
            'remote_rows' => $pull['remote_rows'] ?? [],
            'inventory_ids' => $pull['inventory_ids'] ?? [],
        ];
        $this->runService->storeCatalogSnapshot($snapshot);

        $this->runService->finishSucceeded(
            $run,
            updated: (int) ($pull['linked'] ?? 0),
            skipped: (int) ($pull['skipped'] ?? 0),
            failedIds: [],
            errorMessage: ($pull['errors'] ?? []) !== [] ? implode('; ', array_slice($pull['errors'], 0, 3)) : null,
            meta: [
                'total_elements' => $pull['total_elements'] ?? 0,
                'skip_reasons' => $pull['skip_reasons'] ?? [],
                'unmatched_sample' => $pull['unmatched_sample'] ?? [],
            ],
        );

        $takedownRun = $this->runService->queueRun(RunModel::JOB_TAKEDOWN, 'chain', $run->triggered_by_user_id);

        if ($takedownRun !== null) {
            RunAnanasScheduledJob::dispatch($takedownRun->id);
        }
    }

    private function runTakedownJob(AnanasSyncRun $run, bool $manual): void
    {
        if (! $this->runService->todaySucceeded(RunModel::JOB_CATALOG)) {
            $this->runService->finishSkipped($run, 'Today’s catalog run has not succeeded.');

            return;
        }

        $snapshot = $this->runService->catalogSnapshot();

        if ($snapshot === null) {
            $this->runService->finishSkipped($run, 'No catalog snapshot for today.');

            return;
        }

        $rows = $this->takedown->rowsFromSnapshot($snapshot);
        $allow = $this->allowProduction($manual);
        $this->writeGuard->assertAllowed($allow);

        $result = $this->takedown->runTakedown($rows, $allow, $run, $this->runService);

        if ($this->runService->cancelRequested($this->runService->refreshCancel($run))) {
            $this->runService->finishCancelled($run);

            return;
        }

        $this->runService->setTakedownExcludeIds($result['exclude_ids']);

        $this->runService->finishSucceeded(
            $run,
            updated: $result['updated'],
            skipped: $result['skipped'],
            failedIds: $result['failed_ids'],
            errorMessage: $result['errors'] !== [] ? implode('; ', array_slice($result['errors'], 0, 3)) : null,
        );

        if (AnanasDailyWindow::inPriceStockWindow() && ! $this->runService->isJobLocked(RunModel::JOB_PRICE_STOCK)) {
            $priceRun = $this->runService->queueRun(RunModel::JOB_PRICE_STOCK, 'chain', $run->triggered_by_user_id);

            if ($priceRun !== null) {
                RunAnanasScheduledJob::dispatch($priceRun->id);
            }
        }
    }

    private function runPriceStock(AnanasSyncRun $run, bool $manual, bool $fallback = false): void
    {
        if (! $this->runService->todaySucceeded(RunModel::JOB_CATALOG)) {
            $this->runService->finishSkipped($run, 'Today’s catalog run has not succeeded.');

            return;
        }

        if (! $this->runService->todaySucceeded(RunModel::JOB_TAKEDOWN)) {
            $this->runService->finishSkipped($run, 'Takedown has not succeeded today.');

            return;
        }

        if (! AnanasDailyWindow::inPriceStockWindow() && ! $manual) {
            $this->runService->finishSkipped($run, 'Outside 00:00–02:00 price window.');

            return;
        }

        if ($fallback && $this->runService->todaySucceeded(RunModel::JOB_PRICE_STOCK)) {
            $this->runService->finishSkipped($run, 'Price/stock already succeeded today.');

            return;
        }

        $allow = $this->allowProduction($manual);
        $this->writeGuard->assertAllowed($allow);

        $vat = (int) ($this->settings->all()['vat_rate'] ?? 17);
        $result = $this->linkedSync->syncDailyPriceStock($run, $this->runService, $allow, $vat);

        if ($this->runService->cancelRequested($this->runService->refreshCancel($run))) {
            $this->runService->finishCancelled($run);

            return;
        }

        if ($result['aborted_vat']) {
            $this->runService->finishFailed($run, implode('; ', $result['errors'] ?: ['VAT rejected on first chunk.']));

            return;
        }

        $this->runService->finishSucceeded(
            $run,
            updated: $result['updated'],
            skipped: $result['skipped'],
            failedIds: $result['failed_ids'],
            errorMessage: $result['errors'] !== [] ? implode('; ', array_slice($result['errors'], 0, 3)) : null,
            meta: ['price_changed_ids' => $result['price_changed_ids']],
        );

        if (AnanasDailyWindow::inDiscountWindow() && ! $this->runService->isJobLocked(RunModel::JOB_DISCOUNTS)) {
            $discountRun = $this->runService->queueRun(RunModel::JOB_DISCOUNTS, 'chain', $run->triggered_by_user_id);

            if ($discountRun !== null) {
                RunAnanasScheduledJob::dispatch($discountRun->id);
            }
        }
    }

    private function runDiscounts(AnanasSyncRun $run, bool $manual, bool $fallback = false): void
    {
        if (! $this->runService->todaySucceeded(RunModel::JOB_PRICE_STOCK)) {
            $this->runService->finishSkipped($run, 'Tonight’s price/stock run has not succeeded.');

            return;
        }

        if (! AnanasDailyWindow::inDiscountWindow() && ! $manual) {
            $this->runService->finishSkipped($run, 'Outside discount window (before 03:00).');

            return;
        }

        if ($fallback && $this->runService->todaySucceeded(RunModel::JOB_DISCOUNTS)) {
            $this->runService->finishSkipped($run, 'Discounts already succeeded today.');

            return;
        }

        $priceRun = $this->runService->todayRunWithStatus(RunModel::JOB_PRICE_STOCK, RunModel::STATUS_SUCCEEDED);
        $priceChanged = is_array($priceRun?->meta) ? ($priceRun->meta['price_changed_ids'] ?? []) : [];

        $allow = $this->allowProduction($manual);
        $result = $this->dailyDiscounts->runDaily($run, $this->runService, is_array($priceChanged) ? $priceChanged : [], $allow);

        if ($this->runService->cancelRequested($this->runService->refreshCancel($run))) {
            $this->runService->finishCancelled($run);

            return;
        }

        $this->runService->finishSucceeded(
            $run,
            updated: $result['scheduled'] + $result['cancelled'],
            skipped: $result['skipped'],
            failedIds: $result['failed_ids'],
            errorMessage: $result['errors'] !== [] ? implode('; ', array_slice($result['errors'], 0, 3)) : null,
        );
    }

    private function runImport(AnanasSyncRun $run, bool $manual): void
    {
        $allow = $this->allowProduction($manual);
        $limit = $this->settings->importDailyLimit();
        $totalSubmitted = 0;
        $totalSkipped = 0;
        $errors = [];

        while ($totalSubmitted < $limit) {
            if ($this->runService->cancelRequested($this->runService->refreshCancel($run))) {
                $this->runService->finishCancelled($run);

                return;
            }

            $batch = min(25, $limit - $totalSubmitted);
            $result = $this->importService->importBatch($batch, null, $allow, false);
            $submitted = (int) ($result['submitted'] ?? 0);
            $totalSubmitted += $submitted;
            $totalSkipped += (int) ($result['skipped'] ?? 0);
            $errors = array_merge($errors, $result['errors'] ?? []);
            $this->runService->heartbeat($run);

            if ($submitted === 0) {
                break;
            }
        }

        $this->runService->finishSucceeded(
            $run,
            updated: $totalSubmitted,
            skipped: $totalSkipped,
            failedIds: [],
            errorMessage: $errors !== [] ? implode('; ', array_slice($errors, 0, 3)) : null,
        );
    }

    private function runIntradayStock(AnanasSyncRun $run, bool $manual): void
    {
        if (! $this->runService->todaySucceeded(RunModel::JOB_CATALOG)) {
            $this->runService->finishSkipped($run, 'Today’s catalog run has not succeeded.');

            return;
        }

        $allow = $this->allowProduction($manual);
        $result = $this->linkedSync->syncDailyStockOnly($run, $this->runService, $allow);

        if ($this->runService->cancelRequested($this->runService->refreshCancel($run))) {
            $this->runService->finishCancelled($run);

            return;
        }

        $this->runService->finishSucceeded(
            $run,
            updated: $result['updated'],
            skipped: $result['skipped'],
            failedIds: $result['failed_ids'],
            errorMessage: $result['errors'] !== [] ? implode('; ', array_slice($result['errors'], 0, 3)) : null,
        );
    }
}
