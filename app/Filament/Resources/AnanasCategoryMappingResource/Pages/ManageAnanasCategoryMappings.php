<?php

namespace App\Filament\Resources\AnanasCategoryMappingResource\Pages;

use App\Filament\Resources\AnanasCategoryMappingResource;
use App\Services\Ananas\AnanasCategoryMappingProposer;
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
            Actions\Action::make('proposeFromProductTypes')
                ->label('Predloži ostale kategorije')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Predloži BNC → Ananas mapiranja')
                ->modalDescription('Uspoređuje BNC leaf kategorije sa katalogom Ananas category stringova (Monitori, Slušalice…), ne sa 10 product-type šablona. Kreira isključena mapiranja.')
                ->action(function (AnanasCategoryMappingProposer $proposer): void {
                    try {
                        $result = $proposer->propose(minProducts: 1, minScore: (int) config('bnc.ananas_mapping_min_score', 82), refreshTypes: true);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Prijedlog nije uspio')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    $applied = $proposer->applySuggestions(
                        $result['suggestions'],
                        enableExact: false,
                        productType: (string) config('bnc.ananas_mapping_default_product_type', 'ITShop'),
                    );

                    Notification::make()
                        ->title('Prijedlozi upisani')
                        ->body($applied['created'].' novih mapiranja (isključena). Bez poklapanja: '.count($result['unmatched']).'. Uključite samo tačne redove.')
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
