<?php

namespace App\Services\Pricing;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductPriceHistory;

class PriceCalculator
{
    public function __construct(
        private readonly DiscountEngine $discountEngine,
        private readonly CouponEngine $couponEngine,
        private readonly SupplierOfferSelector $supplierOfferSelector,
        private readonly MarginRuleResolver $marginRuleResolver,
    ) {}

    public function calculate(Product $product, ?Coupon $coupon = null): PriceResult
    {
        $priceLocked = (bool) $product->price_locked;
        $wholesalePrice = null;
        $appliedMargin = null;
        $marginSource = null;
        $supplierName = null;
        $appliedPriceAdjustment = null;

        if ($priceLocked && $product->manual_price !== null) {
            $regularPrice = (float) $product->manual_price;
        } else {
            $pricing = $this->resolveRegularPrice($product);
            $regularPrice = $pricing['regular_price'];
            $wholesalePrice = $pricing['wholesale_price'];
            $appliedMargin = $pricing['applied_margin'];
            $marginSource = $pricing['margin_source'];
            $supplierName = $pricing['supplier_name'];
            $appliedPriceAdjustment = $pricing['applied_price_adjustment'];
        }

        $discount = null;
        $discountSource = 'none';
        $discountAmount = 0.0;
        $badgeText = null;

        if ($priceLocked && $product->manual_price !== null) {
            $base = (float) $product->manual_price;
            $discountSource = 'manual';
        } else {
            $discount = $this->discountEngine->bestForProduct($product);

            if ($discount) {
                $base = $this->discountEngine->applyDiscount($discount, $regularPrice);
                $discountAmount = $this->discountEngine->discountAmount($discount, $regularPrice);
                $discountSource = 'local';
                $badgeText = $discount->badge_text;
            } elseif ($this->hasActiveApiRebate($product)) {
                $base = (float) ($product->api_final_price ?? $this->applyApiRebate($product));
                $discountAmount = round($regularPrice - $base, 2);
                $discountSource = 'api';
            } else {
                $base = (float) ($product->api_final_price ?? $product->api_price ?? $regularPrice);
            }
        }

        $originalPrice = $regularPrice;
        $displayPrice = $base;

        if ($coupon) {
            $beforeCoupon = $displayPrice;
            $displayPrice = $this->couponEngine->apply($displayPrice, $coupon, $product);
            if ($displayPrice < $beforeCoupon) {
                $discountAmount += round($beforeCoupon - $displayPrice, 2);
                $discountSource = $discountSource === 'none' ? 'coupon' : $discountSource.'+coupon';
            }
        }

        $onSale = $displayPrice < $regularPrice;

        return new PriceResult(
            displayPrice: $displayPrice,
            regularPrice: $regularPrice,
            originalPrice: $onSale ? $originalPrice : null,
            discountSource: $discountSource,
            discountAmount: $discountAmount,
            discount: $discount,
            coupon: $coupon,
            badgeText: $badgeText,
            onSale: $onSale,
            priceLocked: $priceLocked,
            wholesalePrice: $wholesalePrice,
            appliedMargin: $appliedMargin,
            marginSource: $marginSource,
            supplierName: $supplierName,
            appliedPriceAdjustment: $appliedPriceAdjustment,
        );
    }

    public function recalculateAndPersist(Product $product): PriceResult
    {
        $oldRegularPrice = (float) ($product->regular_price ?? 0);
        $oldDisplayPrice = (float) ($product->display_price ?? 0);

        $result = $this->calculate($product);

        $updates = [
            'display_price' => $result->displayPrice,
            'on_sale' => $result->onSale,
        ];

        if (! $product->price_locked) {
            $updates['regular_price'] = $result->regularPrice;
        }

        $product->update($updates);

        app(\App\Services\Catalog\ProductReadCache::class)->forgetProduct($product);

        if (
            ! $product->price_locked
            && (
                round($oldRegularPrice, 2) !== round($result->regularPrice, 2)
                || round($oldDisplayPrice, 2) !== round($result->displayPrice, 2)
            )
        ) {
            ProductPriceHistory::query()->create([
                'product_id' => $product->id,
                'old_price' => $oldRegularPrice > 0 ? $oldRegularPrice : null,
                'new_price' => $result->regularPrice,
                'source' => $result->marginSource ?? 'recalculate',
                'changed_by' => auth()->id(),
                'created_at' => now(),
            ]);
        }

        return $result;
    }

    /**
     * @return array{
     *     regular_price: float,
     *     wholesale_price: ?float,
     *     applied_margin: ?float,
     *     margin_source: ?string,
     *     supplier_name: ?string,
     *     applied_price_adjustment: ?float
     * }
     */
    private function resolveRegularPrice(Product $product): array
    {
        $fallback = (float) ($product->regular_price ?? $product->api_price ?? 0);

        $offer = $this->supplierOfferSelector->select($product);

        if (! $offer || $offer->supplier_price === null || (float) $offer->supplier_price <= 0) {
            return [
                'regular_price' => $fallback,
                'wholesale_price' => null,
                'applied_margin' => null,
                'margin_source' => null,
                'supplier_name' => null,
                'applied_price_adjustment' => null,
            ];
        }

        $wholesalePrice = (float) $offer->supplier_price;
        $offer->loadMissing('supplier');
        $margin = $this->marginRuleResolver->resolve($product, $offer->supplier);
        $marginPercentage = $margin['margin_percentage'];
        $supplierName = $offer->supplier?->display_name ?? $offer->supplier?->name;

        if ($marginPercentage === null) {
            $basePrice = $fallback > 0 ? $fallback : $wholesalePrice;
            [$regularPrice, $appliedPriceAdjustment] = $this->applySupplierPriceAdjustment(
                $basePrice,
                $offer->supplier,
            );

            return [
                'regular_price' => $regularPrice,
                'wholesale_price' => $wholesalePrice,
                'applied_margin' => null,
                'margin_source' => $margin['source'],
                'supplier_name' => $supplierName,
                'applied_price_adjustment' => $appliedPriceAdjustment,
            ];
        }

        $regularPrice = round($wholesalePrice * (1 + ($marginPercentage / 100)), 2);
        [$regularPrice, $appliedPriceAdjustment] = $this->applySupplierPriceAdjustment(
            $regularPrice,
            $offer->supplier,
        );

        return [
            'regular_price' => $regularPrice,
            'wholesale_price' => $wholesalePrice,
            'applied_margin' => $marginPercentage,
            'margin_source' => $margin['source'],
            'supplier_name' => $supplierName,
            'applied_price_adjustment' => $appliedPriceAdjustment,
        ];
    }

    /**
     * @return array{0: float, 1: ?float}
     */
    private function applySupplierPriceAdjustment(float $regularPrice, ?\App\Models\Supplier $supplier): array
    {
        $adjustment = (float) ($supplier?->price_adjustment_amount ?? 0);

        if ($adjustment <= 0) {
            return [$regularPrice, null];
        }

        return [round($regularPrice + $adjustment, 2), $adjustment];
    }

    private function hasActiveApiRebate(Product $product): bool
    {
        if ($product->api_rebate === null || (float) $product->api_rebate <= 0) {
            return false;
        }

        if ($product->api_rebate_valid_until && $product->api_rebate_valid_until->isPast()) {
            return false;
        }

        return true;
    }

    private function applyApiRebate(Product $product): float
    {
        $base = (float) ($product->api_price ?? $product->regular_price ?? 0);
        $rebate = (float) $product->api_rebate;

        return max(0, round($base - $rebate, 2));
    }
}
