<?php

namespace App\Observers;

use App\Models\B2bProduct;
use App\Services\B2b\B2bNewProductNotificationService;
use App\Services\B2b\B2bReadCache;
use App\Support\SafeHtml;

class B2bProductObserver
{
    public function saving(B2bProduct $product): void
    {
        if ($product->isDirty('description') && filled($product->description)) {
            $product->description = SafeHtml::clean($product->description);
        }
    }

    public function saved(B2bProduct $product): void
    {
        app(B2bReadCache::class)->flushCategories();
        app(B2bNewProductNotificationService::class)->handleProductSaved($product);
    }

    public function deleted(B2bProduct $product): void
    {
        app(B2bReadCache::class)->flushCategories();
    }
}
