<?php

namespace App\Filament\Resources\AttributeDefinitionResource\Pages;

use App\Filament\Resources\AttributeDefinitionResource;
use Filament\Resources\Pages\EditRecord;

class EditAttributeDefinition extends EditRecord
{
    protected static string $resource = AttributeDefinitionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('is_public', $data) && $this->record->is_public !== (bool) $data['is_public']) {
            $data['is_public_locked'] = true;
        }

        if (! empty($data['display_name']) || ! empty($data['value_mappings'])) {
            $data['is_mapped'] = true;
        }

        if (($data['internal_type'] ?? null) === 'boolean' && empty($data['parsed_options'])) {
            $data['parsed_options'] = [
                'true' => 'Da',
                'false' => 'Ne',
            ];
        }

        return $data;
    }
}
