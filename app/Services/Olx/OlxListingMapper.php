<?php

namespace App\Services\Olx;

use App\Models\OlxCategoryMapping;
use App\Models\Product;
use Illuminate\Support\Str;

class OlxListingMapper
{
    public function __construct(
        private readonly OlxSyncSettings $settings,
        private readonly OlxAttributeResolver $attributeResolver,
        private readonly OlxDescriptionBuilder $descriptionBuilder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function map(Product $product, OlxCategoryMapping $categoryMapping): array
    {
        $settings = $this->settings->all();
        $olxCategoryId = (int) $categoryMapping->olx_category_id;
        $attributes = $this->attributeResolver->resolveForProduct($product, $olxCategoryId);

        $payload = [
            'title' => $this->sanitizeTitle($product->name),
            'short_description' => $this->descriptionBuilder->buildShortDescription($product),
            'description' => $this->descriptionBuilder->buildDescription($product, $this->settings),
            'price' => (float) $product->display_price,
            'regular_price' => $product->on_sale ? (float) $product->regular_price : 0,
            'available' => $product->available_stock > 0,
            'category_id' => $olxCategoryId,
            'state' => $product->is_refurbished ? 'used' : 'new',
            'listing_type' => (string) ($settings['listing_type'] ?? 'sell'),
            'shipping' => (string) ($settings['shipping'] ?? 'no_shipping'),
            'country_id' => (int) ($settings['country_id'] ?? 49),
            'city_id' => (int) ($settings['city_id'] ?? 133),
            'location' => [
                'lat' => (float) ($settings['location_lat'] ?? config('bnc.olx_default_location_lat')),
                'lon' => (float) ($settings['location_lon'] ?? config('bnc.olx_default_location_lon')),
            ],
            'sku_number' => $this->resolveSku($product),
            'attributes' => $attributes,
        ];

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function fingerprintPayload(array $payload): string
    {
        $stable = [
            'title' => $payload['title'] ?? '',
            'short_description' => $payload['short_description'] ?? '',
            'description' => $payload['description'] ?? '',
            'price' => $payload['price'] ?? 0,
            'regular_price' => $payload['regular_price'] ?? 0,
            'available' => $payload['available'] ?? false,
            'state' => $payload['state'] ?? '',
            'category_id' => $payload['category_id'] ?? 0,
            'attributes' => $payload['attributes'] ?? [],
            'sku_number' => $payload['sku_number'] ?? '',
        ];

        return hash('sha256', json_encode($stable, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function sanitizeTitle(string $title): string
    {
        return Str::limit(trim(strip_tags($title)), 120, '');
    }

    private function resolveSku(Product $product): string
    {
        if (filled($product->sku)) {
            return (string) $product->sku;
        }

        return (string) $product->id;
    }
}
