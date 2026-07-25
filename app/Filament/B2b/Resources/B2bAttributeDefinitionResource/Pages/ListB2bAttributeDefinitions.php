<?php

namespace App\Filament\B2b\Resources\B2bAttributeDefinitionResource\Pages;

use App\Filament\B2b\Resources\B2bAttributeDefinitionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListB2bAttributeDefinitions extends ListRecords
{
    protected static string $resource = B2bAttributeDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
