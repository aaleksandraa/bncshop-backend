<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use App\Jobs\RecalculateSupplierProductPricesJob;
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

        RecalculateSupplierProductPricesJob::dispatch(
            supplierId: $this->record->id,
            supplierLabel: $this->record->label(),
        )->afterCommit();

        Notification::make()
            ->title('Postavke spremljene')
            ->body("Re-kalkulacija cijena za dobavljača {$this->record->label()} je pokrenuta u pozadini. Promjene će se pojaviti na proizvodima u narednih nekoliko minuta.")
            ->success()
            ->send();
    }
}
