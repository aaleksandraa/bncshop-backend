<?php

namespace App\Services\B2b;

use App\Models\B2bCampaign;
use App\Models\B2bCustomer;
use App\Models\B2bProduct;

class B2bPricingService
{
    /**
     * @return array{
     *     regular_price: float,
     *     product_price: float,
     *     customer_discount_percent: float,
     *     final_price: float,
     *     has_sale: bool,
     *     badge_text: string|null,
     *     exclude_customer_discount: bool
     * }
     */
    public function calculate(B2bProduct $product, ?B2bCustomer $customer = null): array
    {
        $regularPrice = (float) $product->regular_price;
        $productPrice = $regularPrice;
        $badgeText = null;
        $hasSale = false;

        if ($product->sale_price !== null && (float) $product->sale_price < $productPrice) {
            $productPrice = (float) $product->sale_price;
            $hasSale = true;
        }

        $activeCampaign = $this->resolveActiveCampaign($product);

        if ($activeCampaign !== null) {
            $campaignPrice = $this->campaignPrice($regularPrice, $activeCampaign);

            if ($campaignPrice < $productPrice) {
                $productPrice = $campaignPrice;
                $hasSale = true;
                $badgeText = $activeCampaign->badge_text ?: $activeCampaign->name;
            }
        }

        $customerDiscountPercent = $customer?->effectiveDiscountPercent() ?? 0.0;
        $finalPrice = $productPrice;

        if (
            ! $product->exclude_customer_discount
            && $customerDiscountPercent > 0
        ) {
            $customerPrice = round($regularPrice * (1 - ($customerDiscountPercent / 100)), 2);
            $finalPrice = min($productPrice, $customerPrice);

            if ($finalPrice < $regularPrice) {
                $hasSale = true;
            }
        }

        return [
            'regular_price' => $regularPrice,
            'product_price' => $productPrice,
            'customer_discount_percent' => $customerDiscountPercent,
            'final_price' => $finalPrice,
            'has_sale' => $hasSale,
            'badge_text' => $badgeText,
            'exclude_customer_discount' => (bool) $product->exclude_customer_discount,
        ];
    }

    private function resolveActiveCampaign(B2bProduct $product): ?B2bCampaign
    {
        return $product->campaigns
            ->filter(fn (B2bCampaign $campaign): bool => $campaign->isCurrentlyActive())
            ->sortBy(fn (B2bCampaign $campaign): float => $this->campaignPrice((float) $product->regular_price, $campaign))
            ->first();
    }

    private function campaignPrice(float $regularPrice, B2bCampaign $campaign): float
    {
        if ($campaign->discount_type === 'fixed_price') {
            return (float) $campaign->value;
        }

        return round($regularPrice * (1 - ((float) $campaign->value / 100)), 2);
    }
}
