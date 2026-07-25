<?php

namespace App\Filament\B2b\Resources\B2bProductResource\Pages;

use App\Filament\B2b\Resources\B2bProductResource;
use App\Services\B2b\B2bProductAttributeService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditB2bProduct extends EditRecord
{
    protected static string $resource = B2bProductResource::class;

    /** @var array<string, mixed> */
    protected array $pendingAttributeData = [];

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [
            ...$data,
            ...app(B2bProductAttributeService::class)->hydrateFormState($this->record),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingAttributeData = B2bProductResource::extractAttributeFields($data);

        return B2bProductResource::stripAttributeFields($data);
    }

    protected function afterSave(): void
    {
        app(B2bProductAttributeService::class)->syncFromForm(
            $this->record,
            $this->pendingAttributeData,
        );
    }
}
