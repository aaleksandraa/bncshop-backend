<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_menu_returns_nested_tree(): void
    {
        $menu = Menu::query()->create([
            'name' => 'Glavni meni',
            'slug' => 'header',
            'is_active' => true,
        ]);

        $category = Category::query()->create([
            'external_category_id' => 'test-cat-1',
            'name' => 'Laptopi',
            'full_slug' => 'laptopi',
            'path' => '1',
            'depth' => 0,
            'status' => 'active',
        ]);

        $page = CmsPage::query()->create([
            'title' => 'Kontakt',
            'slug' => 'kontakt',
            'status' => 'active',
        ]);

        $parent = MenuItem::query()->create([
            'menu_id' => $menu->id,
            'type' => MenuItem::TYPE_CATEGORY,
            'category_id' => $category->id,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        MenuItem::query()->create([
            'menu_id' => $menu->id,
            'parent_id' => $parent->id,
            'type' => MenuItem::TYPE_PAGE,
            'cms_page_id' => $page->id,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/menus/header');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'header')
            ->assertJsonPath('data.items.0.label', 'Laptopi')
            ->assertJsonPath('data.items.0.url', '/kategorija/laptopi')
            ->assertJsonPath('data.items.0.children.0.label', 'Kontakt')
            ->assertJsonPath('data.items.0.children.0.url', '/kontakt');
    }
}
