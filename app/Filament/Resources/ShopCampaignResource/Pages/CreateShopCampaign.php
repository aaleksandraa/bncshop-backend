<?php

namespace App\Filament\Resources\ShopCampaignResource\Pages;

use App\Filament\Resources\ShopCampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShopCampaign extends CreateRecord
{
    protected static string $resource = ShopCampaignResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ShopCampaignResource::normalizeFormData($data);
    }
}
