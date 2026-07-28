<?php

namespace App\Services\Catalog;

use App\Jobs\ReindexProductsJob;
use App\Models\AttributeCategoryMapping;
use App\Models\Category;
use App\Models\CategoryMarginRule;
use App\Models\Discount;
use App\Models\ElineCategoryMapping;
use App\Models\ElineProductOverride;
use App\Models\MenuItem;
use App\Models\OlxCategoryMapping;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\ShippingRule;
use App\Models\SupplierCategoryMarginRule;
use App\Support\Catalog\CategoryScopeResolver;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CategoryMergeService
{
    /**
     * @param  array{
     *     reparent_children?: bool,
     *     deactivate_source?: bool,
     *     create_redirect?: bool,
     * }  $options
     * @return array{products: int, children: int, mappings: int, redirects: int}
     */
    public function merge(Category $target, Category $source, array $options = []): array
    {
        $this->assertCanMerge($target, $source);

        $reparentChildren = $options['reparent_children'] ?? true;
        $deactivateSource = $options['deactivate_source'] ?? true;
        $createRedirect = $options['create_redirect'] ?? true;

        $stats = [
            'products' => 0,
            'children' => 0,
            'mappings' => 0,
            'redirects' => 0,
        ];

        $affectedProductIds = [];

        DB::transaction(function () use (
            $target,
            $source,
            $reparentChildren,
            $deactivateSource,
            $createRedirect,
            &$stats,
            &$affectedProductIds,
        ): void {
            $affectedProductIds = Product::query()
                ->where('category_id', $source->id)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $stats['products'] = Product::query()
                ->where('category_id', $source->id)
                ->update(['category_id' => $target->id]);

            if ($reparentChildren) {
                $stats['children'] = Category::query()
                    ->where('parent_id', $source->id)
                    ->update(['parent_id' => $target->id]);
            }

            $stats['mappings'] = $this->mergeAttributeMappings($target, $source);
            $this->mergeMarginRules($target, $source);
            $this->mergeSupplierMarginRules($target, $source);
            $this->reassignCategoryReferences($target, $source);
            $this->mergeDiscountCategoryLinks($target, $source);
            $this->mergeMarginRuleTargetLinks($target, $source);

            if ($createRedirect && $source->full_slug !== $target->full_slug) {
                $stats['redirects'] = $this->createSlugRedirect($source, $target);
            }

            if ($deactivateSource) {
                $source->update(['status' => 'inactive']);
            }
        });

        if ($affectedProductIds !== []) {
            ReindexProductsJob::dispatch(array_values(array_unique($affectedProductIds)));
        }

        $cache = app(ProductReadCache::class);
        $cache->flushListAndFilters($target->id);
        $cache->flushListAndFilters($source->id);
        $cache->flushCategories();

        return $stats;
    }

    public function assertCanMerge(Category $target, Category $source): void
    {
        if ($target->id === $source->id) {
            throw new InvalidArgumentException('Kategorija se ne može spojiti sama u sebe.');
        }

        $sourceTreeIds = CategoryScopeResolver::descendantIds($source->id, includeSelf: true);

        if (in_array($target->id, $sourceTreeIds, true)) {
            throw new InvalidArgumentException('Ciljna kategorija ne smije biti unutar izvorne kategorije.');
        }
    }

    private function mergeAttributeMappings(Category $target, Category $source): int
    {
        $merged = 0;

        $sourceMappings = AttributeCategoryMapping::query()
            ->where('category_id', $source->id)
            ->get();

        foreach ($sourceMappings as $mapping) {
            $existing = AttributeCategoryMapping::query()
                ->where('category_id', $target->id)
                ->where('attribute_definition_id', $mapping->attribute_definition_id)
                ->first();

            if ($existing === null) {
                $mapping->update(['category_id' => $target->id]);
                $merged++;

                continue;
            }

            $existing->update([
                'is_filter_enabled' => $existing->is_filter_enabled || $mapping->is_filter_enabled,
                'is_public_enabled' => $existing->is_public_enabled || $mapping->is_public_enabled,
                'sort_order' => min((int) $existing->sort_order, (int) $mapping->sort_order),
            ]);

            $mapping->delete();
            $merged++;
        }

        return $merged;
    }

    private function mergeMarginRules(Category $target, Category $source): void
    {
        $sourceRule = CategoryMarginRule::query()->where('category_id', $source->id)->first();

        if ($sourceRule === null) {
            return;
        }

        $targetRule = CategoryMarginRule::query()->where('category_id', $target->id)->first();

        if ($targetRule === null) {
            $sourceRule->update(['category_id' => $target->id]);

            return;
        }

        $sourceRule->delete();
    }

    private function mergeSupplierMarginRules(Category $target, Category $source): void
    {
        $sourceRules = SupplierCategoryMarginRule::query()
            ->where('category_id', $source->id)
            ->get();

        foreach ($sourceRules as $sourceRule) {
            $targetRule = SupplierCategoryMarginRule::query()
                ->where('supplier_id', $sourceRule->supplier_id)
                ->where('category_id', $target->id)
                ->first();

            if ($targetRule === null) {
                $sourceRule->update(['category_id' => $target->id]);

                continue;
            }

            $sourceRule->delete();
        }
    }

    private function reassignCategoryReferences(Category $target, Category $source): void
    {
        Discount::query()->where('category_id', $source->id)->update(['category_id' => $target->id]);
        ShippingRule::query()->where('category_id', $source->id)->update(['category_id' => $target->id]);
        ElineCategoryMapping::query()->where('category_id', $source->id)->update(['category_id' => $target->id]);
        MenuItem::query()->where('category_id', $source->id)->update(['category_id' => $target->id]);
        ElineProductOverride::query()->where('category_id', $source->id)->update(['category_id' => $target->id]);
        $this->mergeOlxCategoryMappings($target, $source);
    }

    private function mergeOlxCategoryMappings(Category $target, Category $source): void
    {
        $sourceMapping = OlxCategoryMapping::query()->where('category_id', $source->id)->first();

        if ($sourceMapping === null) {
            return;
        }

        $targetMapping = OlxCategoryMapping::query()->where('category_id', $target->id)->first();

        if ($targetMapping === null) {
            $sourceMapping->update(['category_id' => $target->id]);

            return;
        }

        $sourceMapping->delete();
    }

    private function mergeDiscountCategoryLinks(Category $target, Category $source): void
    {
        $discountIds = DB::table('discount_category')
            ->where('category_id', $source->id)
            ->pluck('discount_id');

        foreach ($discountIds as $discountId) {
            DB::table('discount_category')->insertOrIgnore([
                'discount_id' => $discountId,
                'category_id' => $target->id,
            ]);
        }

        DB::table('discount_category')
            ->where('category_id', $source->id)
            ->delete();
    }

    private function mergeMarginRuleTargetLinks(Category $target, Category $source): void
    {
        $this->mergeTargetPivotRows(
            'category_margin_rule_targets',
            'category_margin_rule_id',
            $target->id,
            $source->id,
        );

        $this->mergeTargetPivotRows(
            'supplier_category_margin_rule_targets',
            'supplier_category_margin_rule_id',
            $target->id,
            $source->id,
        );
    }

    private function mergeTargetPivotRows(
        string $table,
        string $ruleColumn,
        int $targetCategoryId,
        int $sourceCategoryId,
    ): void {
        $rows = DB::table($table)
            ->where('category_id', $sourceCategoryId)
            ->get();

        foreach ($rows as $row) {
            $conflict = DB::table($table)
                ->where($ruleColumn, $row->{$ruleColumn})
                ->where('category_id', $targetCategoryId)
                ->exists();

            if ($conflict) {
                DB::table($table)->where('id', $row->id)->delete();

                continue;
            }

            DB::table($table)
                ->where('id', $row->id)
                ->update(['category_id' => $targetCategoryId]);
        }
    }

    private function createSlugRedirect(Category $source, Category $target): int
    {
        $fromPath = '/kategorija/'.$source->full_slug;
        $toPath = '/kategorija/'.$target->full_slug;

        Redirect::query()->updateOrCreate(
            ['from_path' => $fromPath],
            [
                'to_path' => $toPath,
                'status_code' => 301,
            ],
        );

        return 1;
    }
}
