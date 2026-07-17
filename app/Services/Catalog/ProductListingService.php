<?php

namespace App\Services\Catalog;

use App\Http\Resources\ProductCardResource;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Services\Catalog\CategoryScopeResolver;
use App\Services\Search\FilterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Throwable;

class ProductListingService
{
    public function __construct(
        private readonly FilterService $filterService,
        private readonly ProductReadCache $productReadCache,
        private readonly CategoryScopeResolver $categoryScopeResolver,
        private readonly CategoryListingOrder $categoryListingOrder,
    ) {}

    public function shouldUseMeilisearch(Request $request): bool
    {
        if ($request->string('q')->toString() !== '') {
            return true;
        }

        if ($request->has('filters') && count((array) $request->input('filters', [])) > 0) {
            return true;
        }

        return false;
    }

    /**
     * @return array{items: array<int, mixed>, total: int, per_page: int, current_page: int, last_page: int}
     */
    public function listWithFallback(Request $request, int $perPage): array
    {
        if (! $this->shouldUseMeilisearch($request)) {
            return $this->listViaDatabase($request, $perPage);
        }

        try {
            return $this->listViaMeilisearch($request, $perPage);
        } catch (Throwable $exception) {
            report($exception);

            return $this->listViaDatabase($request, $perPage);
        }
    }

    /**
     * @return array{items: array<int, mixed>, total: int, per_page: int, current_page: int, last_page: int}
     */
    public function listViaMeilisearch(Request $request, int $perPage): array
    {
        $query = $request->string('q')->toString();
        $category = null;

        if ($categorySlug = $request->string('category')->toString()) {
            $category = $this->productReadCache->rememberCategorySlug($categorySlug, 300, function () use ($categorySlug): ?Category {
                return Category::query()->where('full_slug', $categorySlug)->first();
            });
        }

        $filters = $this->normalizeFilters($request);
        $page = max(1, (int) $request->integer('page', 1));
        $sort = $this->resolveMeilisearchSort($request);

        if ($query !== '') {
            if ($category) {
                $meiliFilters = $this->filterService->buildMeilisearchFilters($category, $filters);
                $results = Product::search($query, function ($engine, $searchQuery, $options) use ($meiliFilters, $sort) {
                    $options['filter'] = implode(' AND ', $meiliFilters);
                    if ($sort !== null) {
                        $options['sort'] = $sort;
                    }

                    return $engine->search($searchQuery, $options);
                })->paginate($perPage, 'page', $page);
            } else {
                $results = Product::search($query, function ($engine, $searchQuery, $options) use ($sort) {
                    if ($sort !== null) {
                        $options['sort'] = $sort;
                    }

                    return $engine->search($searchQuery, $options);
                })->paginate($perPage, 'page', $page);
            }
        } elseif ($category) {
            $meiliFilters = $this->filterService->buildMeilisearchFilters($category, $filters);
            $results = Product::search('', function ($engine, $searchQuery, $options) use ($meiliFilters, $sort) {
                $options['filter'] = implode(' AND ', $meiliFilters);
                if ($sort !== null) {
                    $options['sort'] = $sort;
                }

                return $engine->search($searchQuery, $options);
            })->paginate($perPage, 'page', $page);
        } else {
            return $this->listViaDatabase($request, $perPage);
        }

        $items = $this->hydrateSearchResults($results->items());

        return [
            'items' => ProductCardResource::collection($items)->resolve(),
            'total' => $results->total(),
            'per_page' => $results->perPage(),
            'current_page' => $results->currentPage(),
            'last_page' => $results->lastPage(),
        ];
    }

    /**
     * @return array{items: array<int, mixed>, total: int, per_page: int, current_page: int, last_page: int}
     */
    public function listViaDatabase(Request $request, int $perPage): array
    {
        $query = Product::query()
            ->public()
            ->active()
            ->select([
                'id',
                'slug',
                'name',
                'short_description',
                'display_price',
                'regular_price',
                'stock_status',
                'available_stock',
                'is_new',
                'is_gaming',
                'is_refurbished',
                'on_sale',
                'manufacturer_id',
                'category_id',
                'default_image_id',
            ])
            ->with([
                'manufacturer:id,name,slug,logo_path,logo_url',
                'category:id,name,full_slug',
                'defaultImage:id,product_id,local_path,public_url,image_url,source_url,is_primary,sort_order,width,height',
            ]);

        $this->applyDatabaseFilters($query, $request);

        $paginator = $query->paginate($perPage);

        return [
            'items' => ProductCardResource::collection($paginator->items())->resolve(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    private function applyDatabaseFilters(\Illuminate\Database\Eloquent\Builder $query, Request $request): void
    {
        $category = null;

        if ($categorySlug = $request->string('category')->toString()) {
            $category = $this->productReadCache->rememberCategorySlug($categorySlug, 300, function () use ($categorySlug): ?Category {
                return Category::query()->where('full_slug', $categorySlug)->first();
            });
            if ($category) {
                $categoryIds = $this->categoryScopeResolver->expandWithDescendants([(int) $category->id]);
                $query->whereIn('category_id', $categoryIds);
            }
        }

        if ($brandSlug = $request->string('brand')->toString()) {
            $manufacturer = Manufacturer::query()->where('slug', $brandSlug)->first();
            if ($manufacturer) {
                $query->where('manufacturer_id', $manufacturer->id);
            }
        }

        if ($request->boolean('in_stock')) {
            $query->where('available_stock', '>', 0);
        }

        if ($request->boolean('on_sale')) {
            $query->where('on_sale', true);
        }

        if ($request->boolean('is_gaming')) {
            $query->where('is_gaming', true);
        }

        if ($request->boolean('is_new')) {
            $query->where('is_new', true);
        }

        if ($request->boolean('is_refurbished')) {
            $query->where('is_refurbished', true);
        }

        if ($request->boolean('has_image')) {
            $query->whereNotNull('default_image_id');
        }

        if ($request->filled('min_price')) {
            $query->where('display_price', '>=', (float) $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('display_price', '<=', (float) $request->input('max_price'));
        }

        if ($search = trim($request->string('q')->toString())) {
            $like = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';

            $query->where(function (Builder $builder) use ($like): void {
                $builder->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(sku) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(short_description) LIKE ?', [$like])
                    ->orWhereHas(
                        'manufacturer',
                        fn (Builder $manufacturerQuery) => $manufacturerQuery->whereRaw('LOWER(name) LIKE ?', [$like]),
                    );
            });
        }

        if ($category !== null && $this->shouldApplySubcategoryOrdering($request)) {
            $this->categoryListingOrder->applyProductOrdering($query, $category);
        }

        match ($request->string('sort')->toString()) {
            'price_asc' => $query->orderBy('display_price'),
            'price_desc' => $query->orderByDesc('display_price'),
            'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            'newest' => $query->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };
    }

    /**
     * @param  array<int, Product>  $items
     * @return array<int, Product>
     */
    private function hydrateSearchResults(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $ids = collect($items)->pluck('id')->all();
        $order = array_flip($ids);

        return Product::query()
            ->public()
            ->active()
            ->select([
                'id',
                'slug',
                'name',
                'short_description',
                'display_price',
                'regular_price',
                'stock_status',
                'available_stock',
                'is_new',
                'is_gaming',
                'is_refurbished',
                'on_sale',
                'manufacturer_id',
                'category_id',
                'default_image_id',
            ])
            ->with([
                'manufacturer:id,name,slug,logo_path,logo_url',
                'category:id,name,full_slug',
                'defaultImage:id,product_id,local_path,public_url,image_url,source_url,is_primary,sort_order,width,height',
            ])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Product $product): int => $order[$product->id] ?? PHP_INT_MAX)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeFilters(Request $request): array
    {
        $filters = [];

        foreach ($request->input('filters', []) as $attributeId => $value) {
            $filters['attr_'.$attributeId] = $value;
        }

        foreach (['min_price', 'max_price', 'in_stock', 'on_sale', 'is_gaming', 'is_new', 'is_refurbished', 'brand_id', 'manufacturer_id'] as $key) {
            if ($request->has($key)) {
                $filters[$key] = $request->input($key);
            }
        }

        return $filters;
    }

    private function shouldApplySubcategoryOrdering(Request $request): bool
    {
        if ($request->string('q')->toString() !== '') {
            return false;
        }

        $sort = $request->string('sort')->toString();

        return $sort === '' || $sort === 'newest';
    }

    /**
     * @return array<int, string>|null
     */
    private function resolveMeilisearchSort(Request $request): ?array
    {
        return match ($request->string('sort')->toString()) {
            'price_asc' => ['display_price:asc'],
            'price_desc' => ['display_price:desc'],
            'name_asc' => ['name:asc'],
            'name_desc' => ['name:desc'],
            'newest' => ['created_at:desc'],
            default => null,
        };
    }
}
