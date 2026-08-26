<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\Concerns\ManagesProductSet;
use App\Filament\Resources\ProductResource\Pages\Concerns\ManagesProductSalePrice;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    use ManagesProductSet;
    use ManagesProductSalePrice;

    protected static string $resource = ProductResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->stripVirtualSetFields($this->prepareSetProductData($data));
    }

    protected function afterCreate(): void
    {
        $this->syncSetItemsIfNeeded();
        $this->syncSetImageIfNeeded();
        $this->persistProductSalePriceFromForm();
    }
}
