<?php

namespace App\Filament\Resources\ApiSourceResource\Pages;

use App\Filament\Resources\ApiSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListApiSources extends ListRecords
{
    protected static string $resource = ApiSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
