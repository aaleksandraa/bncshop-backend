<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Resources\CategoryTreeResource;
use App\Models\Category;
use App\Services\Catalog\CategoryNavBuilder;
use App\Services\Catalog\ProductReadCache;
use App\Services\Catalog\CategoryListingOrder;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly ProductReadCache $productReadCache,
        private readonly CategoryListingOrder $categoryListingOrder,
        private readonly CategoryNavBuilder $categoryNavBuilder,
    ) {}

    public function index(): JsonResponse
    {
        $categories = $this->productReadCache->rememberCategoryTree(300, function () {
            return Category::query()
                ->active()
                ->with(['seo'])
                ->orderBy('path')
                ->get();
        });

        return $this->success(CategoryTreeResource::collection($categories)->resolve());
    }

    public function nav(): JsonResponse
    {
        return $this->success($this->categoryNavBuilder->buildPayload());
    }

    public function show(string $slug): JsonResponse
    {
        $category = $this->productReadCache->rememberCategorySlug($slug, 300, function () use ($slug): ?Category {
            return Category::query()
                ->active()
                ->where('full_slug', $slug)
                ->with(['seo', 'children' => fn ($query) => $query->active()->orderByRaw("COALESCE(NULLIF(display_name, ''), name)")])
                ->first();
        });

        if (! $category) {
            abort(404);
        }

        if ($category->relationLoaded('children')) {
            $category->setRelation(
                'children',
                $this->categoryListingOrder
                    ->sortDirectChildren($category, $category->children)
                    ->all(),
            );
        }

        return $this->success((new CategoryTreeResource($category))->resolve());
    }
}
