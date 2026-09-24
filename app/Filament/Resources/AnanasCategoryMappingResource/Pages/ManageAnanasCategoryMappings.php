<?php

namespace App\Filament\Resources\AnanasCategoryMappingResource\Pages;

use App\Filament\Resources\AnanasCategoryMappingResource;
use App\Services\Ananas\AnanasValidatedMappingService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageAnanasCategoryMappings extends ManageRecords
{
    protected static string $resource = AnanasCategoryMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('applyValidated')
                ->label('Primijeni Stage mapiranja')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Primijeni potvrđena mapiranja')
                ->modalDescription('Upsert ITShop + Gaming laptopi (BNC 199) i Nosači za televizor (BNC 231), uključi export, isključi stari kandidat „Laptopi“.')
                ->action(function (AnanasValidatedMappingService $mappingService): void {
                    $result = $mappingService->apply();
                    $applied = count($result['applied']);

                    if ($applied === 0) {
                        Notification::make()
                            ->title('Nijedno mapiranje nije primijenjeno')
                            ->body(implode(' ', $result['skipped']) ?: 'BNC kategorije 199/231 nisu pronađene.')
                            ->warning()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Stage mapiranja primijenjena')
                        ->body($applied.' mapiranja spremno. Uvoz: Ananas → Postavke → Import dry-run.')
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
