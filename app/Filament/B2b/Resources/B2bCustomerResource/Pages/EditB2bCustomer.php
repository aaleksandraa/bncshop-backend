<?php

namespace App\Filament\B2b\Resources\B2bCustomerResource\Pages;

use App\Filament\B2b\Resources\B2bCustomerResource;
use App\Services\B2b\B2bCustomerProvisioner;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditB2bCustomer extends EditRecord
{
    protected static string $resource = B2bCustomerResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['name'] = $this->record->user?->name;
        $data['email'] = $this->record->user?->email;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // name/email are dehydrated(false) on the resource form — use raw state after validation.
        $formData = $this->form->getRawState();

        /** @var \App\Models\B2bCustomer $record */
        $record->user?->update([
            'name' => $formData['name'],
            'email' => $formData['email'],
            'phone' => $formData['phone'],
        ]);

        if (filled($formData['password'] ?? null)) {
            if (($formData['password'] ?? null) !== ($formData['password_confirmation'] ?? null)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'password_confirmation' => 'Lozinke se ne podudaraju.',
                ]);
            }

            app(B2bCustomerProvisioner::class)->setCustomerPassword($record->user, $formData['password']);

            Notification::make()
                ->title('Lozinka je ažurirana.')
                ->success()
                ->send();
        }

        $record->update($data);

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sendAccess')
                ->label('Pošalji ponovo pristup')
                ->icon('heroicon-o-envelope')
                ->requiresConfirmation()
                ->modalDescription('Korisniku će biti poslan novi link za postavljanje lozinke. Prethodni link prestaje važiti.')
                ->action(function (): void {
                    app(B2bCustomerProvisioner::class)->sendPasswordSetupEmail($this->record->user, force: true);
                    Notification::make()->title('Email za postavljanje lozinke je poslan.')->success()->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
