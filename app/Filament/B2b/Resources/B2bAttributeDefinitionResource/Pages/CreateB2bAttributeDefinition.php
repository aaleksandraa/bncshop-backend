<?php

namespace App\Filament\B2b\Resources\B2bAttributeDefinitionResource\Pages;

use App\Filament\B2b\Resources\B2bAttributeDefinitionResource;
use App\Models\B2bAttributeDefinition;
use App\Models\B2bAttributeOption;
use Filament\Resources\Pages\CreateRecord;

class CreateB2bAttributeDefinition extends CreateRecord
{
    protected static string $resource = B2bAttributeDefinitionResource::class;

    /** @var array<int, int|string> */
    protected array $pendingCategoryIds = [];

    /** @var array<int, array<string, mixed>> */
    protected array $pendingOptionRows = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingCategoryIds = array_values($data['category_ids'] ?? []);
        $this->pendingOptionRows = $data['option_rows'] ?? [];

        unset($data['category_ids'], $data['option_rows']);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var B2bAttributeDefinition $record */
        $record = $this->record;

        B2bAttributeDefinitionResource::syncCategories($record, $this->pendingCategoryIds);
        B2bAttributeDefinitionResource::syncOptions($record, $this->pendingOptionRows);
    }
}
