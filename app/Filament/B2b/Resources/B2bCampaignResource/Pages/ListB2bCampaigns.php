<?php

namespace App\Filament\B2b\Resources\B2bCampaignResource\Pages;

use App\Filament\B2b\Resources\B2bCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListB2bCampaigns extends ListRecords
{
    protected static string $resource = B2bCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
