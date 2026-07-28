<?php

namespace Tests\Unit;

use App\Models\AttributeCategoryMapping;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\OlxCategory;
use App\Models\OlxCategoryMapping;
use App\Models\Product;
use App\Services\Catalog\CategoryMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Tests\TestCase;

class CategoryMergeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_merge_moves_products_and_deactivates_source(): void
    {
        $parent = Category::query()->create([
            'external_category_id' => 'cat-parent',
            'name' => 'Laptopi',
            'full_slug' => 'racunari/laptopi',
            'status' => 'active',
            'depth' => 1,
        ]);

        $duplicate = Category::query()->create([
            'external_category_id' => 'cat-child-dup',
            'name' => 'Laptopi',
            'full_slug' => 'racunari/laptopi/laptopi',
            'parent_id' => $parent->id,
            'status' => 'active',
            'depth' => 2,
        ]);

        $product = Product::query()->create([
            'external_product_id' => 'prod-cat-merge-1',
            'name' => 'Asus Laptop',
            'slug' => 'asus-laptop',
            'category_id' => $duplicate->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        $result = app(CategoryMergeService::class)->merge($parent, $duplicate);

        $this->assertSame(1, $result['products']);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'category_id' => $parent->id,
        ]);

        $duplicate->refresh();
        $this->assertSame('inactive', $duplicate->status);
    }

    public function test_merge_creates_redirect_from_old_slug(): void
    {
        $target = Category::query()->create([
            'external_category_id' => 'cat-target',
            'name' => 'Laptopi',
            'full_slug' => 'racunari/laptopi',
            'status' => 'active',
        ]);

        $source = Category::query()->create([
            'external_category_id' => 'cat-source',
            'name' => 'Laptopi podkategorija',
            'full_slug' => 'racunari/laptopi/laptopi',
            'status' => 'active',
        ]);

        app(CategoryMergeService::class)->merge($target, $source);

        $this->assertDatabaseHas('redirects', [
            'from_path' => '/kategorija/racunari/laptopi/laptopi',
            'to_path' => '/kategorija/racunari/laptopi',
            'status_code' => 301,
        ]);
    }

    public function test_merge_consolidates_attribute_mappings(): void
    {
        $target = Category::query()->create([
            'external_category_id' => 'cat-map-target',
            'name' => 'Monitori',
            'full_slug' => 'monitori',
            'status' => 'active',
        ]);

        $source = Category::query()->create([
            'external_category_id' => 'cat-map-source',
            'name' => 'Monitori alt',
            'full_slug' => 'monitori-alt',
            'status' => 'active',
        ]);

        $attribute = AttributeDefinition::query()->create([
            'external_attribute_id' => 'attr-size',
            'name' => 'Dijagonala',
            'internal_type' => 'text',
            'is_public' => true,
            'is_filter' => true,
        ]);

        AttributeCategoryMapping::query()->create([
            'attribute_definition_id' => $attribute->id,
            'category_id' => $source->id,
            'external_category_id' => 'cat-map-source',
            'category_name' => 'Monitori alt',
            'is_filter_enabled' => true,
            'is_public_enabled' => true,
            'sort_order' => 1,
        ]);

        app(CategoryMergeService::class)->merge($target, $source);

        $this->assertDatabaseHas('attribute_category_mappings', [
            'attribute_definition_id' => $attribute->id,
            'category_id' => $target->id,
            'is_filter_enabled' => true,
        ]);

        $this->assertDatabaseMissing('attribute_category_mappings', [
            'attribute_definition_id' => $attribute->id,
            'category_id' => $source->id,
        ]);
    }

    public function test_merge_reparents_children_when_enabled(): void
    {
        $target = Category::query()->create([
            'external_category_id' => 'cat-reparent-target',
            'name' => 'Laptopi',
            'full_slug' => 'laptopi',
            'status' => 'active',
        ]);

        $source = Category::query()->create([
            'external_category_id' => 'cat-reparent-source',
            'name' => 'Laptopi duplikat',
            'full_slug' => 'laptopi-duplikat',
            'status' => 'active',
        ]);

        $child = Category::query()->create([
            'external_category_id' => 'cat-reparent-child',
            'name' => 'Gaming laptopi',
            'full_slug' => 'laptopi-duplikat/gaming',
            'parent_id' => $source->id,
            'status' => 'active',
        ]);

        app(CategoryMergeService::class)->merge($target, $source, [
            'reparent_children' => true,
        ]);

        $child->refresh();
        $this->assertSame($target->id, $child->parent_id);
    }

    public function test_cannot_merge_into_descendant(): void
    {
        $parent = Category::query()->create([
            'external_category_id' => 'cat-cycle-parent',
            'name' => 'Parent',
            'full_slug' => 'parent',
            'status' => 'active',
        ]);

        $child = Category::query()->create([
            'external_category_id' => 'cat-cycle-child',
            'name' => 'Child',
            'full_slug' => 'parent/child',
            'parent_id' => $parent->id,
            'status' => 'active',
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(CategoryMergeService::class)->merge($child, $parent);
    }

    public function test_merge_handles_conflicting_olx_mappings(): void
    {
        $target = Category::query()->create([
            'external_category_id' => 'cat-olx-target',
            'name' => 'Laptopi',
            'full_slug' => 'laptopi',
            'status' => 'active',
        ]);

        $source = Category::query()->create([
            'external_category_id' => 'cat-olx-source',
            'name' => 'Laptopi duplikat',
            'full_slug' => 'laptopi-duplikat',
            'status' => 'active',
        ]);

        OlxCategory::query()->create([
            'id' => 100,
            'name' => 'Laptopi OLX',
            'slug' => 'laptopi',
            'path' => 'racunari/laptopi',
        ]);

        OlxCategory::query()->create([
            'id' => 200,
            'name' => 'Laptopi OLX alt',
            'slug' => 'laptopi-alt',
            'path' => 'racunari/laptopi-alt',
        ]);

        OlxCategoryMapping::query()->create([
            'category_id' => $target->id,
            'olx_category_id' => 100,
            'olx_category_path' => 'racunari/laptopi',
            'is_enabled' => true,
        ]);

        OlxCategoryMapping::query()->create([
            'category_id' => $source->id,
            'olx_category_id' => 200,
            'olx_category_path' => 'racunari/laptopi-alt',
            'is_enabled' => true,
        ]);

        app(CategoryMergeService::class)->merge($target, $source);

        $this->assertDatabaseHas('olx_category_mappings', [
            'category_id' => $target->id,
            'olx_category_id' => 100,
        ]);

        $this->assertDatabaseMissing('olx_category_mappings', [
            'category_id' => $source->id,
        ]);
    }

    public function test_merge_deactivates_source_menu_items(): void
    {
        $menu = Menu::query()->create([
            'name' => 'Header',
            'slug' => 'header',
            'is_active' => true,
        ]);

        $target = Category::query()->create([
            'external_category_id' => 'cat-menu-target',
            'name' => 'Laptopi',
            'full_slug' => 'racunari/laptopi',
            'status' => 'active',
        ]);

        $source = Category::query()->create([
            'external_category_id' => 'cat-menu-source',
            'name' => 'Laptopi',
            'full_slug' => 'racunari/laptopi/laptopi',
            'parent_id' => $target->id,
            'status' => 'active',
        ]);

        $parentMenuItem = MenuItem::query()->create([
            'menu_id' => $menu->id,
            'type' => MenuItem::TYPE_CATEGORY,
            'category_id' => $target->id,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $childMenuItem = MenuItem::query()->create([
            'menu_id' => $menu->id,
            'parent_id' => $parentMenuItem->id,
            'type' => MenuItem::TYPE_CATEGORY,
            'category_id' => $source->id,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        app(CategoryMergeService::class)->merge($target, $source);

        $childMenuItem->refresh();
        $this->assertFalse($childMenuItem->is_active);
    }
}
