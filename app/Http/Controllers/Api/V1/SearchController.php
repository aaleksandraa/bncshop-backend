<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Models\Category;
use App\Services\Catalog\ProductListingService;
use App\Services\Catalog\ProductReadCache;
use App\Services\Search\FilterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly FilterService $filterService,
        private readonly ProductReadCache $productReadCache,
        private readonly ProductListingService $productListingService,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $query = trim($request->string('q')->toString());
        $perPage = min((int) $request->integer('per_page', 24), 100);

        if ($query === '') {
            return $this->success([]);
        }

        $cacheKey = 'search:'.md5(json_encode($request->query()));

        $payload = $this->productReadCache->rememberList($cacheKey, 45, function () use ($request, $perPage): array {
            return $this->productListingService->listWithFallback($request, $perPage);
        });

        return $this->success($payload['items'], [
            'pagination' => [
                'current_page' => $payload['current_page'],
                'per_page' => $payload['per_page'],
                'total' => $payload['total'],
                'last_page' => $payload['last_page'],
            ],
        ]);
    }

    public function filters(string $categorySlug): JsonResponse
    {
        $category = $this->productReadCache->rememberCategorySlug($categorySlug, 300, function () use ($categorySlug): ?Category {
            return Category::query()
                ->active()
                ->where('full_slug', $categorySlug)
                ->first();
        });

        if (! $category) {
            abort(404);
        }

        $category->loadMissing([
            'attributeMappings.attributeDefinition.canonicalDefinition',
        ]);

        $payload = $this->productReadCache->rememberFilters(
            $category->id,
            300,
            fn (): array => $this->filterService->getCategoryFilterPayload($category),
        );

        return $this->success($payload);
    }
}
