<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Catalog\CampaignResolver;
use App\Services\Catalog\ProductGratisService;
use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;

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
            'is_set' => (bool) $this->is_set,
            'on_sale' => (bool) $this->on_sale,
            'campaign_badges' => $this->campaignBadges(),
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
            'set_items' => $this->when(
                $this->is_set && $this->relationLoaded('setItems'),
                fn () => $this->setItems
                    ->sortBy('sort_order')
                    ->values()
                    ->map(fn ($item): array => [
                        'name' => $item->componentProduct?->name,
                        'quantity' => (int) $item->quantity,
                    ])
                    ->all(),
            ),
            'gratis_offers' => $this->gratisOffers(),
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

        $url = $image instanceof ProductImage
            ? $image->resolvedUrl()
            : ($image->public_url ?: $image->image_url ?: $image->source_url);

        return [
            'id' => $image->id,
            'url' => PublicStorageUrl::absoluteFromResolved($url),
            'width' => $image->width ?? null,
            'height' => $image->height ?? null,
            'is_primary' => $image->is_primary ?? false,
        ];
    }

    /**
     * @return list<array{slug: string, name: string, image_url: string|null, landing_path: string|null, alt: string}>
     */
    private function campaignBadges(): array
    {
        try {
            return app(CampaignResolver::class)->badgesForProduct($this->resource);
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function gratisOffers(): array
    {
        try {
            return app(ProductGratisService::class)->displayPayloadsFor($this->resource);
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }
}
