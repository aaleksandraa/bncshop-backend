<?php

namespace App\Filament\Resources\PartnerApiClientResource\Pages;

use App\Filament\Resources\PartnerApiClientResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPartnerApiClients extends ListRecords
{
    protected static string $resource = PartnerApiClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
