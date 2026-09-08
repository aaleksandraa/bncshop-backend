<?php

namespace App\Services\Ananas;

use App\Models\AnanasCategoryMapping;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Support\Str;

class AnanasProductMapper
{
    public function __construct(
        private readonly AnanasEligibilityPolicy $eligibilityPolicy,
        private readonly AnanasPackageWeightResolver $packageWeightResolver,
        private readonly PriceCalculator $priceCalculator,
    ) {}

    /**
     * Build Ananas import payload for a product. Does not perform HTTP writes.
     *
     * @return array<string, mixed>
     */
    public function map(Product $product, AnanasCategoryMapping $categoryMapping): array
    {
        $this->eligibilityPolicy->assertCanExport($product);

        $product->loadMissing(['manufacturer', 'images']);

        $weight = $this->packageWeightResolver->resolve($product);
        $pricing = $this->priceCalculator->calculate($product);
        $images = $this->resolveImageUrls($product);
        $coverImage = $images[0] ?? null;

        if ($coverImage === null) {
            throw new \RuntimeException('Product '.$product->id.' has no resolvable cover image.');
        }

        $payload = [
            'name' => $this->sanitizeName((string) $product->name),
            'description' => $this->sanitizeDescription((string) ($product->description ?: $product->short_description ?: $product->name)),
            'coverImage' => $coverImage,
            'ean' => trim((string) $product->barcode),
            'gallery' => $images,
            'packageWeightValue' => $weight->resolvedWeightKg,
            'packageWeightUnit' => 'KG',
            'basePrice' => round($pricing->regularPrice, 2),
            'vat' => $this->eligibilityPolicy->resolvedVatRate(),
            'stockLevel' => max(0, (int) $product->available_stock),
            'sku' => $this->resolveSku($product),
            'externalId' => (string) $product->id,
            'productType' => $categoryMapping->ananas_product_type,
        ];

        if (filled($categoryMapping->ananas_category)) {
            $payload['category'] = $categoryMapping->ananas_category;
        }

        if ($product->manufacturer !== null && filled($product->manufacturer->name)) {
            $payload['brand'] = (string) $product->manufacturer->name;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function fingerprintPayload(array $payload): string
    {
        $stable = [
            'name' => $payload['name'] ?? '',
            'description' => $payload['description'] ?? '',
            'coverImage' => $payload['coverImage'] ?? '',
            'ean' => $payload['ean'] ?? '',
            'gallery' => $payload['gallery'] ?? [],
            'packageWeightValue' => $payload['packageWeightValue'] ?? null,
            'basePrice' => $payload['basePrice'] ?? 0,
            'vat' => $payload['vat'] ?? null,
            'stockLevel' => $payload['stockLevel'] ?? 0,
            'sku' => $payload['sku'] ?? '',
            'externalId' => $payload['externalId'] ?? '',
            'productType' => $payload['productType'] ?? '',
            'category' => $payload['category'] ?? '',
            'brand' => $payload['brand'] ?? '',
        ];

        return hash('sha256', json_encode($stable, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<string>
     */
    private function resolveImageUrls(Product $product): array
    {
        $urls = [];

        foreach ($product->images->sortBy('sort_order')->sortByDesc('is_primary') as $image) {
            if (! $image instanceof ProductImage || $image->status !== 'active') {
                continue;
            }

            $url = $image->resolvedUrl();

            if (filled($url)) {
                $urls[] = (string) $url;
            }
        }

        return array_values(array_unique($urls));
    }

    private function sanitizeName(string $name): string
    {
        return Str::limit(trim(strip_tags($name)), 255, '');
    }

    private function sanitizeDescription(string $description): string
    {
        $allowed = '<br><ul><ol><li>';

        return Str::limit(trim(strip_tags($description, $allowed)), 50000, '');
    }

    private function resolveSku(Product $product): string
    {
        if (filled($product->sku)) {
            return (string) $product->sku;
        }

        return 'BNC-'.$product->id;
    }
}
