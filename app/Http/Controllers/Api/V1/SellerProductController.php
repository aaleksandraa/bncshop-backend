<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateSellerProductRequest;
use App\Http\Requests\Api\V1\UploadSellerProductImageRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\Seller\SellerElineProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerProductController extends Controller
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
            ->with(['defaultImage', 'category:id,name,display_name,full_slug', 'supplierOffers.supplier'])
            ->orderByRaw('CASE WHEN available_stock > 0 THEN 0 ELSE 1 END')
            ->orderByDesc('updated_at');

        if ($source = trim($request->string('source')->toString())) {
            $query->where(function ($builder) use ($source): void {
                if ($source === 'a1') {
                    $builder->fromA1();
                } elseif ($source === 'eline') {
                    $builder->fromEline();
                } elseif ($source === 'manual') {
                    $builder->sellerPanelManual();
                }
            });
        }

        if ($search = trim($request->string('search')->toString())) {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function ($builder) use ($needle): void {
                foreach (['name', 'sku', 'eline_sifra', 'barcode'] as $index => $column) {
                    $sql = 'LOWER(COALESCE('.$column.", '')) LIKE ?";
                    if ($index === 0) {
                        $builder->whereRaw($sql, [$needle]);
                    } else {
                        $builder->orWhereRaw($sql, [$needle]);
                    }
                }
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
            ->whereHas('products')
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

        $product = $this->sellerProducts->findSellerProduct($id);

        return $this->success($this->sellerProducts->formatDetail($product));
    }

    public function update(UpdateSellerProductRequest $request, int $id): JsonResponse
    {
        $product = $this->sellerProducts->findSellerProduct($id);
        $payload = $request->validated();
        $contentKeys = ['description', 'short_description', 'sale_price', 'sale_validity', 'sale_ends_at'];
        $touchesContent = array_intersect_key($payload, array_flip($contentKeys)) !== [];

        if ($touchesContent && ! $this->sellerProducts->canEditContent($product)) {
            return $this->error('Ovaj proizvod nema uređivanje opisa i cijene.', 404);
        }

        $updated = $this->sellerProducts->update(
            $product,
            $payload,
            $request->user(),
        );

        return $this->success($this->sellerProducts->formatDetail($updated));
    }

    public function storeImage(UploadSellerProductImageRequest $request, int $id): JsonResponse
    {
        $product = $this->sellerProducts->findSellerProduct($id);

        $this->sellerProducts->storeImage(
            $product,
            $request->file('image'),
            $request->boolean('is_primary'),
        );

        return $this->success(
            $this->sellerProducts->formatDetail($product->fresh(['images', 'defaultImage'])),
        );
    }

    public function destroyImage(Request $request, int $id, int $imageId): JsonResponse
    {
        if ($response = $this->ensureCanEditProducts($request)) {
            return $response;
        }

        $product = $this->sellerProducts->findSellerProduct($id);
        $this->sellerProducts->deleteImage($product, $imageId);

        return $this->success(
            $this->sellerProducts->formatDetail($product->fresh(['images', 'defaultImage'])),
        );
    }

    private function ensureCanEditProducts(Request $request): ?JsonResponse
    {
        if ($request->user()?->canEditSellerElineProducts()) {
            return null;
        }

        return $this->error('Nemate dozvolu za uređivanje eLine proizvoda.', 403);
    }
}
