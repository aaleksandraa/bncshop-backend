<?php

namespace App\Console\Commands;

use App\Models\AnanasSyncRun;
use App\Services\Ananas\AnanasCatalogWriteGuard;
use App\Services\Ananas\AnanasDailyDiscountSyncService;
use App\Services\Ananas\AnanasSyncRunService;
use App\Services\Ananas\AnanasSyncSettings;
use Illuminate\Console\Command;

class AnanasSyncDiscountsCommand extends Command
{
    protected $signature = 'bnc:ananas-sync-discounts
                            {--dry-run : Preview BNC sale discounts without POST or cancel}
                            {--confirm : Required for live discount POST/cancel}
                            {--allow-production : Allow writes when ANANAS_ENV=production}';

    protected $description = 'Schedule or cancel Ananas discounts from current BNC sale prices';

    public function handle(
        AnanasDailyDiscountSyncService $discounts,
        AnanasSyncRunService $runs,
        AnanasSyncSettings $settings,
        AnanasCatalogWriteGuard $writeGuard,
    ): int {
        if (! $settings->hasCredentials()) {
            $this->error('Ananas credentials are not configured.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $confirm = (bool) $this->option('confirm');
        $allowProduction = (bool) $this->option('allow-production');

        if (! $dryRun && ! $confirm) {
            $this->error('Refusing discount sync without --confirm. Use --dry-run first.');

            return self::FAILURE;
        }

        if (! $dryRun && ! $writeGuard->isAllowed()) {
            $this->error('Catalog writes are disabled.');

            return self::FAILURE;
        }

        $run = $runs->queueRun(AnanasSyncRun::JOB_DISCOUNTS, 'cli');

        if ($run === null) {
            $this->error('A discount job is already queued or running.');

            return self::FAILURE;
        }

        $runs->markRunning($run);

        try {
            $result = $discounts->runDaily($run, $runs, [], $allowProduction, $dryRun);
        } catch (\Throwable $e) {
            $runs->finishFailed($run, $e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($runs->cancelRequested($runs->refreshCancel($run))) {
            $runs->finishCancelled($run);
            $this->warn('Discount sync cancelled.');

            return self::SUCCESS;
        }

        $runs->finishSucceeded(
            $run,
            updated: $result['scheduled'] + $result['cancelled'],
            skipped: $result['skipped'],
            failedIds: $result['failed_ids'],
            errorMessage: $result['errors'] !== [] ? implode('; ', array_slice($result['errors'], 0, 3)) : null,
        );

        $this->info(sprintf(
            'BNC sale discounts (%s): scheduled=%d cancelled=%d skipped=%d failed=%d',
            $dryRun ? 'dry-run' : 'live',
            $result['scheduled'],
            $result['cancelled'],
            $result['skipped'],
            count($result['failed_ids']),
        ));

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        return $result['errors'] === [] && $result['failed_ids'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
