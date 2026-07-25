<?php

namespace App\Filament\B2b\Resources\B2bAttributeDefinitionResource\Pages;

use App\Filament\B2b\Resources\B2bAttributeDefinitionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditB2bAttributeDefinition extends EditRecord
{
    protected static string $resource = B2bAttributeDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
