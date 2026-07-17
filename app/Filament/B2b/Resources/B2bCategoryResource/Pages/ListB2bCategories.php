<?php

namespace App\Filament\B2b\Resources\B2bCategoryResource\Pages;

use App\Filament\B2b\Resources\B2bCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListB2bCategories extends ListRecords
{
    protected static string $resource = B2bCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
