<?php

namespace App\Console\Commands;

use App\Services\Ananas\AnanasCatalogWriteGuard;
use App\Services\Ananas\AnanasDiscountService;
use App\Services\Ananas\AnanasSyncSettings;
use Illuminate\Console\Command;

class AnanasCancelDiscountCommand extends Command
{
    protected $signature = 'bnc:ananas-cancel-discount
                            {discountId : Ananas discount UUID}
                            {--dry-run : Preview cancel without PUT}
                            {--confirm : Required for live cancel}
                            {--allow-production : Allow writes when ANANAS_ENV=production}';

    protected $description = 'PUT Ananas discount cancellations for a discountId';

    public function handle(
        AnanasDiscountService $discountService,
        AnanasSyncSettings $settings,
        AnanasCatalogWriteGuard $writeGuard,
    ): int {
        if (! $settings->hasCredentials()) {
            $this->error('Ananas credentials are not configured.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $confirm = (bool) $this->option('confirm');

        if (! $dryRun && ! $confirm) {
            $this->error('Refusing cancel without --confirm. Use --dry-run first.');

            return self::FAILURE;
        }

        if (! $dryRun && ! $writeGuard->isAllowed()) {
            $this->error('Catalog writes are disabled.');

            return self::FAILURE;
        }

        $id = (string) $this->argument('discountId');

        try {
            $result = $discountService->cancel(
                $id,
                dryRun: $dryRun,
                allowProduction: (bool) $this->option('allow-production'),
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Cancel dry-run: '.$id : 'Cancel sent: '.$id);
        $this->line(json_encode($result['raw'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        return self::SUCCESS;
    }
}
