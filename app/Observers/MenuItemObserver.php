<?php

namespace App\Observers;

use App\Models\MenuItem;
use App\Services\Catalog\ProductReadCache;

class MenuItemObserver
{
    public function __construct(
        private readonly ProductReadCache $productReadCache,
    ) {}

    public function saved(MenuItem $menuItem): void
    {
        $this->productReadCache->flushMenus();
    }

    public function deleted(MenuItem $menuItem): void
    {
        $this->productReadCache->flushMenus();
    }
}
