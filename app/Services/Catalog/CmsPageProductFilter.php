<?php

namespace App\Services\Catalog;

use App\Models\CmsPage;
use Illuminate\Database\Eloquent\Builder;

class CmsPageProductFilter
{
    public function applyListingFilter(Builder $query, string $slug): void
    {
        $page = CmsPage::query()
            ->active()
            ->where('slug', $slug)
            ->first();

        if ($page === null || ! $page->has_product_listing) {
            $query->whereRaw('0 = 1');

            return;
        }

        $productIds = $page->products()->pluck('products.id')->all();

        if ($productIds === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereIn('products.id', $productIds);
    }
}
