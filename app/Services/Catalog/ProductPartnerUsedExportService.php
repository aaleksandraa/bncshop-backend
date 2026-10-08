<?php

namespace App\Services\Catalog;

use App\Models\PartnerApiClient;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Cache;

class ProductPartnerUsedExportService
{
    private const COUNT_CACHE_SECONDS = 45;

    public function paginate(
        ?CarbonInterface $updatedSince,
        int $perPage,
        int $page,
        ?PartnerApiClient $client = null,
    ): LengthAwarePaginator {
        $baseQuery = $this->exportableQuery()
            ->when(
                $updatedSince !== null,
                fn (Builder $builder) => $builder->where('updated_at', '>=', $updatedSince),
            );

        $total = $this->cachedTotal($baseQuery, $updatedSince, 'catalog');

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

    public function paginateRemovals(
        CarbonInterface $updatedSince,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        $baseQuery = Product::query()
            ->fromEline()
            ->where('is_refurbished', true)
            ->where('updated_at', '>=', $updatedSince)
            ->where(function (Builder $builder): void {
                $builder
                    ->where('is_public', false)
                    ->orWhere('status', '!=', 'active');
            });

        $total = $this->cachedTotal($baseQuery, $updatedSince, 'removals');

        $items = (clone $baseQuery)
            ->orderBy('updated_at')
            ->orderBy('id')
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
     * @return Builder<Product>
     */
    private function exportableQuery(): Builder
    {
        return Product::query()
            ->fromEline()
            ->where('is_refurbished', true)
            ->public()
            ->active();
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function cachedTotal($query, ?CarbonInterface $updatedSince, string $suffix): int
    {
        $sinceKey = $updatedSince?->utc()->format('Y-m-d\TH:i:s\Z') ?? 'all';

        return (int) Cache::remember(
            'partner-export:product-count:used-eline:'.$suffix.':'.$sinceKey,
            self::COUNT_CACHE_SECONDS,
            fn (): int => (clone $query)->toBase()->count(),
        );
    }
}
