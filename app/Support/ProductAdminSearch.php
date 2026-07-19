<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class ProductAdminSearch
{
    /**
     * @return array<string, string>
     */
    public static function optionsForSearch(string $search, int $limit = 50): array
    {
        $search = trim($search);

        if ($search === '') {
            return [];
        }

        return self::baseQuery($search)
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'sku'])
            ->mapWithKeys(fn (Product $product): array => [
                (string) $product->id => self::formatOptionLabel($product),
            ])
            ->all();
    }

    public static function applySearchTerm(Builder $query, string $search): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $pattern = '%'.$search.'%';

        return $query->where(function (Builder $inner) use ($search, $operator, $pattern): void {
            $inner->where('name', $operator, $pattern)
                ->orWhere('sku', $operator, $pattern)
                ->orWhere('barcode', $operator, $pattern)
                ->orWhere('eline_sifra', $operator, $pattern);

            if (ctype_digit($search)) {
                $inner->orWhere('id', (int) $search)
                    ->orWhere('external_product_id', $search);
            }

            $inner->orWhereHas('manufacturer', function (Builder $manufacturer) use ($operator, $pattern): void {
                $manufacturer->where('name', $operator, $pattern);
            });
        });
    }

    public static function formatOptionLabel(Product $product): string
    {
        $label = $product->name;

        if ($product->sku) {
            $label .= " ({$product->sku})";
        }

        return $label;
    }

    private static function baseQuery(string $search): Builder
    {
        return self::applySearchTerm(Product::query(), $search);
    }
}
