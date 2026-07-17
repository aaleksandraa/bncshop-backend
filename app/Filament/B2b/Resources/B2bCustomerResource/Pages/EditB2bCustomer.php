<?php

namespace App\Filament\B2b\Resources\B2bCustomerResource\Pages;

use App\Filament\B2b\Resources\B2bCustomerResource;
use App\Services\B2b\B2bCustomerProvisioner;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditB2bCustomer extends EditRecord
{
    protected static string $resource = B2bCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sendAccess')
                ->label('Pošalji pristup')
                ->icon('heroicon-o-envelope')
                ->requiresConfirmation()
                ->action(function (): void {
                    app(B2bCustomerProvisioner::class)->sendPasswordSetupEmail($this->record->user);
                    Notification::make()->title('Email za postavljanje lozinke je poslan.')->success()->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
