<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Services\Pricing\PriceCalculator;
use App\Services\Pricing\ProductSalePriceService;
use Illuminate\Contracts\Support\Arrayable;

trait ManagesProductSalePrice
{
    protected function persistProductSalePriceFromForm(): void
    {
        if ($this->record->isSet()) {
            return;
        }

        $rawState = $this->form->getRawState();

        if ($rawState instanceof Arrayable) {
            $rawState = $rawState->toArray();
        }

        $salePriceService = app(ProductSalePriceService::class);
        $product = $this->record->fresh();
        $salePriceRaw = $rawState['sale_price'] ?? null;
        $salePrice = $salePriceRaw === null || $salePriceRaw === ''
            ? null
            : (float) $salePriceRaw;

        $salePriceService->upsert(
            $product,
            $salePrice,
            is_string($rawState['sale_validity'] ?? null)
                ? $rawState['sale_validity']
                : ProductSalePriceService::VALIDITY_NO_END,
            $rawState['sale_ends_at'] ?? null,
        );

        $fresh = $product->fresh();

        if ($fresh->usesA1PriceFormula()) {
            app(PriceCalculator::class)->recalculateAndPersist($fresh);

            return;
        }

        $regular = (float) ($fresh->regular_price ?? 0);
        $display = (float) ($fresh->display_price ?? 0);

        if ($salePrice !== null && $salePrice > 0 && ($regular <= 0 || $salePrice < $regular)) {
            $fresh->update([
                'display_price' => round($salePrice, 2),
                'on_sale' => $regular > 0,
            ]);

            return;
        }

        $fresh->update([
            'on_sale' => $regular > 0 && $display > 0 && $display < $regular,
        ]);
    }
}
