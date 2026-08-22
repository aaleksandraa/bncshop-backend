<?php

namespace App\Filament\Resources\ShopCampaignResource\Pages;

use App\Filament\Resources\ShopCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShopCampaign extends EditRecord
{
    protected static string $resource = ShopCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
