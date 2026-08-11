<?php

namespace App\Services\Pricing;

use App\Models\Coupon;
use App\Models\Discount;

readonly class PriceResult
{
    public function __construct(
        public float $displayPrice,
        public float $regularPrice,
        public ?float $originalPrice = null,
        public string $discountSource = 'none',
        public float $discountAmount = 0.0,
        public ?Discount $discount = null,
        public ?Coupon $coupon = null,
        public ?string $badgeText = null,
        public bool $onSale = false,
        public bool $priceLocked = false,
        public ?float $wholesalePrice = null,
        public ?float $appliedMargin = null,
        public ?string $marginSource = null,
        public ?string $supplierName = null,
        public ?float $appliedPriceAdjustment = null,
    ) {}

    public function toArray(): array
    {
        return [
            'display_price' => $this->displayPrice,
            'regular_price' => $this->regularPrice,
            'original_price' => $this->originalPrice,
            'discount_source' => $this->discountSource,
            'discount_amount' => $this->discountAmount,
            'discount_id' => $this->discount?->id,
            'coupon_code' => $this->coupon?->code,
            'badge_text' => $this->badgeText,
            'on_sale' => $this->onSale,
            'price_locked' => $this->priceLocked,
            'wholesale_price' => $this->wholesalePrice,
            'applied_margin' => $this->appliedMargin,
            'margin_source' => $this->marginSource,
            'supplier_name' => $this->supplierName,
            'applied_price_adjustment' => $this->appliedPriceAdjustment,
        ];
    }
}
