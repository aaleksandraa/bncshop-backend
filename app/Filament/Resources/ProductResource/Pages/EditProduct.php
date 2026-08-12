<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Services\Pricing\ProductPriceRecalculator;
use App\Services\Sync\FieldLockService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        if ($this->record->wasChanged('margin_percentage')) {
            $lockService = app(FieldLockService::class);
            $margin = (float) ($this->record->margin_percentage ?? 0);

            if ($margin > 0) {
                $lockService->lockField($this->record, 'margin_percentage', auth()->id());
            } else {
                $lockService->unlockField($this->record, 'margin_percentage');
            }
        }

        if ($this->record->wasChanged(['preferred_supplier_id', 'margin_percentage', 'price_locked', 'manual_price'])) {
            app(ProductPriceRecalculator::class)->forProduct($this->record);
        }
    }
}
