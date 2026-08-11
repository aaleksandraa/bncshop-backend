<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use App\Services\Pricing\ProductPriceRecalculator;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected function afterSave(): void
    {
        if (! $this->record->wasChanged('price_adjustment_amount')) {
            return;
        }

        $count = app(ProductPriceRecalculator::class)
            ->forSupplierAndCategory($this->record->id);

        Notification::make()
            ->title('Cijene ažurirane')
            ->body("Re-kalkulirano {$count} proizvoda za dobavljača {$this->record->label()}.")
            ->success()
            ->send();
    }
}
