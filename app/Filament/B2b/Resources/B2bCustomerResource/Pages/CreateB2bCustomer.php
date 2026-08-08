<?php

namespace App\Filament\B2b\Resources\B2bCustomerResource\Pages;

use App\Filament\B2b\Resources\B2bCustomerResource;
use App\Services\B2b\B2bCustomerProvisioner;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateB2bCustomer extends CreateRecord
{
    protected static string $resource = B2bCustomerResource::class;

    protected bool $passwordSetByAdmin = false;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // name/email/password are dehydrated(false) on the resource form — use raw state after validation.
        $formData = $this->form->getRawState();
        $password = filled($formData['password'] ?? null) ? (string) $formData['password'] : null;

        if ($password !== null && $password !== ($formData['password_confirmation'] ?? null)) {
            throw ValidationException::withMessages([
                'password_confirmation' => 'Lozinke se ne podudaraju.',
            ]);
        }

        $this->passwordSetByAdmin = $password !== null;

        return app(B2bCustomerProvisioner::class)->createCustomer([
            'name' => $formData['name'],
            'email' => $formData['email'],
            'phone' => $formData['phone'],
            'company_name' => $formData['company_name'],
            'company_address' => $formData['company_address'],
            'jib' => $formData['jib'],
            'pdv_number' => $formData['pdv_number'] ?? null,
            'discount_percent' => $formData['discount_percent'] ?? null,
        ], auth()->user(), sendPasswordEmail: false, password: $password);
    }

    protected function afterCreate(): void
    {
        if ($this->passwordSetByAdmin) {
            Notification::make()
                ->title('B2B kupac je kreiran.')
                ->body('Korisnik se može odmah prijaviti sa zadatom lozinkom.')
                ->success()
                ->send();

            return;
        }

        try {
            app(B2bCustomerProvisioner::class)->sendPasswordSetupEmail($this->record->user);

            Notification::make()
                ->title('Email za postavljanje lozinke je poslan korisniku.')
                ->body('Korisnik se ne može prijaviti dok ne postavi lozinku putem linka u emailu.')
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Kupac je kreiran, ali email nije poslan.')
                ->body($exception->getMessage().' Pošaljite pristup ponovo sa edit ekrana.')
                ->warning()
                ->send();
        }
    }
}
