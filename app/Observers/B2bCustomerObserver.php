<?php

namespace App\Observers;

use App\Models\B2bCustomer;
use App\Services\B2b\B2bReadCache;

class B2bCustomerObserver
{
    public function saved(B2bCustomer $customer): void
    {
        if ($customer->wasChanged('discount_percent') || $customer->wasChanged('is_active')) {
            app(B2bReadCache::class)->flushCategories();
        }
    }
}
