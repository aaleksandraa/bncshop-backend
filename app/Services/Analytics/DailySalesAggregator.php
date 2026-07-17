<?php

namespace App\Services\Analytics;

use App\Models\DailySalesSnapshot;
use App\Models\Order;
use Carbon\Carbon;

class DailySalesAggregator
{
    public function aggregate(?Carbon $date = null): DailySalesSnapshot
    {
        $date = ($date ?? now()->subDay())->toDateString();

        $stats = Order::query()
            ->whereDate('created_at', $date)
            ->whereNotIn('status', ['otkazano'])
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(items_count), 0) as items_sold')
            ->selectRaw('COALESCE(AVG(total), 0) as avg_order_value')
            ->first();

        return DailySalesSnapshot::query()->updateOrCreate(
            ['date' => $date],
            [
                'revenue' => round((float) $stats->revenue, 2),
                'orders_count' => (int) $stats->orders_count,
                'items_sold' => (int) $stats->items_sold,
                'avg_order_value' => round((float) $stats->avg_order_value, 2),
            ]
        );
    }

    /**
     * @return array{processed: int}
     */
    public function backfill(Carbon $from, Carbon $to): array
    {
        $processed = 0;
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $this->aggregate($cursor);
            $processed++;
            $cursor->addDay();
        }

        return ['processed' => $processed];
    }
}
