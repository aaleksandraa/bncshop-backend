<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\CanAccessAnalytics;
use App\Services\Analytics\AnalyticsService;
use Filament\Widgets\ChartWidget;

class SalesChartWidget extends ChartWidget
{
    use CanAccessAnalytics;

    protected static ?string $heading = 'Prodaja (7 dana)';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $chart = app(AnalyticsService::class)->salesChartLastDays(7);

        return [
            'datasets' => [
                [
                    'label' => 'Prihod (KM)',
                    'data' => $chart['revenue'],
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Narudžbe',
                    'data' => $chart['orders'],
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $chart['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'position' => 'left',
                ],
                'y1' => [
                    'beginAtZero' => true,
                    'position' => 'right',
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
        ];
    }

    public static function canView(): bool
    {
        return static::canAccessAnalytics();
    }
}
