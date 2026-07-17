<?php

namespace App\Filament\Resources\DiscountResource\Pages;

use App\Filament\Resources\DiscountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDiscount extends CreateRecord
{
    protected static string $resource = DiscountResource::class;

    protected function afterCreate(): void
    {
        $this->syncLegacyScopeColumns();
    }

    protected function syncLegacyScopeColumns(): void
    {
        $this->record->load(['categories', 'manufacturers']);

        $this->record->update([
            'category_id' => $this->record->categories->first()?->id,
            'manufacturer_id' => $this->record->manufacturers->first()?->id,
        ]);
    }
}
