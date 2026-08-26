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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = ShopCampaignResource::normalizeFormData($data);

        if (blank($data['badge_path'] ?? null) && filled($this->record?->badge_path)) {
            $data['badge_path'] = $this->record->badge_path;
        }

        return $data;
    }
}
