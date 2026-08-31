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

        $queueDescription = match (true) {
            $status['queue_storm'] => 'Cijeli default red, ne broj proizvoda. Raste zbog preklapanja — nemojte ponovo pokretati.',
            $status['in_progress'] => 'Cijeli default red (cijene + ostali jobovi), ne broj proizvoda',
            $status['stalled'] => 'Red je prazan. Ako izračunata cijena još nije upisana, pokrenite preračun jednom.',
            default => 'Red čekanja je prazan',
        };

        return [
            Stat::make('Preračunato', $status['done'].' / '.$status['total'])
                ->description($percent.'% ima upisanu kolonu izračunate cijene')
                ->color($status['pending'] === 0 ? 'success' : 'warning'),
            Stat::make('Default queue', (string) $status['queue_pending'])
                ->description($queueDescription)
                ->color($status['queue_storm'] ? 'danger' : ($status['in_progress'] ? 'info' : ($status['stalled'] ? 'warning' : 'success'))),
            Stat::make('Stvarna razlika', (string) $status['mismatch'])
                ->description('Redovna ≠ izračunata, tek nakon što je kolona popunjena')
                ->color($status['mismatch'] === 0 ? 'success' : 'danger'),
        ];
    }
}
