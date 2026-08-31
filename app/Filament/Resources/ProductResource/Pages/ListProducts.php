<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Widgets\ProductPriceRecalcStatusWidget;
use App\Jobs\RecalculateAllProductPricesJob;
use App\Services\Pricing\ProductPriceRecalcStatus;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ProductPriceRecalcStatusWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 3;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('recalculateAllPrices')
                ->label('Preračunaj sve cijene')
                ->icon('heroicon-o-calculator')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Preračun svih cijena')
                ->modalDescription('U red čekanja se šalje preračun nabavna × marža × PDV. Nezaključane redovne cijene se prepisuju. Status iznad liste se osvježava svakih 10 sekundi. Queue worker mora biti aktivan.')
                ->action(function (): void {
                    $chunks = RecalculateAllProductPricesJob::start();
                    $status = app(ProductPriceRecalcStatus::class)->snapshot();

                    Notification::make()
                        ->title($chunks > 0 ? 'Preračun pokrenut' : 'Nema proizvoda za preračun')
                        ->body($chunks > 0
                            ? "Otprilike {$chunks} batch-eva. U redu: {$status['queue_pending']}. Broj „Preračunato“ iznad liste mora rasti; filter „Cijena nije usklađena“ pokazuje samo stvarne razlike."
                            : 'Nema A1 proizvoda za preračun.')
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
