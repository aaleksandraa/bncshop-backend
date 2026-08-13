<?php

namespace App\Services\Catalog;

use App\Models\PartnerApiClient;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Cache;

class ProductPartnerExportService
{
    private const COUNT_CACHE_SECONDS = 45;

    public function paginate(
        ?CarbonInterface $updatedSince,
        int $perPage,
        int $page,
        ?PartnerApiClient $client = null,
    ): LengthAwarePaginator {
        $baseQuery = Product::query()
            ->public()
            ->active()
            ->when(
                $updatedSince !== null,
                fn ($builder) => $builder->where('updated_at', '>=', $updatedSince),
            );

        $total = $this->cachedTotal($baseQuery, $updatedSince);

        $dataQuery = (clone $baseQuery)
            ->orderBy('updated_at')
            ->orderBy('id');

        if ($client?->isFullExport()) {
            $dataQuery->with([
                'category:id,display_name,name,full_slug',
                'manufacturer:id,name',
                'images',
                'attributeValues.attributeDefinition.categoryMappings',
            ]);
        }

        $items = $dataQuery
            ->forPage($page, $perPage)
            ->get();

        return new Paginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $query
     */
    private function cachedTotal($query, ?CarbonInterface $updatedSince): int
    {
        $sinceKey = $updatedSince?->utc()->format('Y-m-d\TH:i:s\Z') ?? 'all';

        return (int) Cache::remember(
            'partner-export:product-count:'.$sinceKey,
            self::COUNT_CACHE_SECONDS,
            fn (): int => (clone $query)->toBase()->count(),
        );
    }
}
