<?php

namespace App\Filament\Resources\AnanasCategoryMappingResource\Pages;

use App\Filament\Resources\AnanasCategoryMappingResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAnanasCategoryMappings extends ManageRecords
{
    protected static string $resource = AnanasCategoryMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
