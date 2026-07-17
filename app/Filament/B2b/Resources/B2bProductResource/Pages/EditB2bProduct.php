<?php

namespace App\Filament\B2b\Resources\B2bProductResource\Pages;

use App\Filament\B2b\Resources\B2bProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditB2bProduct extends EditRecord
{
    protected static string $resource = B2bProductResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
