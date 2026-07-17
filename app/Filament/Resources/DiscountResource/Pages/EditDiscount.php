<?php

namespace App\Filament\Resources\DiscountResource\Pages;

use App\Filament\Resources\DiscountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDiscount extends EditRecord
{
    protected static string $resource = DiscountResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function afterSave(): void
    {
        $this->record->load(['categories', 'manufacturers']);

        $this->record->update([
            'category_id' => $this->record->categories->first()?->id,
            'manufacturer_id' => $this->record->manufacturers->first()?->id,
        ]);
    }
}
