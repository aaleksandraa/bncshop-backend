<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Catalog\ProductReadCache;

class ProductImageObserver
{
    public function __construct(
        private readonly ProductReadCache $productReadCache,
    ) {}

    public function saved(ProductImage $image): void
    {
        $this->forgetProductCache($image->product_id);
    }

    public function deleted(ProductImage $image): void
    {
        $this->forgetProductCache($image->product_id);
    }

    private function forgetProductCache(?int $productId): void
    {
        if ($productId === null) {
            return;
        }

        $product = Product::query()->find($productId);

        if ($product !== null) {
            $this->productReadCache->forgetProduct($product);
        }
    }
}
