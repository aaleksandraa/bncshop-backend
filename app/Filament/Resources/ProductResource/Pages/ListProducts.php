<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Jobs\RecalculateAllProductPricesJob;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('recalculateAllPrices')
                ->label('Preračunaj sve cijene')
                ->icon('heroicon-o-calculator')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Preračun svih cijena')
                ->modalDescription('U red čekanja se šalje preračun nabavna × marža × PDV za sve proizvode koji nisu eLine. Nezaključane redovne cijene se prepisuju. Zaključane ostaju, ali se ažurira kolona izračunate cijene. Queue worker mora biti aktivan.')
                ->action(function (): void {
                    $chunks = RecalculateAllProductPricesJob::start();

                    Notification::make()
                        ->title($chunks > 0 ? 'Preračun pokrenut' : 'Nema proizvoda za preračun')
                        ->body($chunks > 0
                            ? "Poslano {$chunks} batch-eva u red. Osvježite listu za par minuta i filtrirajte „Cijena nije usklađena“ da provjerite ostatak."
                            : 'Nema A1 proizvoda za preračun.')
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
