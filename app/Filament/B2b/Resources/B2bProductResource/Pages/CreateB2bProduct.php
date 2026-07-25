<?php

namespace App\Filament\B2b\Resources\B2bProductResource\Pages;

use App\Filament\B2b\Resources\B2bProductResource;
use App\Services\B2b\B2bProductAttributeService;
use Filament\Resources\Pages\CreateRecord;

class CreateB2bProduct extends CreateRecord
{
    protected static string $resource = B2bProductResource::class;

    /** @var array<string, mixed> */
    protected array $pendingAttributeData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingAttributeData = B2bProductResource::extractAttributeFields($data);

        return B2bProductResource::stripAttributeFields($data);
    }

    protected function afterCreate(): void
    {
        app(B2bProductAttributeService::class)->syncFromForm(
            $this->record,
            $this->pendingAttributeData,
        );
    }
}
