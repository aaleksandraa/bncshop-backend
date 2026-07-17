<?php

namespace App\Filament\Resources\CouponResource\Pages;

use App\Filament\Resources\CouponResource;
use App\Filament\Resources\CouponResource\Pages\Concerns\ManagesCouponScope;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCoupon extends EditRecord
{
    use ManagesCouponScope;

    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->hydrateCouponScopeFields($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->persistCouponScopeFields($data);
    }
}
