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

        $record->update($data);

        return $record;
    }

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
