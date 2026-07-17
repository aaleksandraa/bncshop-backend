<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        unset($data['is_customer'], $data['is_b2b_customer']);

        $record = static::getModel()::create($data);
        $record->forceFill([
            'is_customer' => false,
            'is_b2b_customer' => false,
        ])->save();

        if ($roles !== []) {
            $record->syncRoles($roles);
        }

        return $record;
    }
}
