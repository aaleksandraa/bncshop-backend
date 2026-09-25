<?php

namespace App\Services\Ananas;

use App\Models\AnanasCategoryMapping;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductImage;
use App\Services\Pricing\PriceCalculator;
use App\Support\PublicStorageUrl;
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

        return $this->buildPayload($product, $categoryMapping);
    }

    public function mapForProbe(Product $product, AnanasCategoryMapping $categoryMapping): array
    {
        $result = $this->eligibilityPolicy->evaluateProductData($product);

        if (! $result->eligible) {
            throw new RuntimeException(sprintf(
                'Product %d is not ready for Ananas probe: %s',
                $product->id,
                $result->reasonCode ?? 'NOT_ELIGIBLE',
            ));
        }

        return $this->buildPayload($product, $categoryMapping);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Product $product, AnanasCategoryMapping $categoryMapping): array
    {
        $product->loadMissing(['manufacturer', 'images', 'attributeValues.attributeDefinition']);

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

        $payload['brand'] = $this->resolveBrand($product);

        $attributes = $this->resolveAttributes($product);

        if ($attributes !== []) {
            $payload['attributes'] = $attributes;
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
            'attributes' => $payload['attributes'] ?? [],
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

            $url = $this->resolvePublicImageUrl($image);

            if ($url !== null) {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * Ananas must fetch coverImage/gallery over the public internet — never relative /storage paths.
     */
    private function resolvePublicImageUrl(ProductImage $image): ?string
    {
        if (filled($image->local_path)) {
            $absolute = PublicStorageUrl::absoluteFromResolved(
                PublicStorageUrl::url((string) $image->local_path),
            );

            if ($this->isPublicHttpsUrl($absolute)) {
                return $absolute;
            }
        }

        foreach ([$image->public_url, $image->image_url, $image->source_url, $image->resolvedUrl()] as $candidate) {
            if (! filled($candidate)) {
                continue;
            }

            $absolute = PublicStorageUrl::absoluteFromResolved((string) $candidate);

            if ($this->isPublicHttpsUrl($absolute)) {
                return $absolute;
            }
        }

        return null;
    }

    private function isPublicHttpsUrl(?string $url): bool
    {
        if (! is_string($url) || $url === '') {
            return false;
        }

        if (! str_starts_with($url, 'https://')) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return ! in_array($host, ['localhost', '127.0.0.1'], true);
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

    private function resolveBrand(Product $product): string
    {
        if ($product->manufacturer !== null && filled($product->manufacturer->name)) {
            return Str::limit(trim((string) $product->manufacturer->name), 255, '');
        }

        $fromConfig = trim((string) config('bnc.ananas_default_brand', 'BNC Shop'));

        return $fromConfig !== '' ? $fromConfig : 'BNC Shop';
    }

    /**
     * Ananas import field attributes: Map<String, List<String>> of all filled BNC specs.
     *
     * @return array<string, list<string>>
     */
    private function resolveAttributes(Product $product): array
    {
        $grouped = [];

        // Flat Map<String, List<String>> of every filled spec — same shape on every
        // SKU. Never nest under productType / Ananas category (they ulistavaju mixed
        // batches and asked not to send attributes "po kategorijama").
        foreach ($product->attributeValues as $value) {
            if (! $value instanceof ProductAttributeValue) {
                continue;
            }

            $label = $this->attributeLabel($value);
            $display = $this->attributeDisplayValue($value);

            if ($label === '' || $display === '') {
                continue;
            }

            $grouped[$label] ??= [];

            if (! in_array($display, $grouped[$label], true)) {
                $grouped[$label][] = $display;
            }
        }

        ksort($grouped, SORT_NATURAL | SORT_FLAG_CASE);

        return $grouped;
    }

    private function attributeLabel(ProductAttributeValue $value): string
    {
        $definition = $value->attributeDefinition;
        $label = trim((string) (
            $definition?->display_name
            ?: $definition?->name
            ?: $value->attribute_name_snapshot
        ));

        return Str::limit($label, 255, '');
    }

    private function attributeDisplayValue(ProductAttributeValue $value): string
    {
        $raw = trim((string) ($value->raw_value ?? ''));

        if ($raw === '') {
            $raw = trim((string) ($value->normalized_value ?? ''));
        }

        if ($raw === '') {
            return '';
        }

        $unit = trim((string) ($value->attributeDefinition?->display_unit ?? ''));

        if ($unit !== '' && ! str_contains(mb_strtolower($raw), mb_strtolower($unit))) {
            $raw .= ' '.$unit;
        }

        return Str::limit($raw, 500, '');
    }
}
