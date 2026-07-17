<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\CanAccessAnalytics;
use App\Services\Analytics\AnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    use CanAccessAnalytics;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $kpis = app(AnalyticsService::class)->dashboardKpis();

        return [
            Stat::make('Prodaja danas', number_format($kpis['revenue']['today'], 2).' KM')
                ->description($kpis['revenue']['today_vs_yesterday_pct'].'% u odnosu na jučer')
                ->descriptionIcon($kpis['revenue']['today_vs_yesterday_pct'] >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($kpis['revenue']['today_vs_yesterday_pct'] >= 0 ? 'success' : 'danger'),
            Stat::make('Narudžbe danas', (string) $kpis['orders']['today'])
                ->description('Jučer: '.$kpis['orders']['yesterday']),
            Stat::make('Prosječna vrijednost narudžbe', number_format($kpis['avg_order_value'], 2).' KM')
                ->description('AOV danas'),
        ];
    }

    public static function canView(): bool
    {
        return static::canAccessAnalytics();
    }
}
