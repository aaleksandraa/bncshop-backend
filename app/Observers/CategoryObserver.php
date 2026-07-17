<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\Catalog\ProductReadCache;
use App\Services\Pricing\PricingCache;

class CategoryObserver
{
    public function __construct(
        private readonly ProductReadCache $productReadCache,
        private readonly PricingCache $pricingCache,
    ) {}

    public function saved(Category $category): void
    {
        $this->productReadCache->flushCategories();
        $this->pricingCache->flush();
    }

    public function saving(Category $category): void
    {
        if ($category->description !== null) {
            $category->description = \App\Support\SafeHtml::clean($category->description);
        }
    }

    public function deleted(Category $category): void
    {
        $this->productReadCache->flushCategories();
        $this->pricingCache->flush();
    }
}
