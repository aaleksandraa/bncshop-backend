<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\Catalog\ProductListingService;
use App\Services\Catalog\ProductReadCache;
use App\Services\Pricing\CouponEngine;
use App\Support\PublicStorageUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly ProductReadCache $productReadCache,
        private readonly ProductListingService $productListingService,
        private readonly CouponEngine $couponEngine,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 24), 100);
        $cacheKey = 'products:list:'.md5(json_encode($request->query()));
        $ttl = $this->listCacheTtl($request);

        $payload = $this->productReadCache->rememberList($cacheKey, $ttl, function () use ($request, $perPage): array {
            return $this->productListingService->listWithFallback($request, $perPage);
        });

        return $this->success(
            PublicStorageUrl::rewriteStorageUrlsInValue($payload['items']),
            [
                'pagination' => [
                    'current_page' => $payload['current_page'],
                    'per_page' => $payload['per_page'],
                    'total' => $payload['total'],
                    'last_page' => $payload['last_page'],
                ],
            ],
        );
    }

    public function categoryOptions(Request $request): JsonResponse
    {
        $cacheKey = 'products:category-options:'.md5(json_encode($request->query()));

        $payload = $this->productReadCache->rememberList($cacheKey, 300, function () use ($request): array {
            $slugs = $this->productListingService->categoryFullSlugsWithProducts($request);

            return [
                'items' => $slugs,
                'total' => count($slugs),
                'per_page' => count($slugs),
                'current_page' => 1,
                'last_page' => 1,
            ];
        });

        return $this->success($payload['items']);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $couponCode = $request->string('kupon')->trim()->toString()
            ?: $request->string('coupon')->trim()->toString();

        if ($couponCode !== '') {
            $product = Product::query()
                ->public()
                ->active()
                ->where('slug', $slug)
                ->with([
                    'manufacturer',
                    'category.seo',
                    'defaultImage',
                    'images' => fn ($q) => $q->where('status', 'active')->orderBy('sort_order'),
                    'attributeValues.attributeDefinition.categoryMappings',
                    'tags',
                    'seoOverride',
                ])
                ->firstOrFail();

            $payload = (new ProductResource($product))->resolve();
            $payload['coupon'] = $this->couponEngine->previewForProduct(
                $couponCode,
                $product,
                $request->user(),
            );

            return $this->success(PublicStorageUrl::rewriteStorageUrlsInValue($payload));
        }

        $payload = $this->productReadCache->rememberProduct($slug, 900, function () use ($slug): array {
            $product = Product::query()
                ->public()
                ->active()
                ->where('slug', $slug)
                ->with([
                    'manufacturer',
                    'category.seo',
                    'defaultImage',
                    'images' => fn ($q) => $q->where('status', 'active')->orderBy('sort_order'),
                    'attributeValues.attributeDefinition.categoryMappings',
                    'tags',
                    'seoOverride',
                ])
                ->firstOrFail();

            return (new ProductResource($product))->resolve();
        });

        return $this->success(PublicStorageUrl::rewriteStorageUrlsInValue($payload));
    }

    private function listCacheTtl(Request $request): int
    {
        if ($request->string('q')->toString() !== '') {
            return 60;
        }

        if ($request->has('filters') && count((array) $request->input('filters', [])) > 0) {
            return 60;
        }

        foreach ([
            'min_price',
            'max_price',
            'brand',
            'in_stock',
            'on_sale',
            'is_gaming',
            'is_new',
            'is_refurbished',
            'has_image',
            'campaign',
            'sort',
        ] as $key) {
            if ($request->filled($key) && ! ($key === 'sort' && $request->string('sort')->toString() === 'newest')) {
                return 60;
            }
        }

        return 240;
    }
}
