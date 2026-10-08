<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductPartnerUsedExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $stock = (int) $this->available_stock;

        return [
            'id' => $this->id,
            'sifra' => $this->sku ?: $this->eline_sifra,
            'ean' => $this->barcode,
            'naziv' => $this->name,
            'cijena' => (float) $this->regular_price,
            'akcijska_cijena' => $this->on_sale ? (float) $this->display_price : null,
            'zaliha' => $stock,
            'izvor' => 'eline',
            'stanje_artikla' => 'polovan',
            'dostupnost' => $stock > 0 ? 'u_radnji' : 'nema',
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
