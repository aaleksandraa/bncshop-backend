<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductPartnerUsedRemovalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sifra' => $this->sku ?: $this->eline_sifra,
            'uklonjen_at' => $this->updated_at?->toIso8601String(),
            'razlog' => $this->status !== 'active' ? 'neaktivan' : 'nije_javan',
        ];
    }
}
