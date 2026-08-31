<?php

namespace App\Services\Pricing;

use App\Models\Product;
use Illuminate\Support\Facades\Queue;

class ProductPriceRecalcStatus
{
    /**
     * @return array{
     *     total: int,
     *     done: int,
     *     pending: int,
     *     mismatch: int,
     *     queue_pending: int,
     *     in_progress: bool,
     *     stalled: bool
     * }
     */
    public function snapshot(): array
    {
        $base = Product::query()->notFromEline()->where('is_set', false);
        $total = (clone $base)->count();
        $pending = (clone $base)->whereNull('calculated_price')->count();
        $mismatch = Product::query()
            ->where('price_locked', false)
            ->where('is_set', false)
            ->notFromEline()
            ->whereNotNull('calculated_price')
            ->whereRaw('ABS(COALESCE(regular_price, 0) - calculated_price) >= 0.005')
            ->count();
        $queuePending = $this->defaultQueueSize();

        return [
            'total' => $total,
            'done' => max(0, $total - $pending),
            'pending' => $pending,
            'mismatch' => $mismatch,
            'queue_pending' => $queuePending,
            'in_progress' => $queuePending > 0,
            'stalled' => $pending > 0 && $queuePending === 0,
        ];
    }

    public function defaultQueueSize(): int
    {
        try {
            return Queue::connection((string) config('queue.default'))->size('default');
        } catch (\Throwable) {
            return (int) \Illuminate\Support\Facades\DB::table('jobs')->count();
        }
    }
}
