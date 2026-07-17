<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\ApiImportJob;
use App\Models\DailySalesSnapshot;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReportCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function track(string $eventType, array $metadata = [], ?int $userId = null, ?string $sessionId = null): AnalyticsEvent
    {
        return AnalyticsEvent::query()->create([
            'event_type' => $eventType,
            'session_id' => $sessionId,
            'user_id' => $userId,
            'product_id' => $metadata['product_id'] ?? null,
            'category_id' => $metadata['category_id'] ?? null,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardKpis(): array
    {
        return $this->rememberReport(
            'dashboard_kpis',
            md5('dashboard'),
            now()->addMinutes(5),
            fn (): array => $this->buildDashboardKpis()
        );
    }

    /**
     * @return array{labels: array<int, string>, revenue: array<int, float>, orders: array<int, int>}
     */
    public function salesChartLastDays(int $days = 7): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $to = now()->endOfDay();

        $snapshots = DailySalesSnapshot::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get()
            ->keyBy(fn (DailySalesSnapshot $snapshot): string => $snapshot->date->toDateString());

        $labels = [];
        $revenue = [];
        $orders = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i)->toDateString();
            $labels[] = Carbon::parse($date)->format('d.m.');
            $snapshot = $snapshots->get($date);

            if ($snapshot) {
                $revenue[] = (float) $snapshot->revenue;
                $orders[] = (int) $snapshot->orders_count;

                continue;
            }

            $period = $this->aggregatePeriod(
                Carbon::parse($date)->startOfDay(),
                Carbon::parse($date)->endOfDay()
            );
            $revenue[] = $period['revenue'];
            $orders[] = $period['orders_count'];
        }

        return compact('labels', 'revenue', 'orders');
    }

    /**
     * @return array<string, mixed>
     */
    public function salesReport(Carbon $from, Carbon $to, ?Carbon $compareFrom = null, ?Carbon $compareTo = null): array
    {
        $cacheKey = md5($from->toDateString().$to->toDateString().($compareFrom?->toDateString() ?? ''));

        return $this->rememberReport(
            'sales_by_period',
            $cacheKey,
            now()->addHour(),
            function () use ($from, $to, $compareFrom, $compareTo): array {
                $current = $this->aggregatePeriod($from, $to);
                $comparison = null;

                if ($compareFrom && $compareTo) {
                    $comparison = $this->aggregatePeriod($compareFrom, $compareTo);
                }

                return [
                    'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                    'current' => $current,
                    'comparison' => $comparison,
                    'change' => $comparison ? $this->percentChange($comparison, $current) : null,
                    'daily' => $this->dailyBreakdown($from, $to),
                ];
            }
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDashboardKpis(): array
    {
        $today = $this->aggregatePeriod(now()->startOfDay(), now());
        $yesterday = $this->aggregatePeriod(now()->subDay()->startOfDay(), now()->subDay()->endOfDay());
        $thisMonth = $this->aggregatePeriod(now()->startOfMonth(), now());

        return [
            'revenue' => [
                'today' => $today['revenue'],
                'yesterday' => $yesterday['revenue'],
                'this_month' => $thisMonth['revenue'],
                'today_vs_yesterday_pct' => $this->percentChange($yesterday, $today)['revenue_pct'] ?? 0,
            ],
            'orders' => [
                'today' => $today['orders_count'],
                'yesterday' => $yesterday['orders_count'],
                'this_month' => $thisMonth['orders_count'],
            ],
            'avg_order_value' => $today['avg_order_value'],
            'top_products' => $this->topProducts(10),
            'top_categories' => $this->topCategories(10),
            'top_brands' => $this->topBrands(10),
            'out_of_stock_count' => Product::query()->where('stock_status', 'out_of_stock')->count(),
            'sync_errors_24h' => ApiImportJob::query()
                ->where('status', 'failed')
                ->where('created_at', '>=', now()->subDay())
                ->count(),
        ];
    }

    /**
     * @return array{revenue: float, orders_count: int, items_sold: int, avg_order_value: float}
     */
    private function aggregatePeriod(Carbon $from, Carbon $to): array
    {
        $row = Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', ['otkazano'])
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(items_count), 0) as items_sold')
            ->selectRaw('COALESCE(AVG(total), 0) as avg_order_value')
            ->first();

        return [
            'revenue' => round((float) $row->revenue, 2),
            'orders_count' => (int) $row->orders_count,
            'items_sold' => (int) $row->items_sold,
            'avg_order_value' => round((float) $row->avg_order_value, 2),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dailyBreakdown(Carbon $from, Carbon $to): array
    {
        return DailySalesSnapshot::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get()
            ->map(fn (DailySalesSnapshot $snapshot): array => [
                'date' => $snapshot->date->toDateString(),
                'revenue' => (float) $snapshot->revenue,
                'orders_count' => (int) $snapshot->orders_count,
                'items_sold' => (int) $snapshot->items_sold,
                'avg_order_value' => (float) $snapshot->avg_order_value,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topProducts(int $limit): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotIn('orders.status', ['otkazano'])
            ->selectRaw('order_items.product_name as name')
            ->selectRaw('SUM(order_items.quantity) as qty')
            ->selectRaw('SUM(order_items.line_total) as revenue')
            ->groupBy('order_items.product_name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'name' => $row->name,
                'qty' => (int) $row->qty,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topCategories(int $limit): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotIn('orders.status', ['otkazano'])
            ->whereNotNull('order_items.category_path')
            ->selectRaw('order_items.category_path as name')
            ->selectRaw('SUM(order_items.line_total) as revenue')
            ->groupBy('order_items.category_path')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'name' => $row->name,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topBrands(int $limit): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotIn('orders.status', ['otkazano'])
            ->whereNotNull('order_items.brand_name')
            ->selectRaw('order_items.brand_name as name')
            ->selectRaw('SUM(order_items.line_total) as revenue')
            ->groupBy('order_items.brand_name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'name' => $row->name,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    /**
     * @param  array<string, float|int>  $previous
     * @param  array<string, float|int>  $current
     * @return array<string, float>
     */
    private function percentChange(array $previous, array $current): array
    {
        $calc = fn (string $key): float => $this->pct(
            (float) ($previous[$key] ?? 0),
            (float) ($current[$key] ?? 0)
        );

        return [
            'revenue_pct' => $calc('revenue'),
            'orders_pct' => $calc('orders_count'),
            'items_pct' => $calc('items_sold'),
            'aov_pct' => $calc('avg_order_value'),
        ];
    }

    private function pct(float $previous, float $current): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function rememberReport(string $reportKey, string $paramsHash, \DateTimeInterface $expiresAt, callable $callback): array
    {
        $cached = ReportCache::query()
            ->where('report_key', $reportKey)
            ->where('params_hash', $paramsHash)
            ->where('expires_at', '>', now())
            ->first();

        if ($cached) {
            return $cached->data;
        }

        $data = $callback();

        ReportCache::query()->updateOrCreate(
            ['report_key' => $reportKey, 'params_hash' => $paramsHash],
            ['data' => $data, 'expires_at' => $expiresAt]
        );

        return $data;
    }
}
