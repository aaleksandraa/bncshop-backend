<?php

namespace App\Services\Sync;

use App\Models\ApiSource;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductImage;
use App\Models\ProductSupplierOffer;
use App\Models\SeoOverride;
use App\Models\Supplier;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Support\Str;

class ProductImporter
{
    public function __construct(
        private readonly FieldLockService $fieldLockService,
        private readonly AttributeNormalizer $attributeNormalizer,
        private readonly PriceCalculator $priceCalculator,
        private readonly SupplierNameNormalizer $supplierNameNormalizer,
        private readonly ProductImageStorageService $productImageStorage,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsertOne(array $payload, ?ApiSource $source = null): ProductUpsertResult
    {
        $externalId = (string) ($payload['productId'] ?? $payload['external_product_id'] ?? '');
        $product = Product::query()->firstOrNew(['external_product_id' => $externalId]);
        $wasExisting = $product->exists;
        $wasPublic = $wasExisting ? (bool) $product->is_public : null;
        $snapshot = $wasExisting ? ProductImportChangeTracker::snapshot($product) : [];

        if (! $wasExisting) {
            $product->first_imported_at = now();
            $product->sync_status = 'synced';
        }

        if ($source !== null) {
            $product->api_source_id = $source->id;
            $product->import_source = $source->target_system_code ?? $source->name ?? 'api';
        }

        $this->applyScalarFields($product, $payload);

        $product->manufacturer_id = $this->resolveManufacturer($payload['manufacturer'] ?? null)?->id;
        $product->category_id = $this->resolveCategory($payload['category'] ?? null)?->id;

        $product->save();

        $attributesChanged = $this->syncAttributes($product, $payload['attributes'] ?? []);
        $imagesChanged = $this->syncImages(
            $product,
            $payload['gallery'] ?? [],
            (string) ($payload['defaultImageId'] ?? ''),
            (string) ($payload['defaultImageUrl'] ?? ''),
        );
        $offersChanged = $this->syncSupplierOffers($product, $payload['supplierOffers'] ?? $payload['supplier_offers'] ?? []);
        $this->syncSeo($product, $payload['seoFields'] ?? $payload['seo'] ?? []);

        $this->recalculateStock($product);
        $this->priceCalculator->recalculateAndPersist($product);

        $product->update([
            'sync_status' => 'synced',
            'marked_missing_at' => null,
        ]);

        $freshProduct = $product->fresh();
        $changedFields = $wasExisting
            ? ProductImportChangeTracker::diff($snapshot, $freshProduct)
            : [];

        if ($attributesChanged) {
            $changedFields[] = 'attributes';
        }

        if ($imagesChanged) {
            $changedFields[] = 'images';
        }

        if ($offersChanged) {
            $changedFields[] = 'supplier_offers';
        }

        $payloadIsPublic = (bool) ($payload['isPublic'] ?? false);

        $action = match (true) {
            ! $wasExisting => 'inserted',
            ! $payloadIsPublic && $wasPublic => 'deactivated',
            default => 'updated',
        };

        return new ProductUpsertResult(
            action: $action,
            product: $freshProduct,
            changedFields: array_values(array_unique($changedFields)),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $payloads
     * @return list<ProductUpsertResult>
     */
    public function upsertMany(array $payloads, int $chunkSize = 50, ?ApiSource $source = null): array
    {
        $results = [];

        foreach (array_chunk($payloads, max(1, $chunkSize)) as $chunk) {
            foreach ($chunk as $payload) {
                $results[] = $this->upsertOne($payload, $source);
            }
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function applyScalarFields(Product $product, array $payload): void
    {
        $fieldMap = [
            'name' => fn (): mixed => trim((string) ($payload['name'] ?? '')),
            'slug' => fn (): string => $this->resolveSlug($product, (string) ($payload['slug'] ?? '')),
            'description' => fn (): ?string => $this->sanitizeHtml($payload['description'] ?? null),
            'short_description' => fn (): ?string => $payload['shortDescription'] ?? $payload['short_description'] ?? null,
            'barcode' => fn (): ?string => $payload['barcode'] ?? null,
            'is_gaming' => fn (): bool => (bool) ($payload['isGaming'] ?? false),
            'is_public' => fn (): bool => (bool) ($payload['isPublic'] ?? false),
            'is_new' => fn (): bool => (bool) ($payload['isNew'] ?? false),
            'margin_percentage' => fn (): mixed => $this->resolveImportedMarginPercentage($product, $payload),
            'api_price' => fn (): mixed => $payload['price'] ?? null,
            'api_final_price' => fn (): mixed => $payload['finalPrice'] ?? $payload['final_price'] ?? null,
            'regular_price' => fn (): mixed => $payload['price'] ?? null,
            'api_rebate' => fn (): mixed => $payload['rebate'] ?? null,
            'api_rebate_valid_until' => fn (): mixed => $payload['rebateValidUntil'] ?? null,
            'api_rebate_type' => fn (): mixed => $payload['rebateType'] ?? null,
            'api_stock' => fn (): int => (int) ($payload['stock'] ?? 0),
            'api_views_count' => fn (): int => (int) ($payload['viewsCount'] ?? 0),
            'api_default_image_id' => fn (): ?string => $payload['defaultImageId'] ?? null,
            'api_default_image_url' => fn (): ?string => $payload['defaultImageUrl'] ?? null,
        ];

        foreach ($fieldMap as $field => $resolver) {
            $newValue = $resolver();

            if (! $product->exists) {
                $product->{$field} = $newValue;
                continue;
            }

            $currentValue = $product->{$field};

            if ($this->fieldLockService->shouldApply($product, $field, $newValue, $currentValue)) {
                $product->{$field} = $newValue;
            }
        }

        if ($product->status === null) {
            $product->status = 'active';
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveImportedMarginPercentage(Product $product, array $payload): mixed
    {
        if ((bool) ($payload['isNew'] ?? false)) {
            return null;
        }

        return $payload['marginPercentage'] ?? null;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function resolveManufacturer(?array $payload): ?Manufacturer
    {
        if (! $payload) {
            return null;
        }

        $externalId = (string) ($payload['manufacturerId'] ?? '');

        $attributes = [
            'name' => (string) ($payload['name'] ?? ''),
            'slug' => (string) ($payload['slug'] ?? Str::slug((string) ($payload['name'] ?? 'brand'))),
            'external_id' => $payload['externalId'] ?? null,
            'system' => (bool) ($payload['system'] ?? true),
            'featured' => (bool) ($payload['featured'] ?? false),
            'description' => $payload['description'] ?? null,
            'meta_title' => $payload['metaTitle'] ?? null,
            'meta_description' => $payload['metaDescription'] ?? null,
        ];

        $logoUrl = $payload['logo']['publicUrl'] ?? $payload['logoUrl'] ?? null;

        if ($logoUrl !== null) {
            $attributes['logo_url'] = $logoUrl;
        }

        return Manufacturer::query()->updateOrCreate(
            ['external_manufacturer_id' => $externalId],
            $attributes
        );
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function resolveCategory(?array $payload): ?Category
    {
        if (! $payload) {
            return null;
        }

        $externalId = (string) ($payload['categoryId'] ?? '');

        return Category::query()->where('external_category_id', $externalId)->first();
    }

    private function resolveSlug(Product $product, string $slug): string
    {
        if ($product->exists && $this->fieldLockService->isLocked($product, 'slug')) {
            return $product->slug;
        }

        $base = $slug !== '' ? $slug : Str::slug((string) $product->name);
        $candidate = $base;
        $suffix = 2;

        while (
            Product::query()
                ->where('slug', $candidate)
                ->when($product->exists, fn ($q) => $q->where('id', '!=', $product->id))
                ->exists()
        ) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @param  array<int, array<string, mixed>>  $attributes
     */
    private function syncAttributes(Product $product, array $attributes): bool
    {
        $changed = false;
        $seenDefinitionIds = [];

        foreach ($attributes as $attributePayload) {
            $externalAttributeId = (string) (
                $attributePayload['attributeId']
                ?? $attributePayload['attribute']
                ?? ''
            );

            $definition = AttributeDefinition::query()
                ->where('external_attribute_id', $externalAttributeId)
                ->first();

            if (! $definition) {
                $definition = AttributeDefinition::query()->create([
                    'external_attribute_id' => $externalAttributeId,
                    'name' => (string) ($attributePayload['attributeName'] ?? 'Unknown'),
                    'internal_type' => 'text',
                    'is_public' => true,
                    'is_filter' => false,
                ]);
            }

            $definition->loadMissing('canonicalDefinition');
            $targetDefinition = $definition->resolveCanonical();
            $targetDefinitionId = $targetDefinition->id;

            $seenDefinitionIds[] = $targetDefinitionId;
            $rawValue = (string) ($attributePayload['value'] ?? '');
            $normalized = $this->attributeNormalizer->normalize($rawValue, $targetDefinition->internal_type);

            $existing = ProductAttributeValue::query()
                ->where('product_id', $product->id)
                ->where('attribute_definition_id', $targetDefinitionId)
                ->first();

            if ($existing?->is_locked) {
                continue;
            }

            if (
                ! $existing
                || $existing->raw_value !== $rawValue
                || $existing->normalized_value !== $normalized['normalized_value']
            ) {
                $changed = true;
            }

            ProductAttributeValue::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'attribute_definition_id' => $targetDefinitionId,
                ],
                [
                    'external_id' => $attributePayload['externalId'] ?? null,
                    'attribute_name_snapshot' => (string) ($attributePayload['attributeName'] ?? $targetDefinition->name),
                    'raw_value' => $rawValue,
                    'normalized_value' => $normalized['normalized_value'],
                    'normalized_type' => $normalized['normalized_type'],
                ]
            );
        }

        $deletedCount = ProductAttributeValue::query()
            ->where('product_id', $product->id)
            ->whereNotIn('attribute_definition_id', AttributeDefinition::expandedDefinitionIds(...$seenDefinitionIds))
            ->where('is_locked', false)
            ->delete();

        return $changed || $deletedCount > 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $gallery
     */
    private function syncImages(
        Product $product,
        array $gallery,
        string $defaultImageId = '',
        string $defaultImageUrl = '',
    ): bool {
        $changed = false;

        if ($gallery === []) {
            $removedCount = ProductImage::query()
                ->where('product_id', $product->id)
                ->where('status', 'active')
                ->update(['status' => 'removed']);

            if ($removedCount > 0 || $product->default_image_id !== null) {
                $changed = true;
            }

            $product->update(['default_image_id' => null]);

            return $changed;
        }

        $seenExternalIds = [];
        $defaultImageRecord = null;

        foreach ($gallery as $index => $imagePayload) {
            $externalImageId = (string) ($imagePayload['imageId'] ?? '');
            $imageData = $imagePayload['image'] ?? [];
            $seenExternalIds[] = $externalImageId;

            $resolvedUrl = (string) (
                $imagePayload['imageUrl']
                ?? $imageData['publicUrl']
                ?? $imageData['sourceUrl']
                ?? ''
            );

            $isPrimary = (bool) ($imagePayload['isPrimary'] ?? false)
                || ($defaultImageId !== '' && $externalImageId === $defaultImageId)
                || ($index === 0 && $defaultImageId === '');

            $existingImage = ProductImage::query()
                ->where('product_id', $product->id)
                ->where('external_image_id', $externalImageId ?: null)
                ->first();

            $image = ProductImage::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'external_image_id' => $externalImageId ?: null,
                ],
                [
                    'stored_file_id' => $imageData['storedFileId'] ?? $externalImageId ?: null,
                    'image_url' => $resolvedUrl,
                    'source_url' => $imageData['sourceUrl'] ?? null,
                    'public_url' => $imageData['publicUrl'] ?? $imagePayload['imageUrl'] ?? $resolvedUrl ?: null,
                    'storage_key' => $imageData['storageKey'] ?? null,
                    'original_file_name' => $imageData['originalFileName'] ?? null,
                    'stored_file_name' => $imageData['storedFileName'] ?? null,
                    'content_type' => $imageData['contentType'] ?? null,
                    'file_extension' => $imageData['fileExtension'] ?? null,
                    'file_type' => isset($imageData['fileType']) ? (int) $imageData['fileType'] : null,
                    'is_public' => array_key_exists('isPublic', $imageData) ? (bool) $imageData['isPublic'] : null,
                    'file_size_bytes' => $imageData['fileSizeBytes'] ?? null,
                    'width' => $imageData['width'] ?? null,
                    'height' => $imageData['height'] ?? null,
                    'is_primary' => $isPrimary,
                    'sort_order' => $index,
                    'status' => 'active',
                ]
            );

            if (
                ! $existingImage
                || $existingImage->sort_order !== $index
                || $existingImage->is_primary !== $isPrimary
                || $existingImage->status !== 'active'
                || $existingImage->image_url !== $resolvedUrl
            ) {
                $changed = true;
            }

            if (config('bnc.product_image_download_on_import', true)) {
                $this->productImageStorage->storeFromRemote($image, $product);
            }

            if ($isPrimary) {
                $defaultImageRecord = $image;
            }
        }

        if ($defaultImageRecord === null && $defaultImageId !== '') {
            $defaultImageRecord = ProductImage::query()
                ->where('product_id', $product->id)
                ->where('external_image_id', $defaultImageId)
                ->first();
        }

        if ($defaultImageRecord === null) {
            $defaultImageRecord = ProductImage::query()
                ->where('product_id', $product->id)
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->first();
        }

        if ($defaultImageRecord !== null) {
            ProductImage::query()
                ->where('product_id', $product->id)
                ->where('id', '!=', $defaultImageRecord->id)
                ->update(['is_primary' => false]);

            $defaultImageRecord->update(['is_primary' => true]);

            if ($product->default_image_id !== $defaultImageRecord->id) {
                $changed = true;
            }

            $product->update(['default_image_id' => $defaultImageRecord->id]);
        }

        if ($defaultImageUrl !== '' && $product->api_default_image_url !== $defaultImageUrl) {
            $product->update(['api_default_image_url' => $defaultImageUrl]);
            $changed = true;
        }

        $activeExternalIds = array_values(array_filter(
            $seenExternalIds,
            static fn (string $id): bool => $id !== '',
        ));

        if ($activeExternalIds === []) {
            return $changed;
        }

        $removedCount = ProductImage::query()
            ->where('product_id', $product->id)
            ->whereNotIn('external_image_id', $activeExternalIds)
            ->update(['status' => 'removed']);

        return $changed || $removedCount > 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $offers
     */
    private function syncSupplierOffers(Product $product, array $offers): bool
    {
        $changed = false;

        foreach ($offers as $offerPayload) {
            $supplierExternalId = (string) ($offerPayload['supplierId'] ?? '');
            $supplierName = (string) ($offerPayload['supplierName'] ?? 'Supplier');
            $normalized = $this->supplierNameNormalizer->normalize($supplierName);

            $supplier = Supplier::query()->firstOrNew(['external_supplier_id' => $supplierExternalId]);
            $supplier->name = $supplierName;

            if (! $supplier->exists || $supplier->display_name === null) {
                $supplier->display_name = $normalized['display_name'];
            }

            if (! $supplier->exists || $supplier->code === null) {
                $supplier->code = $normalized['code'];
            }

            $supplier->save();

            $existingOffer = ProductSupplierOffer::query()
                ->where('product_id', $product->id)
                ->where('supplier_id', $supplier->id)
                ->first();

            $offerAttributes = [
                'supplier_sku' => $offerPayload['supplierSku'] ?? null,
                'supplier_price' => $offerPayload['supplierPrice'] ?? null,
                'supplier_stock' => (int) ($offerPayload['supplierStock'] ?? 0),
                'is_selected_price_source' => (bool) ($offerPayload['isSelectedPriceSource'] ?? false),
            ];

            if (
                ! $existingOffer
                || $existingOffer->supplier_sku !== $offerAttributes['supplier_sku']
                || (float) $existingOffer->supplier_price !== (float) ($offerAttributes['supplier_price'] ?? 0)
                || $existingOffer->supplier_stock !== $offerAttributes['supplier_stock']
                || $existingOffer->is_selected_price_source !== $offerAttributes['is_selected_price_source']
            ) {
                $changed = true;
            }

            ProductSupplierOffer::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'supplier_id' => $supplier->id,
                ],
                $offerAttributes
            );
        }

        return $changed;
    }

    /**
     * @param  array<string, mixed>  $seoPayload
     */
    private function syncSeo(Product $product, array $seoPayload): void
    {
        if ($seoPayload === []) {
            return;
        }

        $override = $product->seoOverride;

        if ($override?->is_locked) {
            return;
        }

        SeoOverride::query()->updateOrCreate(
            [
                'seoable_type' => Product::class,
                'seoable_id' => $product->id,
            ],
            [
                'meta_title' => $seoPayload['metaTitle'] ?? null,
                'meta_description' => $seoPayload['metaDescription'] ?? null,
                'og_image_url' => $seoPayload['ogImageUrl'] ?? null,
                'canonical' => $seoPayload['canonical'] ?? null,
                'robots' => $seoPayload['robots'] ?? 'index,follow',
            ]
        );
    }

    private function recalculateStock(Product $product): void
    {
        if ($product->manual_stock_override !== null) {
            $product->available_stock = (int) $product->manual_stock_override - (int) $product->reserved_stock;
        } else {
            $product->available_stock = (int) $product->api_stock - (int) $product->reserved_stock;
        }

        $product->stock_status = $product->available_stock > 0 ? 'in_stock' : 'out_of_stock';
        $product->save();
    }

    private function sanitizeHtml(?string $html): ?string
    {
        return \App\Support\SafeHtml::clean($html);
    }
}
