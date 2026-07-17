<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'short_description' => $this->short_description,
            'regular_price' => $this->regular_price,
            'display_price' => $this->display_price,
            'available_stock' => $this->available_stock,
            'stock_status' => $this->stock_status,
            'is_new' => $this->is_new,
            'is_gaming' => $this->is_gaming,
            'is_refurbished' => $this->is_refurbished,
            'on_sale' => (bool) $this->on_sale,
            'manufacturer' => $this->whenLoaded('manufacturer', fn () => $this->manufacturer ? [
                'id' => $this->manufacturer->id,
                'name' => $this->manufacturer->name,
                'slug' => $this->manufacturer->slug,
                'logo_url' => $this->manufacturer->logoUrl(),
            ] : null),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->publicName(),
                'full_slug' => $this->category->full_slug,
            ] : null),
            'default_image' => $this->whenLoaded('defaultImage', fn () => $this->formatImage($this->defaultImage)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatImage(?object $image): ?array
    {
        if (! $image) {
            return null;
        }

        $url = $image instanceof \App\Models\ProductImage
            ? $image->resolvedUrl()
            : ($image->public_url ?: $image->image_url ?: $image->source_url);

        return [
            'id' => $image->id,
            'url' => $url,
            'width' => $image->width ?? null,
            'height' => $image->height ?? null,
            'is_primary' => $image->is_primary ?? false,
        ];
    }
}
