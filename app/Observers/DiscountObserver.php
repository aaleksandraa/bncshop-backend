<?php

namespace App\Observers;

use App\Models\Discount;
use App\Services\Pricing\PricingCache;

class DiscountObserver
{
    public function __construct(
        private readonly PricingCache $pricingCache,
    ) {}

    public function saved(Discount $discount): void
    {
        $this->pricingCache->flush();
    }

    public function deleted(Discount $discount): void
    {
        $this->pricingCache->flush();
    }
}
