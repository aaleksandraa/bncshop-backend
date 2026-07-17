<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductPartnerExportService
{
    public function paginate(?CarbonInterface $updatedSince, int $perPage, int $page): LengthAwarePaginator
    {
        return Product::query()
            ->public()
            ->active()
            ->when(
                $updatedSince !== null,
                fn ($query) => $query->where('updated_at', '>=', $updatedSince),
            )
            ->orderBy('updated_at')
            ->orderBy('id')
            ->paginate(
                perPage: $perPage,
                page: $page,
            );
    }
}
