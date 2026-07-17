<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\Catalog\ProductReadCache;

class ProductObserver
{
    public function __construct(
        private readonly ProductReadCache $productReadCache,
    ) {}

    public function saved(Product $product): void
    {
        $this->productReadCache->forgetProduct($product);
    }

    public function deleted(Product $product): void
    {
        $this->productReadCache->forgetProduct($product);
    }
}
