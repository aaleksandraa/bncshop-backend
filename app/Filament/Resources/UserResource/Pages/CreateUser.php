<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $roles = $data['roles'] ?? [];
        unset($data['roles'], $data['is_customer'], $data['is_b2b_customer']);

        $record = User::createAccount([
            ...$data,
            'email_verified_at' => $data['email_verified_at'] ?? now(),
            'is_customer' => false,
            'is_b2b_customer' => false,
        ]);

        if ($roles !== []) {
            $record->syncRoles($roles);
        }

        return $record;
    }

    protected function afterCreate(): void
    {
        $roles = $this->record->roles->pluck('name');

        if ($roles->contains('B2B Admin') && ! $roles->contains('Super Admin') && ! $roles->contains('Admin')) {
            Notification::make()
                ->title('B2B admin korisnik kreiran')
                ->body('Prijava ide na /b2b-admin/login (backend domena, npr. api.bncshop.ba). Glavni /admin panel ovom korisniku nije dostupan.')
                ->success()
                ->send();
        }
    }
}
