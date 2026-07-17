<?php

namespace App\Filament\Resources\ElineCategoryMappingResource\Pages;

use App\Filament\Resources\ElineCategoryMappingResource;
use Filament\Resources\Pages\EditRecord;

class EditElineCategoryMapping extends EditRecord
{
    protected static string $resource = ElineCategoryMappingResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
