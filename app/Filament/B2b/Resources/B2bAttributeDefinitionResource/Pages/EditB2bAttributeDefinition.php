<?php

namespace App\Filament\B2b\Resources\B2bAttributeDefinitionResource\Pages;

use App\Filament\B2b\Resources\B2bAttributeDefinitionResource;
use App\Models\B2bAttributeDefinition;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditB2bAttributeDefinition extends EditRecord
{
    protected static string $resource = B2bAttributeDefinitionResource::class;

    /** @var array<int, int|string> */
    protected array $pendingCategoryIds = [];

    /** @var array<int, array<string, mixed>> */
    protected array $pendingOptionRows = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Obriši atribut'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['category_ids'] = $this->record->categories()->pluck('b2b_categories.id')->all();
        $data['option_rows'] = $this->record->options()
            ->orderBy('sort_order')
            ->orderBy('value')
            ->get()
            ->map(fn ($option): array => [
                'id' => $option->id,
                'value' => $option->value,
                'sort_order' => $option->sort_order,
            ])
            ->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingCategoryIds = array_values($data['category_ids'] ?? []);
        $this->pendingOptionRows = $data['option_rows'] ?? [];

        unset($data['category_ids'], $data['option_rows']);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var B2bAttributeDefinition $record */
        $record = $this->record;

        B2bAttributeDefinitionResource::syncCategories($record, $this->pendingCategoryIds);
        B2bAttributeDefinitionResource::syncOptions($record, $this->pendingOptionRows);
    }
}
