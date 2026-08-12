<?php

namespace App\Services\Pricing;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\Supplier;

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
        } elseif ($apiPricing = $this->resolveApiAdjustedPricing($product)) {
            $regularPrice = $apiPricing['regular_price'];
            $wholesalePrice = $apiPricing['wholesale_price'];
            $supplierName = $apiPricing['supplier_name'];
            $appliedPriceAdjustment = $apiPricing['applied_price_adjustment'];
        } else {
            $pricing = $this->resolveRegularPrice($product);
            $regularPrice = $pricing['regular_price'];
            $wholesalePrice = $pricing['wholesale_price'];
            $appliedMargin = $pricing['applied_margin'];
            $marginSource = $pricing['margin_source'];
            $supplierName = $pricing['supplier_name'];
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
            } elseif ($appliedPriceAdjustment !== null && $this->hasActiveApiRebate($product)) {
                $base = round((float) ($product->api_final_price ?? $this->applyApiRebate($product)) + $appliedPriceAdjustment, 2);
                $discountAmount = round($regularPrice - $base, 2);
                $discountSource = 'api';
            } elseif ($this->hasActiveApiRebate($product)) {
                $base = (float) ($product->api_final_price ?? $this->applyApiRebate($product));
                $discountAmount = round($regularPrice - $base, 2);
                $discountSource = 'api';
            } else {
                $base = (float) $regularPrice;
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
     * When the selected supplier has a fixed price adjustment, derive regular/display
     * from API prices and add the adjustment on top (not on wholesale margin).
     *
     * @return array{
     *     regular_price: float,
     *     wholesale_price: ?float,
     *     supplier_name: ?string,
     *     applied_price_adjustment: float
     * }|null
     */
    private function resolveApiAdjustedPricing(Product $product): ?array
    {
        $offer = $this->supplierOfferSelector->select($product);

        if (! $offer) {
            return null;
        }

        $offer->loadMissing('supplier');
        $supplier = $offer->supplier;
        $adjustment = (float) ($supplier?->price_adjustment_amount ?? 0);

        if ($adjustment <= 0) {
            return null;
        }

        $apiPrice = (float) ($product->api_price ?? 0);

        if ($apiPrice <= 0) {
            return null;
        }

        $supplierName = $supplier?->display_name ?? $supplier?->name;

        return [
            'regular_price' => round($apiPrice + $adjustment, 2),
            'wholesale_price' => $offer->supplier_price !== null ? (float) $offer->supplier_price : null,
            'supplier_name' => $supplierName,
            'applied_price_adjustment' => $adjustment,
        ];
    }

    /**
     * @return array{
     *     regular_price: float,
     *     wholesale_price: ?float,
     *     applied_margin: ?float,
     *     margin_source: ?string,
     *     supplier_name: ?string
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
            ];
        }

        $wholesalePrice = (float) $offer->supplier_price;
        $offer->loadMissing('supplier');
        $margin = $this->marginRuleResolver->resolve($product, $offer->supplier);
        $marginPercentage = $margin['margin_percentage'];
        $supplierName = $offer->supplier?->display_name ?? $offer->supplier?->name;

        if ($marginPercentage === null) {
            $regularPrice = $fallback > 0 ? $fallback : $wholesalePrice;

            return [
                'regular_price' => $regularPrice,
                'wholesale_price' => $wholesalePrice,
                'applied_margin' => null,
                'margin_source' => $margin['source'],
                'supplier_name' => $supplierName,
            ];
        }

        $regularPrice = round($wholesalePrice * (1 + ($marginPercentage / 100)), 2);

        return [
            'regular_price' => $regularPrice,
            'wholesale_price' => $wholesalePrice,
            'applied_margin' => $marginPercentage,
            'margin_source' => $margin['source'],
            'supplier_name' => $supplierName,
        ];
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
