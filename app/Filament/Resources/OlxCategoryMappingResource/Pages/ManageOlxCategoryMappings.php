<?php

namespace App\Filament\Resources\OlxCategoryMappingResource\Pages;

use App\Filament\Resources\OlxCategoryMappingResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Artisan;

class ManageOlxCategoryMappings extends ManageRecords
{
    protected static string $resource = OlxCategoryMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('seedMappings')
                ->label('Učitaj mapiranja')
                ->icon('heroicon-o-arrow-down-tray')
                ->requiresConfirmation()
                ->modalDescription('Učitava svih 15 BNC→OLX mapiranja iz seedera (updateOrCreate — postojeća ručna podešavanja ostaju osim za iste kategorije).')
                ->action(function (): void {
                    Artisan::call('bnc:olx-seed-mappings', ['--categories-only' => true]);

                    Notification::make()
                        ->title('Mapiranja učitana')
                        ->body(trim(Artisan::output()) ?: 'Gotovo.')
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
