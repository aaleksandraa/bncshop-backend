<?php

namespace App\Console\Commands;

use App\Jobs\RunAnanasPullOrdersJob;
use App\Services\Ananas\AnanasOrderPullService;
use App\Services\Ananas\AnanasSyncSettings;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AnanasPullOrdersCommand extends Command
{
    protected $signature = 'bnc:ananas-pull-orders
        {--backfill : Pull the last 14 days without sending notification mail}
        {--since= : ISO date for backfill start}
        {--sync : Run pull in this process instead of queueing}';

    protected $description = 'Pull Ananas merchant orders into local storage';

    public function handle(AnanasSyncSettings $settings, AnanasOrderPullService $pullService): int
    {
        if (! $settings->isOrdersPullEnabled()) {
            $this->line('Ananas orders pull is disabled.');

            return self::SUCCESS;
        }

        if (! $settings->isEnabled() || ! $settings->hasCredentials()) {
            $this->error('Ananas export or credentials are not available.');

            return self::FAILURE;
        }

        $backfill = (bool) $this->option('backfill');
        $since = $this->option('since');

        if ($backfill && ! is_string($since)) {
            $since = Carbon::now()->subDays(14)->toIso8601String();
        }

        if ($this->option('sync')) {
            $result = $pullService->pull(
                $backfill,
                is_string($since) ? Carbon::parse($since) : null,
            );
            $this->info(sprintf(
                'Pulled: created=%d updated=%d notified=%d',
                $result['created'],
                $result['updated'],
                $result['notified'],
            ));

            if ($result['errors'] !== []) {
                $this->warn(implode('; ', $result['errors']));
            }

            return self::SUCCESS;
        }

        RunAnanasPullOrdersJob::dispatch(
            $backfill,
            is_string($since) ? $since : null,
        );

        $this->info('Ananas orders pull queued.');

        return self::SUCCESS;
    }
}
