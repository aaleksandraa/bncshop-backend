<?php

namespace App\Filament\Resources\LoyaltyRewardResource\Pages;

use App\Filament\Resources\LoyaltyRewardResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLoyaltyRewards extends ListRecords
{
    protected static string $resource = LoyaltyRewardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
