<?php

namespace App\Services\Catalog;

use App\Models\PartnerApiClient;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductPartnerExportService
{
    public function paginate(
        ?CarbonInterface $updatedSince,
        int $perPage,
        int $page,
        ?PartnerApiClient $client = null,
    ): LengthAwarePaginator {
        $query = Product::query()
            ->public()
            ->active()
            ->when(
                $updatedSince !== null,
                fn ($builder) => $builder->where('updated_at', '>=', $updatedSince),
            )
            ->orderBy('updated_at')
            ->orderBy('id');

        if ($client?->isFullExport()) {
            $query->with([
                'category:id,display_name,name,full_slug',
                'manufacturer:id,name',
                'images',
                'attributeValues.attributeDefinition.categoryMappings',
            ]);
        }

        return $query->paginate(
            perPage: $perPage,
            page: $page,
        );
    }
}
