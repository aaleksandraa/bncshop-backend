<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateSellerProductRequest;
use App\Http\Requests\Api\V1\UploadSellerProductImageRequest;
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
            ->fromEline()
            ->with(['defaultImage', 'images'])
            ->orderByDesc('updated_at');

        if ($search = trim($request->string('search')->toString())) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('eline_sifra', 'like', "%{$search}%");
            });
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

    public function show(Request $request, int $id): JsonResponse
    {
        if ($response = $this->ensureCanEditProducts($request)) {
            return $response;
        }

        $product = $this->sellerProducts->findElineProduct($id);

        return $this->success($this->sellerProducts->formatDetail($product));
    }

    public function update(UpdateSellerProductRequest $request, int $id): JsonResponse
    {
        $product = $this->sellerProducts->findElineProduct($id);

        $updated = $this->sellerProducts->update(
            $product,
            $request->validated(),
            $request->user(),
        );

        return $this->success($this->sellerProducts->formatDetail($updated));
    }

    public function storeImage(UploadSellerProductImageRequest $request, int $id): JsonResponse
    {
        $product = $this->sellerProducts->findElineProduct($id);

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

        $product = $this->sellerProducts->findElineProduct($id);
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
