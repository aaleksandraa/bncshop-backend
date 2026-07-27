<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Support\CategoryAdminSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryAdminSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_format_option_label_for_root_category_includes_type_and_product_count(): void
    {
        $category = Category::factory()->create([
            'name' => 'Računari',
            'display_name' => 'Računari',
            'full_slug' => 'racunari',
            'parent_id' => null,
            'depth' => 1,
            'path' => '1',
        ]);

        Product::factory()->create(['category_id' => $category->id]);

        $category->loadCount('products');

        $label = CategoryAdminSearch::formatOptionLabel($category);

        $this->assertStringContainsString('Računari — glavna kategorija', $label);
        $this->assertStringContainsString('(1 proizvod)', $label);
    }

    public function test_format_option_label_for_subcategory_includes_parent_path(): void
    {
        $parent = Category::factory()->create([
            'name' => 'Periferija',
            'display_name' => 'Periferija',
            'full_slug' => 'it-oprema/periferija',
            'parent_id' => null,
            'depth' => 1,
            'path' => '1',
        ]);

        $child = Category::factory()->create([
            'name' => 'Monitori',
            'display_name' => 'Monitori',
            'full_slug' => 'it-oprema/periferija/monitori',
            'parent_id' => $parent->id,
            'depth' => 2,
            'path' => '1.2',
        ]);

        $child->loadCount('products');

        $byId = Category::query()
            ->whereIn('id', [$parent->id, $child->id])
            ->get(['id', 'name', 'display_name', 'parent_id'])
            ->keyBy('id');

        $label = CategoryAdminSearch::formatOptionLabel($child, $byId);

        $this->assertStringContainsString('Monitori — podkategorija: Periferija', $label);
        $this->assertStringContainsString('(0 proizvoda)', $label);
    }

    public function test_options_for_search_returns_enriched_labels(): void
    {
        Category::factory()->create([
            'name' => 'Gaming laptopi',
            'display_name' => 'Gaming laptopi',
            'full_slug' => 'racunari/laptopi/gaming',
            'parent_id' => null,
            'depth' => 1,
            'path' => '3',
        ]);

        $options = CategoryAdminSearch::optionsForSearch('gaming');

        $this->assertNotEmpty($options);
        $this->assertStringContainsString('Gaming laptopi — glavna kategorija', (string) reset($options));
    }
}
