<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateSellerCatalogProductRequest;
use App\Http\Requests\Api\V1\UploadSellerProductImageRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\Seller\SellerElineProductService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerCatalogProductController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly SellerElineProductService $sellerProducts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->ensureCanEditProducts($request)) {
            return $response;
        }

        $query = Product::query()
            ->notFromEline()
            ->with(['defaultImage', 'images', 'category:id,name,display_name,full_slug', 'supplierOffers.supplier']);

        $this->applyListingFilters($query, $request);

        $products = $query->paginate(min((int) $request->integer('per_page', 20), 50));

        $items = collect($products->items())
            ->map(fn (Product $product) => $this->sellerProducts->formatSummary($product))
            ->all();

        return $this->paginated($products, $items);
    }

    public function categories(Request $request): JsonResponse
    {
        if ($response = $this->ensureCanEditProducts($request)) {
            return $response;
        }

        $categories = Category::query()
            ->whereHas('products', fn (Builder $builder) => $builder->notFromEline())
            ->orderBy('name')
            ->get(['id', 'name', 'display_name', 'full_slug']);

        $items = $categories
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->display_name ?? $category->name,
                'full_slug' => $category->full_slug,
            ])
            ->all();

        return $this->success($items);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        if ($response = $this->ensureCanEditProducts($request)) {
            return $response;
        }

        $product = $this->sellerProducts->findCatalogProduct($id);

        return $this->success($this->sellerProducts->formatDetail($product));
    }

    public function update(UpdateSellerCatalogProductRequest $request, int $id): JsonResponse
    {
        $product = $this->sellerProducts->findCatalogProduct($id);

        $this->sellerProducts->setPrimaryImage($product, (int) $request->validated('primary_image_id'));

        return $this->success(
            $this->sellerProducts->formatDetail($product->fresh($this->catalogRelations())),
        );
    }

    public function storeImage(UploadSellerProductImageRequest $request, int $id): JsonResponse
    {
        $product = $this->sellerProducts->findCatalogProduct($id);

        $this->sellerProducts->storeImage(
            $product,
            $request->file('image'),
            $request->boolean('is_primary'),
        );

        return $this->success(
            $this->sellerProducts->formatDetail($product->fresh($this->catalogRelations())),
        );
    }

    public function destroyImage(Request $request, int $id, int $imageId): JsonResponse
    {
        if ($response = $this->ensureCanEditProducts($request)) {
            return $response;
        }

        $product = $this->sellerProducts->findCatalogProduct($id);
        $this->sellerProducts->deleteImage($product, $imageId);

        return $this->success(
            $this->sellerProducts->formatDetail($product->fresh($this->catalogRelations())),
        );
    }

    /**
     * @return list<string>
     */
    private function catalogRelations(): array
    {
        return ['images', 'defaultImage', 'category', 'supplierOffers.supplier'];
    }

    private function applyListingFilters(Builder $query, Request $request): void
    {
        $query
            ->orderByRaw('CASE WHEN available_stock > 0 THEN 0 ELSE 1 END')
            ->orderByDesc('updated_at');

        if ($search = trim($request->string('search')->toString())) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->has('in_stock')) {
            if ($request->boolean('in_stock')) {
                $query->where('available_stock', '>', 0);
            } else {
                $query->where('available_stock', '<=', 0);
            }
        }

        if ($request->boolean('on_sale')) {
            $query->where('on_sale', true);
        }
    }

    private function ensureCanEditProducts(Request $request): ?JsonResponse
    {
        if ($request->user()?->canEditSellerElineProducts()) {
            return null;
        }

        return $this->error('Nemate dozvolu za uređivanje proizvoda.', 403);
    }
}
