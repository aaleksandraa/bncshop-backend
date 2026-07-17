<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\MarketingContact;
use App\Services\Marketing\MarketingContactSyncService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    public function mount(): void
    {
        parent::mount();

        if (MarketingContact::query()->count() === 0) {
            app(MarketingContactSyncService::class)->syncAll();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refreshContacts')
                ->label('Osvježi listu')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Osvježi kupce iz narudžbi i registracija')
                ->action(function (MarketingContactSyncService $syncService): void {
                    $count = $syncService->syncAll();

                    Notification::make()
                        ->title("Osvježeno {$count} kontakata.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
