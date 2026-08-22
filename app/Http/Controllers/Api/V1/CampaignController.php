<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Services\Catalog\CampaignResolver;
use App\Services\Catalog\ProductReadCache;
use App\Support\PublicStorageUrl;
use Illuminate\Http\JsonResponse;

class CampaignController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly CampaignResolver $campaignResolver,
        private readonly ProductReadCache $productReadCache,
    ) {}

    public function show(string $slug): JsonResponse
    {
        $payload = $this->productReadCache->rememberCampaign($slug, 60, function () use ($slug): array {
            $campaign = $this->campaignResolver->findActiveLandingBySlug($slug);

            if ($campaign === null) {
                abort(404);
            }

            return $this->campaignResolver->landingPayload($campaign);
        });

        return $this->success(PublicStorageUrl::rewriteStorageUrlsInValue($payload));
    }
}
