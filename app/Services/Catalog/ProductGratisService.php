<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductGratisOffer;
use App\Services\Media\MediaStorage;
use App\Support\PublicStorageUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class ProductGratisService
{
    public function __construct(
        private readonly ProductReadCache $productReadCache,
    ) {}

    public function tablesReady(): bool
    {
        try {
            return Schema::hasTable('product_gratis_offers');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    public function cardEagerLoads(): array
    {
        if (! $this->tablesReady()) {
            return [];
        }

        return ['gratisOffers.giftProduct.defaultImage'];
    }

    /**
     * @return Collection<int, ProductGratisOffer>
     */
    public function activeOffersFor(Product $product): Collection
    {
        if (! $this->tablesReady()) {
            return collect();
        }

        try {
            $product->loadMissing(['gratisOffers.giftProduct.defaultImage']);
        } catch (Throwable $exception) {
            report($exception);

            return collect();
        }

        return $product->gratisOffers
            ->filter(fn (ProductGratisOffer $offer): bool => $this->isOfferApplicable($offer, $product))
            ->values();
    }

    public function isOfferApplicable(ProductGratisOffer $offer, Product $parentProduct): bool
    {
        if (! $offer->is_active) {
            return false;
        }

        $now = now();

        if ($offer->starts_at !== null && $offer->starts_at->isAfter($now)) {
            return false;
        }

        if ($offer->ends_at !== null && $offer->ends_at->isBefore($now)) {
            return false;
        }

        if ($offer->isTextType()) {
            return filled($offer->title);
        }

        if (! $offer->isProductType() || $offer->gift_product_id === null) {
            return false;
        }

        $offer->loadMissing('giftProduct');

        $gift = $offer->giftProduct;

        if ($gift === null || $gift->isSet() || ! $gift->is_public || $gift->status !== 'active') {
            return false;
        }

        if ($offer->until_stock && (int) $gift->available_stock <= 0) {
            return false;
        }

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function displayPayloadsFor(Product $product): array
    {
        return $this->activeOffersFor($product)
            ->map(fn (ProductGratisOffer $offer): array => $this->displayPayload($offer))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function displayPayload(ProductGratisOffer $offer): array
    {
        $offer->loadMissing(['giftProduct.defaultImage', 'giftProduct.manufacturer']);

        $gift = $offer->giftProduct;
        $title = $this->resolveTitle($offer);
        $imageUrl = $this->resolveImageUrl($offer);

        return [
            'id' => $offer->id,
            'type' => $offer->type,
            'title' => $title,
            'description' => $offer->description,
            'image_url' => $imageUrl,
            'gift_quantity_per_parent' => (int) $offer->gift_quantity_per_parent,
            'gift_product' => $gift !== null ? [
                'id' => $gift->id,
                'name' => $gift->name,
                'slug' => $gift->slug,
                'display_price' => $gift->display_price,
                'default_image' => $gift->defaultImage ? [
                    'url' => PublicStorageUrl::absoluteFromResolved($gift->defaultImage->resolvedUrl()),
                ] : null,
            ] : null,
        ];
    }

    public function resolveTitle(ProductGratisOffer $offer): string
    {
        if (filled($offer->title)) {
            return (string) $offer->title;
        }

        if ($offer->isProductType() && $offer->giftProduct !== null) {
            return $offer->giftProduct->name;
        }

        return '';
    }

    public function resolveImageUrl(ProductGratisOffer $offer): ?string
    {
        if (filled($offer->image_path)) {
            return PublicStorageUrl::absoluteFromResolved(PublicStorageUrl::url($offer->image_path));
        }

        if ($offer->isProductType() && $offer->giftProduct?->defaultImage !== null) {
            return PublicStorageUrl::absoluteFromResolved($offer->giftProduct->defaultImage->resolvedUrl());
        }

        return null;
    }

    public function requiredGiftQuantity(ProductGratisOffer $offer, int $parentQuantity): int
    {
        return max(0, $parentQuantity * max(1, (int) $offer->gift_quantity_per_parent));
    }

    /**
     * @return Collection<int, ProductGratisOffer>
     */
    public function applicableProductOffersFor(Product $parentProduct): Collection
    {
        return $this->activeOffersFor($parentProduct)
            ->filter(fn (ProductGratisOffer $offer): bool => $offer->isProductType())
            ->values();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function syncOffers(Product $product, array $rows): void
    {
        if ($product->isSet()) {
            return;
        }

        $this->validateOffers($product, $rows);

        $existingIds = $product->gratisOffers()->pluck('id')->all();
        $keptIds = [];

        foreach ($rows as $index => $row) {
            $type = (string) ($row['type'] ?? ProductGratisOffer::TYPE_TEXT);

            $payload = [
                'sort_order' => $index,
                'type' => $type,
                'gift_product_id' => $type === ProductGratisOffer::TYPE_PRODUCT
                    ? (int) ($row['gift_product_id'] ?? 0) ?: null
                    : null,
                'gift_quantity_per_parent' => $type === ProductGratisOffer::TYPE_PRODUCT
                    ? max(1, (int) ($row['gift_quantity_per_parent'] ?? 1))
                    : 1,
                'title' => filled($row['title'] ?? null) ? (string) $row['title'] : null,
                'description' => filled($row['description'] ?? null) ? (string) $row['description'] : null,
                'image_path' => $this->normalizeImagePath($row['image_path'] ?? null),
                'starts_at' => $row['starts_at'] ?? null,
                'ends_at' => $row['ends_at'] ?? null,
                'until_stock' => $type === ProductGratisOffer::TYPE_PRODUCT
                    && (bool) ($row['until_stock'] ?? false),
                'is_active' => (bool) ($row['is_active'] ?? true),
            ];

            if (! empty($row['id'])) {
                $offer = ProductGratisOffer::query()
                    ->where('product_id', $product->id)
                    ->whereKey((int) $row['id'])
                    ->first();

                if ($offer !== null) {
                    $offer->update($payload);
                    $keptIds[] = $offer->id;

                    continue;
                }
            }

            $created = ProductGratisOffer::query()->create(array_merge($payload, [
                'product_id' => $product->id,
            ]));
            $keptIds[] = $created->id;
        }

        $deleteIds = array_diff($existingIds, $keptIds);

        if ($deleteIds !== []) {
            ProductGratisOffer::query()->whereIn('id', $deleteIds)->delete();
        }

        $this->productReadCache->forgetProduct($product);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toFormRows(Product $product): array
    {
        return $product->gratisOffers()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ProductGratisOffer $offer): array => [
                'id' => $offer->id,
                'type' => $offer->type,
                'gift_product_id' => $offer->gift_product_id,
                'gift_quantity_per_parent' => $offer->gift_quantity_per_parent,
                'title' => $offer->title,
                'description' => $offer->description,
                'image_path' => $offer->image_path,
                'starts_at' => $offer->starts_at?->toDateTimeString(),
                'ends_at' => $offer->ends_at?->toDateTimeString(),
                'until_stock' => $offer->until_stock,
                'is_active' => $offer->is_active,
            ])
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function validateOffers(Product $product, array $rows): void
    {
        foreach ($rows as $index => $row) {
            $type = (string) ($row['type'] ?? ProductGratisOffer::TYPE_TEXT);
            $label = 'gratis_offers.'.$index;

            if ($type === ProductGratisOffer::TYPE_TEXT) {
                if (! filled($row['title'] ?? null)) {
                    throw ValidationException::withMessages([
                        $label.'.title' => 'Naslov je obavezan za tekstualnu gratis ponudu.',
                    ]);
                }

                continue;
            }

            if ($type !== ProductGratisOffer::TYPE_PRODUCT) {
                throw ValidationException::withMessages([
                    $label.'.type' => 'Nepoznat tip gratis ponude.',
                ]);
            }

            $giftProductId = (int) ($row['gift_product_id'] ?? 0);

            if ($giftProductId <= 0) {
                throw ValidationException::withMessages([
                    $label.'.gift_product_id' => 'Odaberite proizvod koji ide gratis.',
                ]);
            }

            if ($product->exists && $giftProductId === (int) $product->id) {
                throw ValidationException::withMessages([
                    $label.'.gift_product_id' => 'Proizvod ne može imati sam sebe kao gratis.',
                ]);
            }

            $gift = Product::query()->find($giftProductId);

            if ($gift === null) {
                throw ValidationException::withMessages([
                    $label.'.gift_product_id' => 'Odabrani gratis proizvod ne postoji.',
                ]);
            }

            if ($gift->isSet()) {
                throw ValidationException::withMessages([
                    $label.'.gift_product_id' => 'Set proizvod se ne može dodati kao gratis.',
                ]);
            }
        }
    }

    /**
     * Filament FileUpload (especially inside a repeater) stores state as an array
     * of temp files or stored keys. Casting that array to string 500s on Laravel 11.
     */
    public function normalizeImagePath(mixed $value): ?string
    {
        if ($value instanceof TemporaryUploadedFile) {
            return $this->storeGratisUpload($value);
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $normalized = $this->normalizeImagePath($item);
                if ($normalized !== null) {
                    return $normalized;
                }
            }

            return null;
        }

        if (! is_string($value)) {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', trim($value)), '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        return $path;
    }

    private function storeGratisUpload(TemporaryUploadedFile $file): ?string
    {
        try {
            $realPath = $file->getRealPath();
            if (! is_string($realPath) || $realPath === '' || ! is_readable($realPath)) {
                return null;
            }

            $contents = (string) file_get_contents($realPath);
            if ($contents === '') {
                return null;
            }

            $original = $file->getClientOriginalName();
            $baseName = pathinfo($original, PATHINFO_FILENAME);
            $baseName = Str::slug($baseName) ?: (string) Str::uuid();
            $mediaStorage = app(MediaStorage::class);

            if (str_ends_with(strtolower($original), '.svg')) {
                return $mediaStorage->storeOptimized(
                    'products/gratis/'.$baseName.'.svg',
                    $contents,
                )->key;
            }

            return $mediaStorage->storeFromBinary($contents, 'products/gratis', $baseName)->key;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    public function assertGiftStockAvailable(ProductGratisOffer $offer, int $parentQuantity): void
    {
        if (! $offer->isProductType()) {
            return;
        }

        $offer->loadMissing('giftProduct');
        $gift = $offer->giftProduct;

        if ($gift === null) {
            throw ValidationException::withMessages([
                'product_id' => 'Gratis proizvod nije dostupan.',
            ]);
        }

        $required = $this->requiredGiftQuantity($offer, $parentQuantity);

        if ($required <= 0) {
            return;
        }

        if ((int) $gift->available_stock < $required) {
            throw ValidationException::withMessages([
                'product_id' => "Nedovoljna zaliha gratis proizvoda ({$gift->name}).",
            ]);
        }
    }
}
