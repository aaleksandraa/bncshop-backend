<?php

namespace App\Filament\Resources\ProductResource\Widgets;

use App\Services\Pricing\ProductPriceRecalcStatus;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProductPriceRecalcStatusWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '10s';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $status = app(ProductPriceRecalcStatus::class)->snapshot();
        $percent = $status['total'] > 0
            ? (int) round(($status['done'] / $status['total']) * 100)
            : 100;

        $queueDescription = $status['in_progress']
            ? 'Preračun je u toku'
            : ($status['stalled']
                ? 'Nije u toku — ponovo kliknite Preračunaj sve cijene'
                : 'Red čekanja je prazan');

        return [
            Stat::make('Preračunato', $status['done'].' / '.$status['total'])
                ->description($percent.'% proizvoda ima spremljenu izračunatu cijenu')
                ->color($status['pending'] === 0 ? 'success' : 'warning'),
            Stat::make('U redu čekanja', (string) $status['queue_pending'])
                ->description($queueDescription)
                ->color($status['in_progress'] ? 'info' : ($status['stalled'] ? 'danger' : 'success')),
            Stat::make('Stvarna razlika', (string) $status['mismatch'])
                ->description('Redovna ≠ izračunata, nakon što je preračun već upisan')
                ->color($status['mismatch'] === 0 ? 'success' : 'danger'),
        ];
    }
}
