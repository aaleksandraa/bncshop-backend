<?php

namespace App\Http\Resources;

use App\Services\Catalog\AttributeDisplayService;
use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductPartnerFullExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $displayService = app(AttributeDisplayService::class);

        return [
            'id' => $this->id,
            'sifra' => $this->sku,
            'ean' => $this->barcode,
            'naziv' => $this->name,
            'cijena' => (float) $this->regular_price,
            'akcijska_cijena' => $this->on_sale ? (float) $this->display_price : null,
            'zaliha' => (int) $this->available_stock,
            'opis' => $this->description,
            'kratki_opis' => $this->short_description,
            'kategorija' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'naziv' => $this->category->publicName(),
                'putanja' => $this->category->full_slug,
            ] : null),
            'proizvodjac' => $this->whenLoaded('manufacturer', fn () => $this->manufacturer ? [
                'id' => $this->manufacturer->id,
                'naziv' => $this->manufacturer->name,
            ] : null),
            'atributi' => $this->whenLoaded('attributeValues', function () use ($displayService): array {
                return collect($displayService->formatManyForProduct(
                    $this->attributeValues,
                    $this->category_id,
                ))->map(fn (array $attribute): array => [
                    'naziv' => $attribute['display_name'] ?? '',
                    'vrijednost' => $attribute['display_value'] ?? '',
                ])->values()->all();
            }),
            'slike' => $this->whenLoaded('images', fn () => $this->images
                ->sortBy('sort_order')
                ->values()
                ->map(fn ($image): array => [
                    'url' => PublicStorageUrl::absoluteFromResolved(
                        $image->public_url ?: $image->image_url ?: $image->source_url,
                    ),
                    'glavna' => (bool) ($image->is_primary ?? ($this->default_image_id === $image->id)),
                    'redoslijed' => (int) ($image->sort_order ?? 0),
                ])
                ->all()),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
