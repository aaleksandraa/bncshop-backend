<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Services\Catalog\ProductGratisService;
use Illuminate\Contracts\Support\Arrayable;

trait ManagesProductGratis
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateGratisFormData(array $data): array
    {
        if ($this->record?->isSet()) {
            return $data;
        }

        if ($this->record !== null) {
            $data['gratis_offers'] = app(ProductGratisService::class)->toFormRows($this->record);
        }

        return $data;
    }

    protected function syncGratisOffersIfNeeded(): void
    {
        if ($this->record->isSet()) {
            return;
        }

        $rawState = $this->form->getRawState();

        if ($rawState instanceof Arrayable) {
            $rawState = $rawState->toArray();
        }

        $rows = collect($rawState['gratis_offers'] ?? [])
            ->filter(function (array $row): bool {
                if ((bool) ($row['is_active'] ?? true) === false && empty($row['title']) && empty($row['gift_product_id'])) {
                    return false;
                }

                return filled($row['type'] ?? null)
                    || filled($row['title'] ?? null)
                    || filled($row['gift_product_id'] ?? null);
            })
            ->values()
            ->all();

        app(ProductGratisService::class)->syncOffers($this->record->fresh(), $rows);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function stripVirtualGratisFields(array $data): array
    {
        unset($data['gratis_offers']);

        return $data;
    }
}
