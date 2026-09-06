<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Jobs\SendMetaProductViewJob;
use App\Models\Product;
use App\Support\CrawlerDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MetaProductViewController extends Controller
{
    use RespondsWithJson;

    public function store(Request $request): JsonResponse
    {
        if (CrawlerDetector::isCrawler($request->userAgent())) {
            return $this->success(['queued' => false, 'ignored' => true], status: 202);
        }

        $validated = Validator::make($request->all(), [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'path' => ['nullable', 'string', 'max:500'],
        ])->validate();

        $product = Product::query()->findOrFail((int) $validated['product_id']);
        $path = trim((string) ($validated['path'] ?? ''));
        $eventSourceUrl = $this->resolveEventSourceUrl($request, $path);

        SendMetaProductViewJob::dispatch(
            $product->id,
            $eventSourceUrl,
            $request->ip(),
            $request->userAgent(),
        );

        return $this->success(['queued' => true], status: 202);
    }

    private function resolveEventSourceUrl(Request $request, string $path): string
    {
        $frontendUrl = rtrim((string) config('bnc.frontend_url', ''), '/');
        if ($frontendUrl !== '' && $path !== '') {
            return $frontendUrl.'/'.ltrim($path, '/');
        }

        if ($path !== '') {
            return $request->getSchemeAndHttpHost().'/'.ltrim($path, '/');
        }

        return $request->fullUrl();
    }
}
