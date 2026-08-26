<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Services\Catalog\ProductSetService;
use App\Services\Pricing\ProductPriceRecalculator;
use Illuminate\Support\Str;

trait ManagesProductSet
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareSetProductData(array $data): array
    {
        if (empty($data['external_product_id'])) {
            $data['external_product_id'] = (string) Str::uuid();
        }

        if (empty($data['import_source'])) {
            $data['import_source'] = 'manual';
        }

        if (! empty($data['is_set'])) {
            $data['price_locked'] = true;
            $data['display_price'] = $data['manual_price'] ?? $data['display_price'] ?? null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function stripVirtualSetFields(array $data): array
    {
        unset($data['set_items']);

        return $data;
    }

    protected function syncSetItemsIfNeeded(): void
    {
        $state = $this->form->getState();

        if (empty($state['is_set'])) {
            if ($this->record->setItems()->exists()) {
                $this->record->setItems()->delete();
            }

            return;
        }

        $items = collect($state['set_items'] ?? [])
            ->filter(fn (array $row): bool => filled($row['component_product_id'] ?? null))
            ->map(fn (array $row): array => [
                'component_product_id' => (int) $row['component_product_id'],
                'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
            ])
            ->values()
            ->all();

        app(ProductSetService::class)->syncSetItems($this->record, $items);

        if ($this->record->manual_price !== null) {
            app(ProductPriceRecalculator::class)->forProduct($this->record->fresh(['setItems.componentProduct']));
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function mutateSetFormData(array $data): array
    {
        if ($this->record?->isSet()) {
            $data['set_items'] = $this->record->setItems()
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($item): array => [
                    'component_product_id' => $item->component_product_id,
                    'quantity' => $item->quantity,
                ])
                ->all();
        }

        return $data;
    }
}
