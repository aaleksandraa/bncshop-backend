<?php

namespace App\Filament\Resources\CouponResource\Pages\Concerns;

use App\Models\Category;

trait ManagesCouponScope
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function hydrateCouponScopeFields(array $data): array
    {
        $applicable = is_array($data['applicable_to'] ?? null) ? $data['applicable_to'] : [];

        $data['applicable_scope'] = $applicable['scope'] ?? 'all';

        if ($data['applicable_scope'] === 'all' && $applicable !== []) {
            if (($applicable['product_ids'] ?? []) !== []) {
                $data['applicable_scope'] = 'products';
            } elseif (($applicable['category_ids'] ?? []) !== []) {
                $data['applicable_scope'] = 'categories';
            } elseif (($applicable['manufacturer_ids'] ?? []) !== []) {
                $data['applicable_scope'] = 'brands';
            } elseif (($applicable['tag_ids'] ?? []) !== []) {
                $data['applicable_scope'] = 'tags';
            }
        }

        $data['applicable_product_ids'] = $applicable['product_ids'] ?? [];
        $data['applicable_category_ids'] = $applicable['category_ids'] ?? [];
        $data['applicable_manufacturer_ids'] = $applicable['manufacturer_ids'] ?? [];
        $data['applicable_tag_ids'] = $applicable['tag_ids'] ?? [];
        $data['include_subcategories'] = (bool) ($applicable['include_subcategories'] ?? false);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function persistCouponScopeFields(array $data): array
    {
        $scope = $data['applicable_scope'] ?? 'all';
        $productIds = array_values(array_map(intval(...), $data['applicable_product_ids'] ?? []));
        $categoryIds = array_values(array_map(intval(...), $data['applicable_category_ids'] ?? []));
        $manufacturerIds = array_values(array_map(intval(...), $data['applicable_manufacturer_ids'] ?? []));
        $tagIds = array_values(array_map(intval(...), $data['applicable_tag_ids'] ?? []));
        $includeSubcategories = (bool) ($data['include_subcategories'] ?? false);

        unset(
            $data['applicable_scope'],
            $data['applicable_product_ids'],
            $data['applicable_category_ids'],
            $data['applicable_manufacturer_ids'],
            $data['applicable_tag_ids'],
            $data['include_subcategories'],
        );

        if ($scope === 'all') {
            $data['applicable_to'] = null;

            return $data;
        }

        $data['applicable_to'] = match ($scope) {
            'products' => [
                'scope' => 'products',
                'product_ids' => $productIds,
            ],
            'categories' => [
                'scope' => 'categories',
                'category_ids' => $categoryIds,
                'include_subcategories' => $includeSubcategories,
            ],
            'brands' => [
                'scope' => 'brands',
                'manufacturer_ids' => $manufacturerIds,
            ],
            'tags' => [
                'scope' => 'tags',
                'tag_ids' => $tagIds,
            ],
            default => null,
        };

        return $data;
    }

    /**
     * @return array<int|string, string>
     */
    protected function categoryOptions(): array
    {
        return Category::query()
            ->orderBy('path')
            ->get()
            ->mapWithKeys(fn (Category $category): array => [
                $category->id => str_repeat('— ', max(0, (int) $category->depth)).$category->publicName(),
            ])
            ->all();
    }
}
