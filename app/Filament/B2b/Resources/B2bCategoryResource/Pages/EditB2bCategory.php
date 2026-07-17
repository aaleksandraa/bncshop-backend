<?php

namespace App\Filament\B2b\Resources\B2bCategoryResource\Pages;

use App\Filament\B2b\Resources\B2bCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditB2bCategory extends EditRecord
{
    protected static string $resource = B2bCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
