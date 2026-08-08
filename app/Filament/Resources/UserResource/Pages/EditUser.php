<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['roles'] = $this->record->roles->pluck('name')->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $roles = $data['roles'] ?? [];
        unset($data['roles'], $data['is_customer'], $data['is_b2b_customer']);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $record->update($data);
        $record->forceFill([
            'is_customer' => false,
            'is_b2b_customer' => false,
        ])->save();

        $record->syncRoles($roles);

        return $record;
    }
}
