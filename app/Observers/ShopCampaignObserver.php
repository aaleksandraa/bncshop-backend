<?php

namespace App\Observers;

use App\Models\ShopCampaign;
use App\Services\Catalog\CampaignResolver;
use App\Services\Catalog\ProductReadCache;

class ShopCampaignObserver
{
    public function __construct(
        private readonly CampaignResolver $campaignResolver,
        private readonly ProductReadCache $productReadCache,
    ) {}

    public function saved(ShopCampaign $campaign): void
    {
        $this->invalidate($campaign);
    }

    public function deleted(ShopCampaign $campaign): void
    {
        $this->invalidate($campaign);
    }

    private function invalidate(ShopCampaign $campaign): void
    {
        $this->campaignResolver->invalidateCache();
        $this->productReadCache->forgetCampaign($campaign->slug);
        $this->productReadCache->flushProducts();
    }
}
