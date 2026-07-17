<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Requests\Api\V1\StoreAnalyticsEventRequest;
use App\Jobs\TrackAnalyticsEventJob;
use Illuminate\Http\JsonResponse;

class AnalyticsEventController extends Controller
{
    use RespondsWithJson;

    public function store(StoreAnalyticsEventRequest $request): JsonResponse
    {
        $validated = $request->validated();

        TrackAnalyticsEventJob::dispatch(
            $validated['event_type'],
            array_merge($validated['metadata'] ?? [], array_filter([
                'product_id' => $validated['product_id'] ?? null,
                'category_id' => $validated['category_id'] ?? null,
            ])),
            $request->user()?->id,
            $validated['session_id'] ?? $request->header('X-Session-Id'),
        );

        return $this->success(['queued' => true], status: 202);
    }
}
