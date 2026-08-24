<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Services\Catalog\CampaignResolver;
use App\Services\Catalog\ProductReadCache;
use App\Support\PublicStorageUrl;
use App\Support\ResourceSlug;
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
        $slug = ResourceSlug::abortIfInvalid($slug);

        $payload = $this->productReadCache->rememberCampaign($slug, 60, function () use ($slug): ?array {
            $campaign = $this->campaignResolver->findActiveLandingBySlug($slug);

            if ($campaign === null) {
                return null;
            }

            return $this->campaignResolver->landingPayload($campaign);
        });

        if ($payload === null) {
            abort(404);
        }

        return $this->success(PublicStorageUrl::rewriteStorageUrlsInValue($payload));
    }
}
