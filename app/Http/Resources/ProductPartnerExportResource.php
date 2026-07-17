<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductPartnerExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sifra' => $this->sku,
            'ean' => $this->barcode,
            'naziv' => $this->name,
            'cijena' => (float) $this->regular_price,
            'akcijska_cijena' => $this->on_sale ? (float) $this->display_price : null,
            'zaliha' => (int) $this->available_stock,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
