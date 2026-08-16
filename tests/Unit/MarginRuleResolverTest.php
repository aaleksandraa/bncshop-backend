<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierCategoryMarginRule;
use App\Services\Pricing\MarginRuleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarginRuleResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_child_category_inherits_parent_supplier_margin_rule(): void
    {
        $parent = Category::factory()->create(['name' => 'Monitori']);
        $child = Category::factory()->create([
            'name' => 'Gaming monitori',
            'parent_id' => $parent->id,
        ]);

        $supplier = Supplier::query()->create([
            'external_supplier_id' => 'supplier-1',
            'name' => 'arbis',
            'display_name' => 'Arbis',
            'code' => 'arbis',
        ]);

        SupplierCategoryMarginRule::query()->create([
            'supplier_id' => $supplier->id,
            'category_id' => $parent->id,
            'margin_percentage' => 20,
            'subcategory_scope' => 'all_descendants',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-margin-1',
            'name' => 'Monitor',
            'slug' => 'monitor',
            'status' => 'active',
            'is_public' => true,
            'category_id' => $child->id,
        ]);

        $result = app(MarginRuleResolver::class)->resolve($product, $supplier);

        $this->assertSame(20.0, $result['margin_percentage']);
        $this->assertSame('rule', $result['source']);
    }

    public function test_product_margin_has_priority_over_rule(): void
    {
        $category = Category::factory()->create();
        $supplier = Supplier::query()->create([
            'external_supplier_id' => 'supplier-2',
            'name' => 'comtrade',
            'display_name' => 'Comtrade',
            'code' => 'comtrade',
        ]);

        SupplierCategoryMarginRule::query()->create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'margin_percentage' => 30,
            'subcategory_scope' => 'all_descendants',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-margin-2',
            'name' => 'Telefon',
            'slug' => 'telefon',
            'status' => 'active',
            'is_public' => true,
            'category_id' => $category->id,
            'margin_percentage' => 15,
        ]);

        app(\App\Services\Sync\FieldLockService::class)->lockField($product, 'margin_percentage');

        $result = app(MarginRuleResolver::class)->resolve($product, $supplier);

        $this->assertSame(15.0, $result['margin_percentage']);
        $this->assertSame('product', $result['source']);
    }

    public function test_unlocked_product_margin_does_not_override_category_or_rule(): void
    {
        $category = Category::factory()->create([
            'margin_percentage' => 22,
        ]);
        $supplier = Supplier::query()->create([
            'external_supplier_id' => 'supplier-unlocked-copy',
            'name' => 'comtrade',
            'display_name' => 'Comtrade',
            'code' => 'comtrade-unlocked',
        ]);

        SupplierCategoryMarginRule::query()->create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'margin_percentage' => 30,
            'subcategory_scope' => 'all_descendants',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-margin-unlocked',
            'name' => 'Telefon copy',
            'slug' => 'telefon-copy',
            'status' => 'active',
            'is_public' => true,
            'category_id' => $category->id,
            'margin_percentage' => 15,
        ]);

        $result = app(MarginRuleResolver::class)->resolve($product, $supplier);

        $this->assertSame(30.0, $result['margin_percentage']);
        $this->assertSame('rule', $result['source']);
    }

    public function test_category_margin_rule_has_priority_over_supplier_rule_for_a1_new(): void
    {
        $category = Category::factory()->create(['name' => 'Monitori']);
        $supplier = Supplier::query()->create([
            'external_supplier_id' => 'supplier-cat-priority',
            'name' => 'arbis',
            'display_name' => 'Arbis',
            'code' => 'arbis',
        ]);

        SupplierCategoryMarginRule::query()->create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'margin_percentage' => 30,
            'subcategory_scope' => 'all_descendants',
            'is_active' => true,
        ]);

        \App\Models\CategoryMarginRule::query()->create([
            'category_id' => $category->id,
            'margin_percentage' => 22,
            'subcategory_scope' => 'all_descendants',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-priority-1',
            'name' => 'Monitor',
            'slug' => 'monitor-priority',
            'status' => 'active',
            'is_public' => true,
            'is_new' => true,
            'import_source' => 'a1',
            'category_id' => $category->id,
        ]);

        $result = app(MarginRuleResolver::class)->resolve($product, $supplier);

        $this->assertSame(22.0, $result['margin_percentage']);
        $this->assertSame('category_rule', $result['source']);
    }

    public function test_category_margin_rule_applies_to_a1_new_products_without_supplier(): void
    {
        $monitors = Category::factory()->create(['name' => 'Monitori']);

        \App\Models\CategoryMarginRule::query()->create([
            'category_id' => $monitors->id,
            'margin_percentage' => 22,
            'subcategory_scope' => 'all_descendants',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-cat-rule-1',
            'name' => 'Monitor A1',
            'slug' => 'monitor-a1',
            'status' => 'active',
            'is_public' => true,
            'is_new' => true,
            'import_source' => 'a1',
            'category_id' => $monitors->id,
        ]);

        $result = app(MarginRuleResolver::class)->resolve($product, null);

        $this->assertSame(22.0, $result['margin_percentage']);
        $this->assertSame('category_rule', $result['source']);
    }

    public function test_category_margin_rule_does_not_apply_to_eline_products(): void
    {
        $category = Category::factory()->create(['name' => 'Monitori']);

        \App\Models\CategoryMarginRule::query()->create([
            'category_id' => $category->id,
            'margin_percentage' => 22,
            'subcategory_scope' => 'all_descendants',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-eline-1',
            'name' => 'Monitor eLine',
            'slug' => 'monitor-eline',
            'status' => 'active',
            'is_public' => true,
            'is_new' => true,
            'import_source' => 'eline',
            'category_id' => $category->id,
        ]);

        $result = app(MarginRuleResolver::class)->resolve($product, null);

        $this->assertNull($result['margin_percentage']);
        $this->assertSame('none', $result['source']);
    }

    public function test_category_margin_rule_does_not_apply_to_used_a1_products(): void
    {
        $category = Category::factory()->create(['name' => 'Monitori']);

        \App\Models\CategoryMarginRule::query()->create([
            'category_id' => $category->id,
            'margin_percentage' => 22,
            'subcategory_scope' => 'all_descendants',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-used-1',
            'name' => 'Monitor used',
            'slug' => 'monitor-used',
            'status' => 'active',
            'is_public' => true,
            'is_new' => false,
            'import_source' => 'a1',
            'margin_percentage' => 10,
            'category_id' => $category->id,
        ]);

        $result = app(MarginRuleResolver::class)->resolve($product, null);

        $this->assertNull($result['margin_percentage']);
        $this->assertSame('none', $result['source']);
    }

    public function test_parent_category_rule_without_subcategories_does_not_apply_to_child(): void
    {
        $parent = Category::factory()->create(['name' => 'Monitori']);
        $child = Category::factory()->create([
            'name' => 'Gaming monitori',
            'parent_id' => $parent->id,
        ]);

        \App\Models\CategoryMarginRule::query()->create([
            'category_id' => $parent->id,
            'margin_percentage' => 20,
            'subcategory_scope' => 'category_only',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-child-only',
            'name' => 'Gaming monitor',
            'slug' => 'gaming-monitor',
            'status' => 'active',
            'is_public' => true,
            'is_new' => true,
            'import_source' => 'a1',
            'category_id' => $child->id,
        ]);

        $result = app(MarginRuleResolver::class)->resolve($product, null);

        $this->assertSame('none', $result['source']);
    }

    public function test_parent_category_rule_with_subcategories_applies_to_child(): void
    {
        $parent = Category::factory()->create(['name' => 'Monitori']);
        $child = Category::factory()->create([
            'name' => 'Gaming monitori',
            'parent_id' => $parent->id,
        ]);

        \App\Models\CategoryMarginRule::query()->create([
            'category_id' => $parent->id,
            'margin_percentage' => 20,
            'subcategory_scope' => 'all_descendants',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-child-inherit',
            'name' => 'Gaming monitor',
            'slug' => 'gaming-monitor-inherit',
            'status' => 'active',
            'is_public' => true,
            'is_new' => true,
            'import_source' => 'a1',
            'category_id' => $child->id,
        ]);

        $result = app(MarginRuleResolver::class)->resolve($product, null);

        $this->assertSame(20.0, $result['margin_percentage']);
        $this->assertSame('category_rule', $result['source']);
    }

    public function test_subcategory_rule_applies_only_to_that_subcategory(): void
    {
        $parent = Category::factory()->create(['name' => 'Monitori']);
        $child = Category::factory()->create([
            'name' => 'Gaming monitori',
            'parent_id' => $parent->id,
        ]);
        $sibling = Category::factory()->create([
            'name' => 'Office monitori',
            'parent_id' => $parent->id,
        ]);

        \App\Models\CategoryMarginRule::query()->create([
            'category_id' => $child->id,
            'margin_percentage' => 25,
            'subcategory_scope' => 'category_only',
            'is_active' => true,
        ]);

        $gamingProduct = Product::query()->create([
            'external_product_id' => 'prod-gaming-only',
            'name' => 'Gaming monitor',
            'slug' => 'gaming-only',
            'status' => 'active',
            'is_public' => true,
            'is_new' => true,
            'import_source' => 'a1',
            'category_id' => $child->id,
        ]);

        $officeProduct = Product::query()->create([
            'external_product_id' => 'prod-office-only',
            'name' => 'Office monitor',
            'slug' => 'office-only',
            'status' => 'active',
            'is_public' => true,
            'is_new' => true,
            'import_source' => 'a1',
            'category_id' => $sibling->id,
        ]);

        $gamingResult = app(MarginRuleResolver::class)->resolve($gamingProduct, null);
        $officeResult = app(MarginRuleResolver::class)->resolve($officeProduct, null);

        $this->assertSame(25.0, $gamingResult['margin_percentage']);
        $this->assertSame('category_rule', $gamingResult['source']);
        $this->assertSame('none', $officeResult['source']);
    }

    public function test_child_category_rule_overrides_parent_with_subcategories(): void
    {
        $parent = Category::factory()->create(['name' => 'Monitori']);
        $child = Category::factory()->create([
            'name' => 'Gaming monitori',
            'parent_id' => $parent->id,
        ]);

        \App\Models\CategoryMarginRule::query()->create([
            'category_id' => $parent->id,
            'margin_percentage' => 20,
            'subcategory_scope' => 'all_descendants',
            'is_active' => true,
        ]);

        \App\Models\CategoryMarginRule::query()->create([
            'category_id' => $child->id,
            'margin_percentage' => 28,
            'subcategory_scope' => 'category_only',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-child-override',
            'name' => 'Gaming monitor',
            'slug' => 'gaming-override',
            'status' => 'active',
            'is_public' => true,
            'is_new' => true,
            'import_source' => 'a1',
            'category_id' => $child->id,
        ]);

        $result = app(MarginRuleResolver::class)->resolve($product, null);

        $this->assertSame(28.0, $result['margin_percentage']);
        $this->assertSame('category_rule', $result['source']);
    }

    public function test_selected_subcategories_apply_only_to_checked_categories(): void
    {
        $parent = Category::factory()->create(['name' => 'Monitori']);
        $gaming = Category::factory()->create([
            'name' => 'Gaming monitori',
            'parent_id' => $parent->id,
        ]);
        $office = Category::factory()->create([
            'name' => 'Office monitori',
            'parent_id' => $parent->id,
        ]);

        $rule = \App\Models\CategoryMarginRule::query()->create([
            'category_id' => $parent->id,
            'margin_percentage' => 18,
            'subcategory_scope' => 'selected',
            'include_parent_category' => false,
            'is_active' => true,
        ]);
        $rule->targetCategories()->sync([$gaming->id]);

        $gamingProduct = Product::query()->create([
            'external_product_id' => 'prod-selected-gaming',
            'name' => 'Gaming monitor',
            'slug' => 'selected-gaming',
            'status' => 'active',
            'is_public' => true,
            'is_new' => true,
            'import_source' => 'a1',
            'category_id' => $gaming->id,
        ]);

        $officeProduct = Product::query()->create([
            'external_product_id' => 'prod-selected-office',
            'name' => 'Office monitor',
            'slug' => 'selected-office',
            'status' => 'active',
            'is_public' => true,
            'is_new' => true,
            'import_source' => 'a1',
            'category_id' => $office->id,
        ]);

        $resolver = app(MarginRuleResolver::class);

        $this->assertSame(18.0, $resolver->resolve($gamingProduct, null)['margin_percentage']);
        $this->assertSame('none', $resolver->resolve($officeProduct, null)['source']);
    }

    public function test_locked_product_margin_applies_to_a1_new_products(): void
    {
        $category = Category::factory()->create(['name' => 'Monitori']);

        $product = Product::query()->create([
            'external_product_id' => 'prod-locked-new',
            'name' => 'Monitor locked',
            'slug' => 'monitor-locked-new',
            'status' => 'active',
            'is_public' => true,
            'is_new' => true,
            'import_source' => 'a1',
            'category_id' => $category->id,
            'margin_percentage' => 18,
        ]);

        app(\App\Services\Sync\FieldLockService::class)->lockField($product, 'margin_percentage');

        $result = app(MarginRuleResolver::class)->resolve($product, null);

        $this->assertSame(18.0, $result['margin_percentage']);
        $this->assertSame('product', $result['source']);
    }
}
