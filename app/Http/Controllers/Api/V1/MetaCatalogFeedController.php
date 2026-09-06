<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Integrations\MetaCatalogFeedService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MetaCatalogFeedController extends Controller
{
    public function __invoke(Request $request, MetaCatalogFeedService $feedService): StreamedResponse
    {
        abort_unless(
            $feedService->isAuthorized($request->query('token')),
            403,
            'Invalid catalog feed token.',
        );

        return $feedService->toStreamedResponse();
    }
}
