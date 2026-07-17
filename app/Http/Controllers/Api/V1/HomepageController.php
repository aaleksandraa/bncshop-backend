<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Services\Homepage\WeeklyOfferProducts;
use Illuminate\Http\JsonResponse;

class HomepageController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly WeeklyOfferProducts $weeklyOfferProducts,
    ) {}

    public function weeklyOffer(): JsonResponse
    {
        return $this->success($this->weeklyOfferProducts->payload());
    }
}
