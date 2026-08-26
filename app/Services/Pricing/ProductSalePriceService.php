<?php

namespace App\Services\Pricing;

use App\Models\Discount;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class ProductSalePriceService
{
    public const DISCOUNT_NAME = 'Prodavač akcija';

    public const VALIDITY_NO_END = 'no_end';

    public const VALIDITY_UNTIL_DATE = 'until_date';

    public const VALIDITY_UNTIL_STOCK = 'until_stock';

    public function findProductSaleDiscount(Product $product): ?Discount
    {
        return Discount::query()
            ->where('product_id', $product->id)
            ->where('type', 'product')
            ->where('name', self::DISCOUNT_NAME)
            ->first();
    }

    /**
     * @return array{sale_price: ?float, sale_validity: string, sale_ends_at: ?string}
     */
    public function toFormData(Product $product): array
    {
        $discount = $this->findProductSaleDiscount($product);

        if ($discount === null || ! $discount->is_active) {
            return [
                'sale_price' => null,
                'sale_validity' => self::VALIDITY_NO_END,
                'sale_ends_at' => null,
            ];
        }

        $validity = $this->resolveValidity($discount);

        return [
            'sale_price' => $this->resolveTargetSalePrice($product, $discount),
            'sale_validity' => $validity['validity'],
            'sale_ends_at' => $validity['ends_at']?->toDateTimeString(),
        ];
    }

    /**
     * @return array{validity: string, ends_at: ?CarbonInterface}
     */
    public function resolveValidity(Discount $discount): array
    {
        $conditions = $discount->conditions_json ?? [];

        if (($conditions['until_stock'] ?? false) === true) {
            return [
                'validity' => self::VALIDITY_UNTIL_STOCK,
                'ends_at' => null,
            ];
        }

        if ($discount->ends_at !== null) {
            return [
                'validity' => self::VALIDITY_UNTIL_DATE,
                'ends_at' => $discount->ends_at,
            ];
        }

        return [
            'validity' => self::VALIDITY_NO_END,
            'ends_at' => null,
        ];
    }

    public function resolveTargetSalePrice(Product $product, ?Discount $discount = null): ?float
    {
        $discount ??= $this->findProductSaleDiscount($product);

        if ($discount === null || ! $discount->is_active) {
            return null;
        }

        $conditions = $discount->conditions_json ?? [];

        if (isset($conditions['sale_price']) && is_numeric($conditions['sale_price'])) {
            return round((float) $conditions['sale_price'], 2);
        }

        $regularPrice = $this->resolveEffectiveRegularPrice($product);

        return max(0, round($regularPrice - (float) $discount->value, 2));
    }

    public function resolveSalePrice(Product $product): ?float
    {
        return $this->resolveTargetSalePrice($product);
    }

    public function resolveEffectiveRegularPrice(Product $product): float
    {
        if ($product->price_locked && $product->manual_price !== null) {
            return (float) $product->manual_price;
        }

        $apiPrice = (float) ($product->api_price ?? 0);
        $regularPrice = (float) ($product->regular_price ?? 0);

        if ($product->isFromEline()) {
            return $apiPrice > 0 ? $apiPrice : $regularPrice;
        }

        return $regularPrice > 0 ? $regularPrice : $apiPrice;
    }

    public function isSaleDiscountApplicable(Product $product, ?Discount $discount = null): bool
    {
        $discount ??= $this->findProductSaleDiscount($product);

        if ($discount === null || ! $discount->is_active) {
            return false;
        }

        if ($discount->starts_at !== null && $discount->starts_at->isFuture()) {
            return false;
        }

        if ($discount->ends_at !== null && $discount->ends_at->isPast()) {
            return false;
        }

        if ($this->isUntilStockDiscount($discount) && (int) $product->available_stock <= 0) {
            return false;
        }

        $targetSalePrice = $this->resolveTargetSalePrice($product, $discount);

        return $targetSalePrice !== null
            && $targetSalePrice < $this->resolveEffectiveRegularPrice($product);
    }

    public function upsert(
        Product $product,
        ?float $salePrice,
        ?string $validity = null,
        CarbonInterface|string|null $endsAt = null,
    ): void {
        $discount = $this->findProductSaleDiscount($product);

        if ($salePrice === null) {
            if ($discount !== null) {
                $discount->update(['is_active' => false]);
            }

            return;
        }

        $regularPrice = $this->resolveEffectiveRegularPrice($product);
        $discountAmount = round($regularPrice - $salePrice, 2);

        if ($discountAmount <= 0) {
            if ($discount !== null) {
                $discount->update(['is_active' => false]);
            }

            return;
        }

        if ($validity === null && $discount !== null) {
            $resolved = $this->resolveValidity($discount);
            $validity = $resolved['validity'];
            $endsAt ??= $resolved['ends_at'];
        }

        $validity ??= self::VALIDITY_NO_END;

        [$storedEndsAt, $untilStock] = $this->mapValidityToStorage($validity, $endsAt);

        $conditions = [
            'product_sale' => true,
            'seller_managed' => true,
            'sale_price' => round($salePrice, 2),
            'until_stock' => $untilStock,
        ];

        Discount::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'type' => 'product',
                'name' => self::DISCOUNT_NAME,
            ],
            [
                'discount_type' => 'fixed',
                'value' => $discountAmount,
                'starts_at' => null,
                'ends_at' => $storedEndsAt,
                'is_active' => true,
                'badge_text' => 'Akcija',
                'combines_with_coupons' => false,
                'conditions_json' => $conditions,
            ],
        );
    }

    public function syncDiscountValue(Product $product): bool
    {
        $discount = $this->findProductSaleDiscount($product);

        if ($discount === null || ! $discount->is_active) {
            return false;
        }

        $targetSalePrice = $this->resolveTargetSalePrice($product, $discount);

        if ($targetSalePrice === null) {
            $discount->update(['is_active' => false]);

            return true;
        }

        $regularPrice = $this->resolveEffectiveRegularPrice($product);
        $discountAmount = round($regularPrice - $targetSalePrice, 2);

        if ($discountAmount <= 0) {
            $discount->update(['is_active' => false]);

            return true;
        }

        if (round((float) $discount->value, 2) === $discountAmount) {
            return false;
        }

        $discount->update(['value' => $discountAmount]);

        return true;
    }

    public function deactivateUntilStockSalesIfOutOfStock(Product $product): bool
    {
        if ((int) $product->available_stock > 0) {
            return false;
        }

        $discount = $this->findProductSaleDiscount($product);

        if ($discount === null || ! $discount->is_active) {
            return false;
        }

        $conditions = $discount->conditions_json ?? [];

        if (($conditions['until_stock'] ?? false) !== true) {
            return false;
        }

        $discount->update(['is_active' => false]);

        return true;
    }

    public function isUntilStockDiscount(Discount $discount): bool
    {
        $conditions = $discount->conditions_json ?? [];

        return ($conditions['until_stock'] ?? false) === true;
    }

    /**
     * @return array{0: ?CarbonInterface, 1: bool}
     */
    private function mapValidityToStorage(string $validity, CarbonInterface|string|null $endsAt): array
    {
        return match ($validity) {
            self::VALIDITY_UNTIL_DATE => [
                $endsAt instanceof CarbonInterface
                    ? $endsAt
                    : ($endsAt !== null ? Carbon::parse($endsAt) : null),
                false,
            ],
            self::VALIDITY_UNTIL_STOCK => [null, true],
            default => [null, false],
        };
    }
}
