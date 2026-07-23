<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Services\Homepage\HomepageCategoryChips;
use App\Services\Homepage\HomepageFeaturedProducts;
use App\Services\Homepage\WeeklyOfferProducts;
use Illuminate\Http\JsonResponse;

class HomepageController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly WeeklyOfferProducts $weeklyOfferProducts,
        private readonly HomepageCategoryChips $homepageCategoryChips,
        private readonly HomepageFeaturedProducts $homepageFeaturedProducts,
    ) {}

    public function weeklyOffer(): JsonResponse
    {
        return $this->success($this->weeklyOfferProducts->payload());
    }

    public function categoryChips(): JsonResponse
    {
        return $this->success($this->homepageCategoryChips->payload());
    }

    public function featuredProducts(): JsonResponse
    {
        return $this->success($this->homepageFeaturedProducts->payload());
    }
}
