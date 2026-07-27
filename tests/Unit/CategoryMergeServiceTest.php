<?php

namespace Tests\Unit;

use App\Models\AttributeCategoryMapping;
use App\Models\AttributeDefinition;
use App\Models\Category;
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
}
