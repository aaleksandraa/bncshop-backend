<?php

namespace App\Filament\B2b\Resources\B2bCampaignResource\Pages;

use App\Filament\B2b\Resources\B2bCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditB2bCampaign extends EditRecord
{
    protected static string $resource = B2bCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
