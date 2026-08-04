<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Catalog\ProductReadCache;
use App\Services\Media\MediaStorage;
use App\Services\Sync\ProductImageStorageService;

class ProductImageObserver
{
    public function __construct(
        private readonly ProductReadCache $productReadCache,
        private readonly MediaStorage $mediaStorage,
        private readonly ProductImageStorageService $productImageStorage,
    ) {}

    public function saved(ProductImage $image): void
    {
        $this->forgetProductCache($image->product_id);
    }

    public function deleted(ProductImage $image): void
    {
        if ($image->local_path) {
            $this->mediaStorage->deleteFromAnyDisk($image->local_path, $image->storage_disk);
        }

        $this->productImageStorage->forgetResolvedUrlCache($image);
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
