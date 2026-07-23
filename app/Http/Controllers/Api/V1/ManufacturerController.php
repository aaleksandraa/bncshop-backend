<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Models\Manufacturer;
use App\Services\Catalog\ProductReadCache;
use App\Support\PublicStorageUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManufacturerController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly ProductReadCache $productReadCache,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $cacheKey = $request->boolean('featured') ? 'featured' : 'all';

        $payload = $this->productReadCache->rememberManufacturers($cacheKey, 600, function () use ($request): array {
            $query = Manufacturer::query()
                ->withCount([
                    'products as products_count' => fn ($builder) => $builder->public()->active(),
                ])
                ->orderBy('sort_order')
                ->orderByDesc('products_count')
                ->orderBy('name');

            if ($request->boolean('featured')) {
                $query->where('featured', true);
            }

            return $query
                ->get([
                    'id',
                    'name',
                    'slug',
                    'featured',
                    'sort_order',
                    'logo_url',
                    'logo_path',
                ])
                ->map(fn (Manufacturer $manufacturer): array => [
                    'id' => $manufacturer->id,
                    'name' => $manufacturer->name,
                    'slug' => $manufacturer->slug,
                    'featured' => (bool) $manufacturer->featured,
                    'sort_order' => (int) $manufacturer->sort_order,
                    'products_count' => (int) $manufacturer->products_count,
                    'logo_url' => PublicStorageUrl::absoluteFromResolved($manufacturer->logoUrl()),
                ])
                ->all();
        });

        return $this->success($payload);
    }

    public function show(string $slug): JsonResponse
    {
        $payload = $this->productReadCache->rememberManufacturers("slug:{$slug}", 600, function () use ($slug): array {
            $manufacturer = Manufacturer::query()
                ->where('slug', $slug)
                ->with(['seoOverride'])
                ->withCount([
                    'products as products_count' => fn ($builder) => $builder->public()->active(),
                ])
                ->firstOrFail();

            return [
                'id' => $manufacturer->id,
                'name' => $manufacturer->name,
                'slug' => $manufacturer->slug,
                'featured' => (bool) $manufacturer->featured,
                'sort_order' => (int) $manufacturer->sort_order,
                'products_count' => (int) $manufacturer->products_count,
                'description' => $manufacturer->description,
                'logo_url' => PublicStorageUrl::absoluteFromResolved($manufacturer->logoUrl()),
                'meta_title' => $manufacturer->meta_title,
                'meta_description' => $manufacturer->meta_description,
                'seo_override' => $manufacturer->seoOverride,
            ];
        });

        return $this->success($payload);
    }
}
