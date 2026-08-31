<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\Concerns\ManagesProductGratis;
use App\Filament\Resources\ProductResource\Pages\Concerns\ManagesProductSet;
use App\Filament\Resources\ProductResource\Pages\Concerns\ManagesProductSalePrice;
use App\Services\Pricing\ProductPriceRecalculator;
use App\Services\Pricing\ProductSalePriceService;
use App\Services\Sync\FieldLockService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    use ManagesProductGratis;
    use ManagesProductSet;
    use ManagesProductSalePrice;

    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('recalculatePrice')
                ->label('Preračunaj cijenu')
                ->icon('heroicon-o-calculator')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Preračunaj cijenu')
                ->modalDescription(fn (): string => $this->record->price_locked
                    ? 'Cijena je zaključana. Preračun ažurira izračunatu cijenu, ali ne dira redovnu/ručnu cijenu.'
                    : 'Upisuje nabavna × marža × PDV u izračunatu i redovnu cijenu (zaokruženo na cijeli KM).')
                ->visible(fn (): bool => $this->record !== null
                    && ! $this->record->isSet()
                    && ! $this->record->isFromEline())
                ->action(function (): void {
                    app(ProductPriceRecalculator::class)->forProduct($this->record);
                    $this->record->refresh();
                    $this->fillForm();

                    Notification::make()
                        ->title('Cijena preračunata')
                        ->body('Redovna cijena: '.number_format((float) $this->record->regular_price, 2, '.', '').' KM. Izračunata: '.number_format((float) $this->record->calculated_price, 2, '.', '').' KM.')
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = $this->mutateSetFormData($data);
        $data = $this->mutateGratisFormData($data);

        if ($this->record !== null && ! $this->record->isSet()) {
            $data = array_merge(
                $data,
                app(ProductSalePriceService::class)->toFormData($this->record),
            );
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->stripVirtualGratisFields(
            $this->stripVirtualSetFields($this->prepareSetProductData($data)),
        );
    }

    protected function afterSave(): void
    {
        $this->syncSetItemsIfNeeded();
        $this->syncSetImageIfNeeded();

        if ($this->record->isSet()) {
            return;
        }

        if ($this->record->wasChanged('margin_percentage')) {
            $lockService = app(FieldLockService::class);
            $margin = (float) ($this->record->margin_percentage ?? 0);

            if ($margin > 0) {
                $lockService->lockField($this->record, 'margin_percentage', auth()->id());
            } else {
                $lockService->unlockField($this->record, 'margin_percentage');
            }
        }

        $this->persistProductSalePriceFromForm();
        $this->syncGratisOffersIfNeeded();

        if ($this->record->wasChanged(['preferred_supplier_id', 'margin_percentage', 'price_locked', 'manual_price'])) {
            app(ProductPriceRecalculator::class)->forProduct($this->record->fresh());
        }
    }
}
