<?php

namespace App\Filament\Resources\OlxAttributeMappingResource\Pages;

use App\Filament\Resources\OlxAttributeMappingResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Artisan;

class ManageOlxAttributeMappings extends ManageRecords
{
    protected static string $resource = OlxAttributeMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('seedMappings')
                ->label('Učitaj mapiranja atributa')
                ->icon('heroicon-o-arrow-down-tray')
                ->requiresConfirmation()
                ->modalDescription('Učitava mapiranja atributa za svih 15 OLX kategorija (updateOrCreate).')
                ->action(function (): void {
                    Artisan::call('bnc:olx-seed-mappings', ['--attributes-only' => true]);

                    Notification::make()
                        ->title('Atribut mapiranja učitana')
                        ->body(trim(Artisan::output()) ?: 'Gotovo.')
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
