<?php

namespace App\Services\Pricing;

use App\Models\Discount;
use App\Models\Product;
use App\Services\Catalog\CategoryScopeResolver;
use App\Services\Pricing\PricingCache;
use Illuminate\Database\Eloquent\Builder;

class DiscountEngine
{
    public function __construct(
        private readonly CategoryScopeResolver $categoryScopeResolver,
        private readonly PricingCache $pricingCache,
    ) {}

    /**
     * @return array<int, Discount>
     */
    public function applicableForProduct(Product $product): array
    {
        $product->loadMissing(['category', 'manufacturer', 'tags', 'attributeValues']);

        $discounts = $this->pricingCache->rememberActiveDiscounts(
            fn () => Discount::query()
                ->where('is_active', true)
                ->where(function (Builder $query): void {
                    $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                })
                ->where(function (Builder $query): void {
                    $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                })
                ->with(['excludedProducts', 'excludedBrands', 'categories', 'manufacturers'])
                ->get()
        );

        return $discounts
            ->filter(fn (Discount $discount): bool => $this->matchesProduct($discount, $product))
            ->values()
            ->all();
    }

    public function bestForProduct(Product $product): ?Discount
    {
        $applicable = $this->applicableForProduct($product);

        if ($applicable === []) {
            return null;
        }

        $regularPrice = (float) ($product->regular_price ?? $product->api_price ?? 0);

        return $this->resolveBestDiscount($applicable, $regularPrice);
    }

    public function applyDiscount(Discount $discount, float $price): float
    {
        if ($discount->discount_type === 'percentage') {
            return round($price * (1 - ((float) $discount->value / 100)), 2);
        }

        return max(0, round($price - (float) $discount->value, 2));
    }

    public function discountAmount(Discount $discount, float $price): float
    {
        return round($price - $this->applyDiscount($discount, $price), 2);
    }

    /**
     * @param  array<int, Discount>  $discounts
     */
    private function resolveBestDiscount(array $discounts, float $regularPrice): ?Discount
    {
        $mode = config('bnc.discount_combination_mode', 'best_single');

        return match ($mode) {
            'stack' => $this->bestStackedDiscount($discounts, $regularPrice),
            'product_priority' => $this->priorityDiscount($discounts, 'product', $regularPrice),
            'category_priority' => $this->priorityDiscount($discounts, 'category', $regularPrice),
            default => $this->lowestPriceDiscount($discounts, $regularPrice),
        };
    }

    /**
     * @param  array<int, Discount>  $discounts
     */
    private function lowestPriceDiscount(array $discounts, float $regularPrice): ?Discount
    {
        $best = null;
        $bestPrice = $regularPrice;

        foreach ($discounts as $discount) {
            $discounted = $this->applyDiscount($discount, $regularPrice);
            if ($discounted < $bestPrice) {
                $bestPrice = $discounted;
                $best = $discount;
            }
        }

        return $best;
    }

    /**
     * @param  array<int, Discount>  $discounts
     */
    private function bestStackedDiscount(array $discounts, float $regularPrice): ?Discount
    {
        usort($discounts, fn (Discount $a, Discount $b): int => $this->typePriority($a->type) <=> $this->typePriority($b->type));

        $price = $regularPrice;
        $last = null;

        foreach ($discounts as $discount) {
            $price = $this->applyDiscount($discount, $price);
            $last = $discount;
        }

        return $last;
    }

    /**
     * @param  array<int, Discount>  $discounts
     */
    private function priorityDiscount(array $discounts, string $preferredType, float $regularPrice): ?Discount
    {
        $preferred = array_values(array_filter($discounts, fn (Discount $d): bool => $d->type === $preferredType));

        if ($preferred !== []) {
            return $this->lowestPriceDiscount($preferred, $regularPrice);
        }

        return $this->lowestPriceDiscount($discounts, $regularPrice);
    }

    private function typePriority(string $type): int
    {
        return match ($type) {
            'product' => 1,
            'category' => 2,
            'brand' => 3,
            'attribute' => 4,
            'tag' => 5,
            default => 99,
        };
    }

    private function matchesProduct(Discount $discount, Product $product): bool
    {
        if ($this->isExcluded($discount, $product)) {
            return false;
        }

        return match ($discount->type) {
            'product' => $discount->product_id === $product->id,
            'category' => $this->matchesCategory($discount, $product),
            'brand' => $this->matchesBrand($discount, $product),
            'tag' => $this->matchesTag($discount, $product),
            'attribute' => $this->matchesAttribute($discount, $product),
            default => false,
        };
    }

    private function isExcluded(Discount $discount, Product $product): bool
    {
        if ($discount->excludedProducts->contains('id', $product->id)) {
            return true;
        }

        if ($product->manufacturer_id && $discount->excludedBrands->contains('id', $product->manufacturer_id)) {
            return true;
        }

        return false;
    }

    private function matchesCategory(Discount $discount, Product $product): bool
    {
        $categoryIds = $this->scopedCategoryIds($discount);

        if ($categoryIds === []) {
            return false;
        }

        return $this->categoryScopeResolver->matchesAnyCategory(
            $product,
            $categoryIds,
            (bool) $discount->include_subcategories,
        );
    }

    private function matchesBrand(Discount $discount, Product $product): bool
    {
        if ($product->manufacturer_id === null) {
            return false;
        }

        $manufacturerIds = $this->scopedManufacturerIds($discount);

        if ($manufacturerIds === []) {
            return false;
        }

        return in_array((int) $product->manufacturer_id, $manufacturerIds, true);
    }

    /**
     * @return array<int, int>
     */
    private function scopedCategoryIds(Discount $discount): array
    {
        $ids = $discount->categories
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($ids !== []) {
            return $ids;
        }

        return $discount->category_id ? [(int) $discount->category_id] : [];
    }

    /**
     * @return array<int, int>
     */
    private function scopedManufacturerIds(Discount $discount): array
    {
        $ids = $discount->manufacturers
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($ids !== []) {
            return $ids;
        }

        return $discount->manufacturer_id ? [(int) $discount->manufacturer_id] : [];
    }

    private function matchesTag(Discount $discount, Product $product): bool
    {
        if (! $discount->tag_id) {
            return false;
        }

        return $product->tags->contains('id', $discount->tag_id);
    }

    private function matchesAttribute(Discount $discount, Product $product): bool
    {
        $conditions = $discount->conditions_json ?? [];

        if ($conditions === []) {
            return false;
        }

        $attributeId = $conditions['attribute_definition_id'] ?? $conditions['attribute_id'] ?? null;
        $expectedValue = $conditions['value'] ?? null;

        if (! $attributeId || $expectedValue === null) {
            return false;
        }

        $attributeValue = $product->attributeValues
            ->firstWhere('attribute_definition_id', (int) $attributeId);

        if (! $attributeValue) {
            return false;
        }

        $actual = $attributeValue->normalized_value ?? $attributeValue->raw_value;

        return (string) $actual === (string) $expectedValue;
    }
}
