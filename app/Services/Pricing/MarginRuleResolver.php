<?php

namespace App\Services\Pricing;

use App\Models\Category;
use App\Models\CategoryMarginRule;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierCategoryMarginRule;

class MarginRuleResolver
{
    /**
     * @return array{margin_percentage: ?float, source: string, rule_id: ?int}
     */
    public function resolve(Product $product, ?Supplier $supplier): array
    {
        if ($this->shouldUseProductMargin($product)) {
            return [
                'margin_percentage' => (float) $product->margin_percentage,
                'source' => 'product',
                'rule_id' => null,
            ];
        }

        if ($this->appliesA1CategoryMarginRules($product)) {
            $categoryRule = $this->findCategoryMarginRule($product);

            if ($categoryRule) {
                return [
                    'margin_percentage' => (float) $categoryRule->margin_percentage,
                    'source' => 'category_rule',
                    'rule_id' => $categoryRule->id,
                ];
            }
        }

        if ($supplier && $product->category_id) {
            $rule = $this->findSupplierCategoryRule($product, $supplier);

            if ($rule) {
                return [
                    'margin_percentage' => (float) $rule->margin_percentage,
                    'source' => 'rule',
                    'rule_id' => $rule->id,
                ];
            }
        }

        $product->loadMissing('category');

        if ($product->category?->margin_percentage !== null && (float) $product->category->margin_percentage > 0) {
            return [
                'margin_percentage' => (float) $product->category->margin_percentage,
                'source' => 'category',
                'rule_id' => null,
            ];
        }

        return [
            'margin_percentage' => null,
            'source' => 'none',
            'rule_id' => null,
        ];
    }

    private function shouldUseProductMargin(Product $product): bool
    {
        if ($product->margin_percentage === null || (float) $product->margin_percentage <= 0) {
            return false;
        }

        if ($this->appliesA1CategoryMarginRules($product)) {
            return false;
        }

        return true;
    }

    private function appliesA1CategoryMarginRules(Product $product): bool
    {
        if ($product->import_source === 'eline') {
            return false;
        }

        return (bool) $product->is_new;
    }

    private function findCategoryMarginRule(Product $product): ?CategoryMarginRule
    {
        if (! $product->category_id) {
            return null;
        }

        $chain = $this->categoryChain($product->category_id);

        foreach ($chain as $categoryId) {
            $rule = CategoryMarginRule::query()
                ->where('category_id', $categoryId)
                ->where('is_active', true)
                ->with('targetCategories')
                ->first();

            if ($rule && $rule->appliesToCategoryId((int) $product->category_id)) {
                return $rule;
            }
        }

        return null;
    }

    private function findSupplierCategoryRule(Product $product, Supplier $supplier): ?SupplierCategoryMarginRule
    {
        if (! $product->category_id) {
            return null;
        }

        $chain = $this->categoryChain($product->category_id);

        foreach ($chain as $categoryId) {
            $rule = SupplierCategoryMarginRule::query()
                ->where('supplier_id', $supplier->id)
                ->where('category_id', $categoryId)
                ->where('is_active', true)
                ->with('targetCategories')
                ->first();

            if ($rule && $rule->appliesToCategoryId((int) $product->category_id)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * @return array<int, int>
     */
    private function categoryChain(?int $categoryId): array
    {
        if (! $categoryId) {
            return [];
        }

        $chain = [];
        $category = Category::query()->find($categoryId);

        while ($category) {
            $chain[] = $category->id;
            $category = $category->parent_id
                ? Category::query()->find($category->parent_id)
                : null;
        }

        return $chain;
    }
}
