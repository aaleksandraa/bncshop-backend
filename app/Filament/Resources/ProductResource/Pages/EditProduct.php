<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\Concerns\ManagesProductSet;
use App\Services\Pricing\PriceCalculator;
use App\Services\Pricing\ProductPriceRecalculator;
use App\Services\Pricing\ProductSalePriceService;
use App\Services\Sync\FieldLockService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    use ManagesProductSet;

    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
        return $this->stripVirtualSetFields($this->prepareSetProductData($data));
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

        $state = $this->form->getState();
        $salePriceService = app(ProductSalePriceService::class);
        $product = $this->record->fresh();
        $salePriceRaw = $state['sale_price'] ?? null;
        $salePrice = $salePriceRaw === null || $salePriceRaw === ''
            ? null
            : (float) $salePriceRaw;

        $salePriceService->upsert(
            $product,
            $salePrice,
            is_string($state['sale_validity'] ?? null) ? $state['sale_validity'] : ProductSalePriceService::VALIDITY_NO_END,
            $state['sale_ends_at'] ?? null,
        );

        app(PriceCalculator::class)->recalculateAndPersist($product->fresh());

        if ($this->record->wasChanged(['preferred_supplier_id', 'margin_percentage', 'price_locked', 'manual_price'])) {
            app(ProductPriceRecalculator::class)->forProduct($product->fresh());
        }
    }
}
