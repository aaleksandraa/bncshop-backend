<?php

namespace App\Console\Commands;

use App\Services\Analytics\DailySalesAggregator;
use Illuminate\Console\Command;

class AggregateDailyAnalyticsCommand extends Command
{
    protected $signature = 'analytics:aggregate-daily {--date= : Date to aggregate (Y-m-d), defaults to yesterday}';

    protected $description = 'Aggregate daily sales snapshot for analytics';

    public function handle(DailySalesAggregator $aggregator): int
    {
        $date = $this->option('date')
            ? \Carbon\Carbon::parse($this->option('date'))
            : null;

        $snapshot = $aggregator->aggregate($date);

        $this->info("Aggregated sales for {$snapshot->date->toDateString()}: {$snapshot->orders_count} orders, {$snapshot->revenue} revenue.");

        return self::SUCCESS;
    }
}
