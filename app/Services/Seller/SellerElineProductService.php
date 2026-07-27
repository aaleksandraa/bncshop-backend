<?php

namespace App\Services\Seller;

use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\Pricing\PriceCalculator;
use App\Services\Sync\FieldLockService;
use App\Services\Sync\ProductImageStorageService;
use App\Support\PublicStorageUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class SellerElineProductService
{
    public const SELLER_DISCOUNT_NAME = 'Prodavač akcija';

    /**
     * @var array<string, bool>
     */
    public const SELLER_DISCOUNT_MARKER = ['seller_managed' => true];

    public function __construct(
        private readonly FieldLockService $fieldLockService,
        private readonly PriceCalculator $priceCalculator,
        private readonly ProductImageStorageService $productImageStorage,
    ) {}

    public function findElineProduct(int $id): Product
    {
        return Product::query()
            ->fromEline()
            ->with(['images', 'defaultImage'])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data, User $user): Product
    {
        if (array_key_exists('description', $data)) {
            $product->description = (string) $data['description'];
            $this->fieldLockService->lockField($product, 'description', $user->id);
        }

        if (array_key_exists('short_description', $data)) {
            $product->short_description = (string) $data['short_description'];
            $this->fieldLockService->lockField($product, 'short_description', $user->id);
        }

        if (array_key_exists('sale_price', $data)) {
            $this->upsertSalePrice($product, $data['sale_price'] !== null ? (float) $data['sale_price'] : null);
        }

        if (array_key_exists('primary_image_id', $data) && $data['primary_image_id'] !== null) {
            $this->setPrimaryImage($product, (int) $data['primary_image_id']);
        }

        $product->save();

        if (array_key_exists('sale_price', $data)) {
            $this->priceCalculator->recalculateAndPersist($product->fresh());
        }

        return $product->fresh(['images', 'defaultImage']);
    }

    public function upsertSalePrice(Product $product, ?float $salePrice): void
    {
        $discount = $this->findSellerDiscount($product);
        $regularPrice = (float) $product->regular_price;

        if ($salePrice === null) {
            if ($discount !== null) {
                $discount->update(['is_active' => false]);
            }

            return;
        }

        $discountAmount = round($regularPrice - $salePrice, 2);

        if ($discountAmount <= 0) {
            if ($discount !== null) {
                $discount->update(['is_active' => false]);
            }

            return;
        }

        Discount::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'type' => 'product',
                'name' => self::SELLER_DISCOUNT_NAME,
            ],
            [
                'discount_type' => 'fixed',
                'value' => $discountAmount,
                'is_active' => true,
                'badge_text' => 'Akcija',
                'combines_with_coupons' => false,
                'conditions_json' => self::SELLER_DISCOUNT_MARKER,
            ],
        );
    }

    public function resolveSalePrice(Product $product): ?float
    {
        $discount = $this->findSellerDiscount($product);

        if ($discount === null || ! $discount->is_active) {
            return null;
        }

        $regularPrice = (float) $product->regular_price;

        return max(0, round($regularPrice - (float) $discount->value, 2));
    }

    public function storeImage(Product $product, UploadedFile $file, bool $isPrimary = false): ProductImage
    {
        $extension = Str::lower($file->getClientOriginalExtension() ?: 'jpg');
        $directory = 'products/'.Str::slug((string) $product->external_product_id, '_');
        $fileName = 'seller-'.Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs($directory, $fileName, 'public');

        $contents = (string) file_get_contents($file->getRealPath());
        [$width, $height] = $this->resolveDimensions($contents);

        $maxSort = (int) ProductImage::query()
            ->where('product_id', $product->id)
            ->max('sort_order');

        $image = ProductImage::query()->create([
            'product_id' => $product->id,
            'local_path' => $path,
            'image_url' => PublicStorageUrl::url($path),
            'public_url' => PublicStorageUrl::url($path),
            'original_file_name' => $file->getClientOriginalName(),
            'stored_file_name' => $fileName,
            'content_type' => $file->getMimeType(),
            'file_extension' => $extension,
            'file_size_bytes' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'is_primary' => $isPrimary,
            'sort_order' => $maxSort + 1,
            'status' => 'active',
            'is_public' => true,
        ]);

        if ($isPrimary || $product->default_image_id === null) {
            $this->setPrimaryImage($product, $image->id);
        }

        return $image->fresh();
    }

    public function deleteImage(Product $product, int $imageId): void
    {
        $image = ProductImage::query()
            ->where('product_id', $product->id)
            ->whereKey($imageId)
            ->firstOrFail();

        if ($image->local_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($image->local_path);
        }

        $wasPrimary = (int) $product->default_image_id === (int) $image->id;

        $image->delete();

        if ($wasPrimary) {
            $nextImage = ProductImage::query()
                ->where('product_id', $product->id)
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->first();

            $product->update([
                'default_image_id' => $nextImage?->id,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function formatSummary(Product $product): array
    {
        $primaryImage = $product->defaultImage ?? $product->images->first();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'eline_sifra' => $product->eline_sifra,
            'regular_price' => $product->regular_price,
            'display_price' => $product->display_price,
            'sale_price' => $this->resolveSalePrice($product),
            'on_sale' => (bool) $product->on_sale,
            'status' => $product->status,
            'is_public' => (bool) $product->is_public,
            'primary_image_url' => $primaryImage
                ? PublicStorageUrl::absoluteFromResolved($this->productImageStorage->resolvedUrl($primaryImage))
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatDetail(Product $product): array
    {
        return [
            ...$this->formatSummary($product),
            'description' => $product->description,
            'short_description' => $product->short_description,
            'available_stock' => $product->available_stock,
            'stock_status' => $product->stock_status,
            'import_source' => $product->import_source,
            'images' => $product->images->map(fn (ProductImage $image) => [
                'id' => $image->id,
                'url' => PublicStorageUrl::absoluteFromResolved($this->productImageStorage->resolvedUrl($image)),
                'is_primary' => (bool) $image->is_primary || (int) $product->default_image_id === (int) $image->id,
                'sort_order' => $image->sort_order,
            ])->values()->all(),
        ];
    }

    private function findSellerDiscount(Product $product): ?Discount
    {
        return Discount::query()
            ->where('product_id', $product->id)
            ->where('type', 'product')
            ->where('name', self::SELLER_DISCOUNT_NAME)
            ->first();
    }

    private function setPrimaryImage(Product $product, int $imageId): void
    {
        $image = ProductImage::query()
            ->where('product_id', $product->id)
            ->whereKey($imageId)
            ->firstOrFail();

        ProductImage::query()
            ->where('product_id', $product->id)
            ->update(['is_primary' => false]);

        $image->update(['is_primary' => true]);
        $product->default_image_id = $image->id;
        $product->save();
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function resolveDimensions(string $contents): array
    {
        $info = @getimagesizefromstring($contents);

        if (! is_array($info)) {
            return [null, null];
        }

        return [
            isset($info[0]) ? (int) $info[0] : null,
            isset($info[1]) ? (int) $info[1] : null,
        ];
    }
}
