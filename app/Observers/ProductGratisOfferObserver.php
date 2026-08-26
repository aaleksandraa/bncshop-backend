<?php

namespace App\Observers;

use App\Models\ProductGratisOffer;
use App\Services\Catalog\ProductReadCache;

class ProductGratisOfferObserver
{
    public function __construct(
        private readonly ProductReadCache $productReadCache,
    ) {}

    public function saved(ProductGratisOffer $offer): void
    {
        $this->forgetParentProduct($offer);
    }

    public function deleted(ProductGratisOffer $offer): void
    {
        $this->forgetParentProduct($offer);
    }

    private function forgetParentProduct(ProductGratisOffer $offer): void
    {
        $offer->loadMissing('product');

        if ($offer->product !== null) {
            $this->productReadCache->forgetProduct($offer->product);
        }
    }
}
