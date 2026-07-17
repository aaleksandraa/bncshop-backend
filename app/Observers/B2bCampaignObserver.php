<?php

namespace App\Observers;

use App\Models\B2bCampaign;
use App\Services\B2b\B2bReadCache;

class B2bCampaignObserver
{
    public function saved(B2bCampaign $campaign): void
    {
        app(B2bReadCache::class)->flushCategories();
    }

    public function deleted(B2bCampaign $campaign): void
    {
        app(B2bReadCache::class)->flushCategories();
    }
}
