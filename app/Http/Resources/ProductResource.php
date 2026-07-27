<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Services\Catalog\AttributeDisplayService;
use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $displayService = app(AttributeDisplayService::class);

        return [
            'id' => $this->id,
            'manufacturer_id' => $this->manufacturer_id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'is_gaming' => $this->is_gaming,
            'is_new' => $this->is_new,
            'is_refurbished' => $this->is_refurbished,
            'regular_price' => $this->regular_price,
            'display_price' => $this->display_price,
            'available_stock' => $this->available_stock,
            'stock_status' => $this->stock_status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'manufacturer' => $this->whenLoaded('manufacturer', fn () => $this->manufacturer ? [
                'id' => $this->manufacturer->id,
                'name' => $this->manufacturer->name,
                'slug' => $this->manufacturer->slug,
                'logo_url' => PublicStorageUrl::absoluteFromResolved($this->manufacturer->logoUrl()),
            ] : null),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->publicName(),
                'full_slug' => $this->category->full_slug,
            ] : null),
            'default_image' => $this->whenLoaded('defaultImage', fn () => $this->formatImage($this->defaultImage)),
            'images' => $this->whenLoaded('images', fn () => $this->images
                ->where('status', 'active')
                ->sortBy('sort_order')
                ->values()
                ->map(fn ($image) => $this->formatImage($image))
                ->all()),
            'attribute_values' => $this->whenLoaded('attributeValues', fn () => $displayService->formatManyForProduct(
                $this->attributeValues,
                $this->category_id,
            )),
            'tags' => $this->whenLoaded('tags'),
            'seo_override' => $this->whenLoaded('seoOverride'),
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
            'url' => PublicStorageUrl::absoluteFromResolved($url),
            'width' => $image->width ?? null,
            'height' => $image->height ?? null,
            'is_primary' => $image->is_primary,
            'sort_order' => $image->sort_order,
        ];
    }
}
