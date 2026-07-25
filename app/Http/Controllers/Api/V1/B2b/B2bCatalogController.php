<?php

namespace App\Http\Controllers\Api\V1\B2b;

use App\Http\Controllers\Api\V1\B2b\Concerns\FormatsB2bResponses;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Models\B2bCategory;
use App\Models\B2bProduct;
use App\Services\B2b\B2bProductAttributeService;
use App\Services\B2b\B2bReadCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class B2bCatalogController extends Controller
{
    use FormatsB2bResponses;
    use RespondsWithJson;

    public function __construct(
        private readonly B2bReadCache $b2bReadCache,
        private readonly B2bProductAttributeService $attributeService,
    ) {}

    /**
     * @return array<int, string>
     */
    private function catalogRelations(): array
    {
        return [
            'category',
            'images',
            'attributeValues.definition',
            'campaigns' => fn ($query) => $query
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                })
                ->where(function ($query): void {
                    $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                }),
        ];
    }

    public function categories(Request $request): JsonResponse
    {
        $payload = $this->b2bReadCache->rememberCategories(120, function (): array {
            $categories = B2bCategory::query()
                ->where('is_active', true)
                ->withCount(['products' => fn ($query) => $query->where('is_active', true)])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            return $categories
                ->map(fn (B2bCategory $category) => $this->formatCategory($category))
                ->values()
                ->all();
        });

        return $this->success($payload);
    }

    public function categoryFilters(Request $request, string $slug): JsonResponse
    {
        $category = B2bCategory::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $payload = $this->b2bReadCache->rememberCategoryFilters($slug, 120, fn (): array => [
            'category' => $this->formatCategory($category),
            'attributes' => $this->attributeService->filtersForCategory($category),
        ]);

        return $this->success($payload);
    }

    public function products(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'attrs' => ['nullable', 'array'],
            'attrs.*' => ['nullable'],
        ]);

        $attributeFilters = collect($validated['attrs'] ?? [])
            ->map(function ($values): array {
                if (is_array($values)) {
                    return array_values(array_filter(array_map(
                        fn ($value) => trim((string) $value),
                        $values,
                    )));
                }

                $value = trim((string) $values);

                return $value === '' ? [] : [$value];
            })
            ->filter(fn (array $values): bool => $values !== [])
            ->all();

        $customer = $this->b2bCustomer($request);

        $query = B2bProduct::query()
            ->with($this->catalogRelations())
            ->where('is_active', true);

        if (! empty($validated['category'])) {
            $categoryId = B2bCategory::query()
                ->where('slug', $validated['category'])
                ->where('is_active', true)
                ->value('id');

            if ($categoryId) {
                $query->where('b2b_category_id', $categoryId);
            } else {
                $query->whereRaw('0 = 1');
            }
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('sku', 'ilike', "%{$search}%");
            });
        }

        if ($attributeFilters !== []) {
            $this->attributeService->applyFilters($query, $attributeFilters);
        }

        $paginator = $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 24);

        return $this->paginated(
            $paginator,
            collect($paginator->items())
                ->map(fn (B2bProduct $product) => $this->formatProductList($product, $customer))
                ->values()
                ->all()
        );
    }

    public function showProduct(Request $request, string $slug): JsonResponse
    {
        $customer = $this->b2bCustomer($request);

        $product = B2bProduct::query()
            ->with($this->catalogRelations())
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return $this->success($this->formatProduct($product, $customer));
    }
}
