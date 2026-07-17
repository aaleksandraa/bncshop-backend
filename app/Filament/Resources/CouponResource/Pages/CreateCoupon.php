<?php

namespace App\Filament\Resources\CouponResource\Pages;

use App\Filament\Resources\CouponResource;
use App\Filament\Resources\CouponResource\Pages\Concerns\ManagesCouponScope;
use Filament\Resources\Pages\CreateRecord;

class CreateCoupon extends CreateRecord
{
    use ManagesCouponScope;

    protected static string $resource = CouponResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->persistCouponScopeFields($data);
    }
}
